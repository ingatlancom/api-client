<?php
namespace IngatlanCom\ApiClient;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use IngatlanCom\ApiClient\Exception\JSendFailException;
use IngatlanCom\ApiClient\Exception\NotAuthenticatedException;
use IngatlanCom\ApiClient\Exception\ServerErrorException;
use IngatlanCom\ApiClient\Service\ClientFactoryService;
use JSend\InvalidJSendException;
use JSend\JSendResponse;
use Psr\Http\Message\RequestInterface;
use Stash\Item;
use Stash\Pool;

/**
 * Class ApiClient
 *
 * ingatlan.com API kliens
 *
 * @package IngatlanCom\ApiClient
 */
class ApiClient
{
    const APIVERSION = 1;
    const NUMBER_OF_MAX_PARALLEL_REQUESTS = 4;

    /**
     * JWT token
     * @var string
     */
    private $token;

    /**
     * Cache a JWT tokennek
     * @var Pool
     */
    private $stashPool;

    /**
     * @var Client Guzzle Client az API kapcsolódáshoz
     */
    private $client;

    /**
     * ApiClient constructor
     *
     * @param string               $apiUrl ingatlan.com API url
     * @param Pool                 $stashPool Stash példány az authentikációs token tárolására
     * @param ClientFactoryService $clientFactoryService Guzzle kliens factory
     */
    public function __construct($apiUrl, Pool $stashPool = null, ClientFactoryService $clientFactoryService = null)
    {
        $this->stashPool = $stashPool;
        $clientFactoryService = null != $clientFactoryService ? $clientFactoryService : new ClientFactoryService();
        $this->client = $clientFactoryService->getClient($apiUrl);
    }

    /**
     * Bejelentkezés
     *
     * @param string $username
     * @param string $password
     * @throws NotAuthenticatedException
     */
    public function login($username, $password)
    {
        if (null == $this->token) {
            $this->token = $this->getToken($username, $password);
        }
    }

    /**
     * Authentikációs token lekérése a cache-ből, vagy a szervertől.
     *
     * @param string $username felhasználónév
     * @param string $password jelszó
     * @return string JWT token
     * @throws NotAuthenticatedException
     */
    private function getToken($username, $password)
    {
        //TODO: letárolni tovább, nézni h érvényes-e
        if ($this->stashPool) {
            $item = $this->stashPool->getItem($username . 'Token');
            if (!$item->isMiss()) {
                return $item->get();
            }
        }

        $token = $this->callLogin($username, $password);

        if ($this->stashPool) {
            /** @var Item $item */
            $item = $this->stashPool->getItem($username . 'Token');
            //cache for 10 minutes
            $item->set($token)->setTTL(600);
        }

        return $token;
    }

    /**
     * API bejelentkezés meghívása
     *
     * @param string $username felhasználónév
     * @param string $password jelszó
     * @return string JWT token
     * @throws NotAuthenticatedException
     */
    private function callLogin($username, $password)
    {
        try {
            $result = $this->sendRequest('POST', '/auth/login', json_encode(array(
                'username' => $username,
                'password' => $password
            )));
        } catch (JSendFailException $e) {
            throw new NotAuthenticatedException('Login failed', 0, $e);
        }

        return $result['token'];
    }

    /**
     * Hirdetés feladása/módosítása
     *
     * @param array $ad hirdetésadatok
     * @return array a feladott hirdetés adatai
     * @throws JSendFailException
     */
    public function putAd(array $ad)
    {
        $result = $this->sendRequest('PUT', '/ads/' . $ad['ownId'], json_encode($ad));

        return $result['ad'];
    }

    /**
     * Hirdetés lekérése
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @return array hirdetés adatai
     */
    public function getAd($adOwnId)
    {
        $result = $this->sendRequest('GET', '/ads/' . $adOwnId);

        return $result['ad'];
    }

    /**
     * Hirdetés törlése
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @return array törölt hirdetés adatai
     */
    public function deleteAd($adOwnId)
    {
        $result = $this->sendRequest('DELETE', '/ads/' . $adOwnId);

        return $result['ad'];
    }

