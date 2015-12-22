<?php

namespace IngatlanCom\ApiClient;

use Guzzle\Http\Client;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Exception\MultiTransferException;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Message\Response;
use IngatlanCom\ApiClient\Exception\JSendFailException;
use IngatlanCom\ApiClient\Exception\NotAuthenticatedException;
use IngatlanCom\ApiClient\Exception\ServerErrorException;
use IngatlanCom\ApiClient\Service\ClientFactoryService;
use JSend\InvalidJSendException;
use JSend\JSendResponse;
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
     * ApiClient constructor.
     *
     * @param string $apiUrl ingatlan.com API url
     * @param Pool $stashPool Stash példány az authentikációs token tárolására
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
            $item = $this->stashPool->getItem($username . 'Token');
            //cache for 10 minutes
            $item->set($token, 600);
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
            $result = $this->sendRequest(RequestInterface::POST, '/auth/login', json_encode(array(
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
        $result = $this->sendRequest(RequestInterface::PUT, '/ads/' . $ad['ownId'], json_encode($ad));
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
        $result = $this->sendRequest(RequestInterface::GET, '/ads/' . $adOwnId);

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
        $result = $this->sendRequest(RequestInterface::DELETE, '/ads/' . $adOwnId);
        return $result['ad'];
    }

    /**
     * visszaadja az iroda összes hirdetésének azonosítóit (ingatlan.com-os és saját azonosítót)
     *
     * @return array hirdetés ID-k
     */
    public function getAdIds()
    {
        $data = $this->sendRequest(RequestInterface::GET, '/ads/ids');

        return $data['ids'];
    }

    /**
     * iroda ingatlan.com-on lévő hirdetéseit szinkronba hozza az iroda saját rendszerében lévő hirdetésekkel
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
     * @see PhotoSync::syncPhotos()
     * @return PhotoSync
     */
    public function syncPhotos($adOwnId, array $photos, $forceImageDataUpdate = false, array $uploadedPhotos = null)
    {
        $service = new PhotoSync($this);
        return $service->syncPhotos($adOwnId, $photos, $forceImageDataUpdate, $uploadedPhotos);
    }

    /**
     * Fotó feltöltés Request létrehozása
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @param array $photoData fotó adatok
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

        $request = $this->getRequest(RequestInterface::PUT, '/ads/' . $adOwnId . '/photos/' . $photoOwnId, json_encode($photoData));

        return $request;
    }

    /**
     * Fotó feltöltése/módosítása
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @param array $photoData fotó adatok
     * @return array feltöltött fotó adatok
     * @throws JSendFailException
     */
    public function putPhoto($adOwnId, array $photoData)
    {
        $request = $this->createPhotoPutRequest($adOwnId, $photoData);
        try {
            $response = $request->send();
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
     * @param array $photosByOwnId hirdetés képeinek adatai, saját azonosító szerint indexelve
     * @return Response[]
     * @throws MultiTransferException
     */
    public function putPhotosMulti($adOwnId, array $photosByOwnId)
    {
        $requests = array();
        foreach ($photosByOwnId as $photo) {
            $requests[] = $this->createPhotoPutRequest($adOwnId, $photo);
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
        $this->sendRequest(RequestInterface::DELETE, '/ads/' . $adOwnId . '/photos/' . $photoId);
        return true;
    }

    /**
     * Hirdetés fotóinak törlése, több szálon
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @param array $photosByOwnId hirdetés képeinek adatai, saját azonosító szerint indexelve
     * @return Response[]
     * @throws MultiTransferException
     */
    public function deletePhotosMulti($adOwnId, array $photosByOwnId)
    {
        $requests = array();
        foreach (array_keys($photosByOwnId) as $photoOwnIdToDelete) {
            $requests[] = $this->getRequest(RequestInterface::DELETE, '/ads/' . $adOwnId . '/photos/' . $photoOwnIdToDelete);
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
        $photos = $this->sendRequest(RequestInterface::GET, '/ads/' . $adOwnId . '/photos');
        return $photos['photos'];
    }

    /**
     * Hirdetés képeinek sorrendezése
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @param array $photoOwnIds fotók saját azonosítója, kívánt sorrendben
     * @return array hirdetés fotói
     */
    public function putPhotoOrder($adOwnId, $photoOwnIds)
    {
        $photos = $this->sendRequest(RequestInterface::PUT, '/ads/' . $adOwnId . '/photoOrder', json_encode(array('order' => $photoOwnIds)));
        return $photos['photos'];
    }

    /**
     * Guzzle Request előállítása az API-nak megfelelő headerekkel
     *
     * @param string $method HTTP method
     * @param string $endpoint path
     * @param string|null $body content
     * @return RequestInterface
     * @throws NotAuthenticatedException
     */
    private function getRequest($method, $endpoint, $body = null)
    {
        $request = $this->client->createRequest($method, '/v' . self::APIVERSION . $endpoint, null, $body);

        $request->addHeader('Accept', 'application/json');

        if ($body) {
            $request->addHeader('Content-type', 'application/json');
        }

        if ($this->token) {
            $request->addHeader('Authorization', 'Bearer ' . $this->token);
        } elseif ('/auth/login' != $endpoint) {
            throw new NotAuthenticatedException('Not authenticated');
        }

        return $request;
    }

    /**
     * Request legyártás, elküldés, válasz feldolgozás
     *
     * @param string $method HTTP method
     * @param string $endpoint path
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
            $response = $request->send();
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
        }

        return $this->parseResponse($response);
    }

    /**
     * Request-ek párhuzamos elküldése
     *
     * @param RequestInterface[] $requests
     * @return Response[]
     * @throws MultiTransferException
     */
    private function sendMultiRequest($requests)
    {
        if (count($requests)) {
            $responses = $this->client->send($requests);

            return $responses;
        }
        return array();
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
        $jsendResponse = JSendResponse::decode($response->getBody(true));

        if ($jsendResponse->isFail()) {
            throw new JSendFailException('Call failed', 0, null, $jsendResponse);
        }

        if ($jsendResponse->isError()) {
            throw new ServerErrorException($jsendResponse->getErrorMessage(), 0, null, $jsendResponse);
        }

        return $jsendResponse->getData();
    }
}