    /**
     * Visszaadja az iroda összes hirdetésének azonosítóit (ingatlan.com-os és saját azonosítót)
     *
     * @return array hirdetés ID-k
     */
    public function getAdIds()
    {
        $data = $this->sendRequest('GET', '/ads/ids');

        return $data['ids'];
    }

    /**
     * Iroda ingatlan.com-on lévő hirdetéseit szinkronba hozza az iroda saját rendszerében lévő hirdetésekkel
     * (kitörli az ingatlan.com-ról azokat a hirdetéseket, amik már nincsenek meg az iroda saját rendszerében)
     *
     * @param array $adIds az iroda saját rendszerében lévő összes hirdetés ID-ja
     * @return array a törölt hirdetések ID-i
     * @throws \Exception
     */
    public function syncAds(array $adIds)
    {
        try {
            $ids = $this->getAdIds();
        } catch (\Exception $e) {
            throw new \Exception('A hirdetés ID-k lekérése nem sikerült', 0, $e);
        }

        $ownIds = array_reduce(
            $ids,
            function ($carry, $item) {
                if ($item['ownId'] && 0 == $item['statusId']) {
                    $carry[] = $item['ownId'];
                }
                return $carry;
            },
            array()
        );

        $idsToDelete = array_diff($ownIds, $adIds);
        foreach ($idsToDelete as $id) {
            $this->deleteAd($id);
        }

        return $idsToDelete;
    }

    /**
     * @param string     $adOwnId hirdetés saját azonosító
     * @param array      $photos iroda rendszerében levő fotók adatai
     * @param bool       $forceImageDataUpdate akkor is töltsük le a fotót az iroda rendszeréből, ha már fel van töltve adott azonosítóval
     * @param array|null $uploadedPhotos ingatlan.com rendszerében levő fotók adatai
     * @param bool       $paralellDownload párhuzamos fotóletöltés az iroda szerveréről
     * @return PhotoSync
     */
    public function syncPhotos(
        $adOwnId,
        array $photos,
        $forceImageDataUpdate = false,
        array $uploadedPhotos = null,
        $paralellDownload = false
    ) {
        $service = new PhotoSync($this);

        return $service->syncPhotos($adOwnId, $photos, $forceImageDataUpdate, $uploadedPhotos, $paralellDownload);
    }

    /**
     * Fotó feltöltés Request létrehozása
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @param array  $photoData fotó adatok
     * @return RequestInterface
     */
    private function createPhotoPutRequest($adOwnId, array $photoData)
    {
        $photoOwnId = $photoData['ownId'];

        unset($photoData['ownId']);
        unset($photoData['location']);
        if (isset($photoData['imageData'])) {
            $photoData['imageData'] = base64_encode($photoData['imageData']);
        }

        $request = $this->getRequest('PUT', '/ads/' . $adOwnId . '/photos/' . $photoOwnId, json_encode($photoData));

        return $request;
    }

    /**
     * Fotó feltöltése/módosítása
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @param array  $photoData fotó adatok
     * @return array feltöltött fotó adatok
     * @throws JSendFailException
     */
    public function putPhoto($adOwnId, array $photoData)
    {
        $request = $this->createPhotoPutRequest($adOwnId, $photoData);
        try {
            $response = $this->client->send($request);
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
        }

        $response = $this->parseResponse($response);

        return $response['photo'];
    }

    /**
     * Hirdetés fotóinak feltöltése, több szálon
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @param array  $photosByOwnId hirdetés képeinek adatai, saját azonosító szerint indexelve
     * @return array
     */
    public function putPhotosMulti($adOwnId, array $photosByOwnId)
    {
        $requests = array();
        foreach ($photosByOwnId as $photoOwnId => $photo) {
            $requests[$photoOwnId] = $this->createPhotoPutRequest($adOwnId, $photo);
        }

        return $this->sendMultiRequest($requests);
    }

    /**
     * Fotó törlése
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @param string $photoId fotó saját azonosítója
     * @return bool sikeres törlés
     */
    public function deletePhoto($adOwnId, $photoId)
    {
        $this->sendRequest('DELETE', '/ads/' . $adOwnId . '/photos/' . $photoId);

        return true;
    }

    /**
     * Hirdetés fotóinak törlése, több szálon
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @param array  $photosByOwnId hirdetés képeinek adatai, saját azonosító szerint indexelve
     * @return array
     */
    public function deletePhotosMulti($adOwnId, array $photosByOwnId)
    {
        $requests = array();
        foreach (array_keys($photosByOwnId) as $photoOwnIdToDelete) {
            $requests[$photoOwnIdToDelete] = $this->getRequest('DELETE', '/ads/' . $adOwnId . '/photos/' . $photoOwnIdToDelete);
        }

        return $this->sendMultiRequest($requests);
    }

    /**
     * Hirdetés fotóinak lekérése
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @return array fotók adatai
     */
    public function getPhotos($adOwnId)
    {
        $photos = $this->sendRequest('GET', '/ads/' . $adOwnId . '/photos');

        return $photos['photos'];
    }

    /**
     * Hirdetés képeinek sorrendezése
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @param array  $photoOwnIds fotók saját azonosítója, kívánt sorrendben
     * @return array hirdetés fotói
     */
    public function putPhotoOrder($adOwnId, $photoOwnIds)
    {
        $photos = $this->sendRequest('PUT', '/ads/' . $adOwnId . '/photoOrder',
            json_encode(array('order' => $photoOwnIds)));

        return $photos['photos'];
    }

    /**
     * Guzzle Request előállítása az API-nak megfelelő headerekkel
     *
     * @param string      $method HTTP method
     * @param string      $endpoint path
     * @param string|null $body content
     * @return RequestInterface
     * @throws NotAuthenticatedException
     */
    private function getRequest($method, $endpoint, $body = null)
    {
        $headers['Accept'] = 'application/json';

        if ($body) {
            $headers['Content-type'] = 'application/json';
        }

        if ($this->token) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        } elseif ('/auth/login' != $endpoint) {
            throw new NotAuthenticatedException('Not authenticated');
        }

        return new Request($method, '/v' . self::APIVERSION . $endpoint, $headers, $body);
    }

    /**
     * Request legyártás, elküldés, válasz feldolgozás
     *
     * @param string      $method HTTP method
     * @param string      $endpoint path
     * @param string|null $body content
     * @return array
     * @throws InvalidJSendException if JSend does not conform to spec
     * @throws ServerErrorException
     * @throws JSendFailException
     */
    private function sendRequest($method, $endpoint, $body = null)
    {
        $request = $this->getRequest($method, $endpoint, $body);

        try {
            $response = $this->client->send($request);
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
        }

        return $this->parseResponse($response);
    }

    /**
     * Request-ek párhuzamos elküldése
     *
     * @param RequestInterface[] $requests
     * @return array
     */
    private function sendMultiRequest($requests)
    {
        $results = [];

        if (count($requests)) {
            $promises = [];
            foreach ($requests as $photoOwnId => $request) {
                $promises[$photoOwnId] = $this->client->sendAsync($request);
            }
            Promise\each_limit(
                $promises,
                self::NUMBER_OF_MAX_PARALLEL_REQUESTS,
                function ($value, $idx) use (&$results) {
                    $results[$idx] = ['state' => PromiseInterface::FULFILLED, 'value' => $value];
                },
                function ($reason, $idx) use (&$results) {
                    $results[$idx] = ['state' => PromiseInterface::REJECTED, 'value' => $reason];
                }
            )->wait();
        }

        return $results;
    }

    /**
     * API válasz feldolgozása
     *
     * @param Response $response
     * @return array
     * @throws InvalidJSendException
     * @throws ServerErrorException
     * @throws JSendFailException
     */
    public function parseResponse(Response $response)
    {
        try {
            $jsendResponse = JSendResponse::decode((string)$response->getBody());
        } catch (\UnexpectedValueException $exception) {
            preg_match("/<title>\\d+ .*<\\/title>/", $response->getBody(), $message);
            $jsendResponse = JSendResponse::error(
                strip_tags($message[0]),
                $response->getStatusCode()
            );
        }


        if ($jsendResponse->isFail()) {
            throw new JSendFailException('Call failed', 0, null, $jsendResponse);
        }

        if ($jsendResponse->isError()) {
            throw new ServerErrorException($jsendResponse->getErrorMessage(), 0, null, $jsendResponse);
        }

        return $jsendResponse->getData();
    }
}
