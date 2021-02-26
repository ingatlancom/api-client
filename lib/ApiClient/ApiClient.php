<?php
namespace IngatlanCom\ApiClient;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use IngatlanCom\ApiClient\Enum\PhotoLabelEnum;
use IngatlanCom\ApiClient\Exception\InvalidValueException;
use IngatlanCom\ApiClient\Exception\JSendFailException;
use IngatlanCom\ApiClient\Exception\JWTTokenException;
use IngatlanCom\ApiClient\Exception\NotAuthenticatedException;
use IngatlanCom\ApiClient\Exception\ServerErrorException;
use IngatlanCom\ApiClient\Service\ClientFactoryService;
use IngatlanCom\ApiClient\Service\PhotoResizeService;
use JSend\InvalidJSendException;
use JSend\JSendResponse;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Stash\Driver\Ephemeral;
use Stash\Item;
use Stash\Pool;
use UnexpectedValueException;

/**
 * ingatlan.com API kliens
 */
class ApiClient
{
    const APIVERSION = 1;
    const CLIENT_VERSION = "3.2.1";
    const NUMBER_OF_MAX_PARALLEL_REQUESTS = 4;
    const PUT_WITH_IMAGE_DATA = 2;
    const PUT_WITHOUT_IMAGE_DATA = 1;
    const PUT_NOTNEEDED = 0;

    /**
     * @var Pool $stashPool Cache a JWT tokennek
     */
    private $stashPool;

    /**
     * @var Client $client Guzzle Client az API kapcsolódáshoz
     */
    private $client;

    /**
     * @var string $username Felhasználónév
     */
    private $username;

    /**
     * @var string $password Jelszó
     */
    private $password;

    /**
     * @var array $photoSortQueue rendezendő kepek
     */
    private $photoSortQueue = [];

    /**
     * @var array $photoPutQueue feltoltendő képek
     */
    private $photoPutQueue = [];

    /**
     * @param string $apiUrl ingatlan.com API url
     * @param Pool|null $stashPool Stash példány az authentikációs token tárolására
     * @param ClientFactoryService|null $clientFactoryService Guzzle kliens factory
     */
    public function __construct(
        string $apiUrl,
        Pool $stashPool = null,
        ClientFactoryService $clientFactoryService = null
    ) {
        $this->stashPool = $stashPool ?? new Pool(new Ephemeral());
        $clientFactoryService = $clientFactoryService ?? new ClientFactoryService();
        $this->client = $clientFactoryService->getClient($apiUrl);
    }

    /**
     * Bejelentkezés
     *
     * @param string $username
     * @param string $password
     * @throws NotAuthenticatedException
     * @throws JWTTokenException
     */
    public function login(string $username, string $password): void
    {
        $this->username = $username;
        $this->password = $password;
        $this->getToken();
    }

    /**
     * Authentikációs token lekérése a cache-ből, vagy a szervertől.
     *
     * @return string JWT token
     * @throws NotAuthenticatedException
     * @throws JWTTokenException
     */
    private function getToken(): string
    {
        $item = $this->stashPool->getItem($this->getTokenCacheKey());
        if (!$item->isMiss()) {
            return $item->get();
        }

        $token = $this->callLogin();

        // calculate token lifetime
        $ttl = $this->getTokenTTL($token);

        /** @var Item $item */
        $item = $this->stashPool->getItem($this->getTokenCacheKey());
        // cache token
        $item->set($token)->setTTL($ttl)->save();

        return $token;
    }

    /**
     * Visszaadja a token kulcsát a poolban
     *
     * @return string
     */
    private function getTokenCacheKey(): string
    {
        return $this->username . 'Token';
    }

    /**
     * Kiszedi az auth token érvényességét
     *
     * @param string $token
     * @return int
     * @throws JWTTokenException
     */
    private function getTokenTTL(string $token): int
    {
        $tokenArr = explode('.', $token);
        if (count($tokenArr) > 1) {
            $data = base64_decode($tokenArr[1]);
            $tokenData = json_decode($data);
            if ($tokenData) {
                return $tokenData->exp - $tokenData->iat - $tokenData->clock_skew - 60;
            }
        }

        throw new JWTTokenException(sprintf("Invalid token: %s", $token));
    }

    /**
     * API bejelentkezés meghívása
     *
     * @return string
     * @throws InvalidJSendException
     * @throws NotAuthenticatedException
     * @throws ServerErrorException
     */
    private function callLogin(): string
    {
        try {
            $result = $this->sendRequest('POST', '/auth/login', json_encode(array(
                'username' => $this->username,
                'password' => $this->password
            )));
        } catch (JSendFailException $e) {
            throw new NotAuthenticatedException('Login failed', 0, $e);
        }

        return $result['token'];
    }


    /**
     * Hirdetés lekérése
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @return array hirdetés adatai
     * @throws InvalidJSendException
     * @throws JSendFailException
     * @throws ServerErrorException
     */
    public function getAd(string $adOwnId): array
    {
        $result = $this->sendRequest('GET', '/ads/' . $adOwnId);

        return $result['ad'];
    }

    /**
     * Hirdetés feladása/módosítása
     *
     * @param array $ad hirdetésadatok
     * @return array a feladott hirdetés adatai
     * @throws InvalidJSendException
     * @throws JSendFailException
     * @throws ServerErrorException
     */
    public function putAd(array $ad): array
    {
        $result = $this->sendRequest('PUT', '/ads/' . $ad['ownId'], json_encode($ad));

        return $result['ad'];
    }

    /**
     * Hirdetés törlése
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @return array törölt hirdetés adatai
     * @throws InvalidJSendException
     * @throws JSendFailException
     * @throws ServerErrorException
     */
    public function deleteAd(string $adOwnId): array
    {
        $result = $this->sendRequest('DELETE', '/ads/' . $adOwnId);

        return $result['ad'];
    }

    /**
     * Visszaadja az iroda összes hirdetésének azonosítóit (ingatlan.com-os és saját azonosítót)
     *
     * @return array hirdetés ID-k
     * @throws InvalidJSendException
     * @throws JSendFailException
     * @throws ServerErrorException
     */
    public function getAdIds(): array
    {
        $data = $this->sendRequest('GET', '/ads/ids');

        return $data['ids'];
    }


    /**
     * Hirdetés fotóinak lekérése
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @return array fotók adatai
     * @throws InvalidJSendException
     * @throws JSendFailException
     * @throws ServerErrorException
     */
    public function getPhotos(string $adOwnId)
    {
        $photos = $this->sendRequest('GET', '/ads/' . $adOwnId . '/photos');

        return $photos['photos'];
    }

    /**
     * Fotó feltöltés Request létrehozása
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @param array $photoData fotó adatok
     * @return RequestInterface
     * @throws InvalidValueException
     * @throws JWTTokenException
     * @throws NotAuthenticatedException
     */
    private function createPhotoPutRequest(string $adOwnId, array $photoData): RequestInterface
    {
        $photoOwnId = $photoData['ownId'];

        if (isset($photoData['labelId']) && !PhotoLabelEnum::validate($photoData['labelId'])) {
            throw new InvalidValueException(
                sprintf(
                    "A labelId értéke érvénytelen a %s fotónál: %s",
                    $photoOwnId,
                    $photoData['labelId']
                )
            );
        }
        if (isset($photoData['title']) && $photoData['title'] != '') {
            $encoding = mb_detect_encoding($photoData['title'], ['UTF-8', 'ISO-8859-2'], true);
            if ($encoding == 'ISO-8859-2') {
                $photoData['title'] = mb_convert_encoding($photoData['title'], 'UTF-8', 'ISO-8859-2');
            } elseif ($encoding === false) {
                throw new InvalidValueException(
                    sprintf(
                        "A title értéke nem UTF-8 karakterkódolással van megadva a %s fotónál: %s",
                        $photoOwnId,
                        $photoData['title']
                    )
                );
            }
        }

        unset($photoData['ownId'], $photoData['location']);
        if (isset($photoData['imageData'])) {
            $photoData['imageData'] = base64_encode($photoData['imageData']);
        }

        return $this->getRequest(
            'PUT',
            '/ads/' . $adOwnId . '/photos/' . $photoOwnId,
            json_encode($photoData)
        );
    }

    /**
     * Fotó feltöltése/módosítása
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @param array $photoData fotó adatok
     * @return array feltöltött fotó adatok
     * @throws GuzzleException
     * @throws InvalidJSendException
     * @throws InvalidValueException
     * @throws JSendFailException
     * @throws JWTTokenException
     * @throws NotAuthenticatedException
     * @throws ServerErrorException
     */
    public function putPhoto(string $adOwnId, array $photoData): array
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
     * @param array $photosByOwnId hirdetés képeinek adatai, saját azonosító szerint indexelve
     * @return array
     * @throws InvalidValueException
     * @throws JWTTokenException
     * @throws NotAuthenticatedException
     */
    public function putPhotosMulti(string $adOwnId, array $photosByOwnId): array
    {
        $requests = [];
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
     * @throws InvalidJSendException
     * @throws JSendFailException
     * @throws ServerErrorException
     */
    public function deletePhoto(string $adOwnId, string $photoId): bool
    {
        $this->sendRequest(
            'DELETE',
            '/ads/' . $adOwnId . '/photos/' . $photoId
        );

        return true;
    }

    /**
     * Hirdetés fotóinak törlése, több szálon
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @param array $photosByOwnId hirdetés képeinek adatai, saját azonosító szerint indexelve
     * @return array
     * @throws JWTTokenException
     * @throws NotAuthenticatedException
     */
    public function deletePhotosMulti(string $adOwnId, array $photosByOwnId): array
    {
        $requests = array();
        foreach (array_keys($photosByOwnId) as $photoOwnIdToDelete) {
            $requests[$photoOwnIdToDelete] = $this->getRequest(
                'DELETE',
                '/ads/' . $adOwnId . '/photos/' . $photoOwnIdToDelete
            );
        }

        return $this->sendMultiRequest($requests);
    }

    /**
     * Iroda ingatlan.com-on lévő hirdetéseit szinkronba hozza az iroda saját rendszerében lévő hirdetésekkel
     * (kitörli az ingatlan.com-ról azokat a hirdetéseket, amik már nincsenek meg az iroda saját rendszerében)
     *
     * @param array $adIds az iroda saját rendszerében lévő összes hirdetés ID-ja
     * @return array a törölt hirdetések ID-i
     * @throws Exception
     */
    public function syncAds(array $adIds): array
    {
        try {
            $ids = $this->getAdIds();
        } catch (Exception $e) {
            throw new Exception('A hirdetés ID-k lekérése nem sikerült', 0, $e);
        }

        $ownIds = array_reduce(
            $ids,
            function ($carry, $item) {
                if ($item['ownId'] && 0 == $item['statusId']) {
                    $carry[] = $item['ownId'];
                }
                return $carry;
            },
            []
        );

        $idsToDelete = array_diff($ownIds, $adIds);
        foreach ($idsToDelete as $id) {
            $this->deleteAd($id);
        }

        return $idsToDelete;
    }

    /**
     * Hirdetés képeinek sorrendezése
     *
     * @param string $adOwnId hirdetés saját azonosítója
     * @param array $photoOwnIds fotók saját azonosítója, kívánt sorrendben
     * @return array hirdetés fotói
     * @throws InvalidJSendException
     * @throws JSendFailException
     * @throws ServerErrorException
     */
    public function putPhotoOrder(string $adOwnId, array $photoOwnIds): array
    {
        $photos = $this->sendRequest(
            'PUT',
            '/ads/' . $adOwnId . '/photoOrder',
            json_encode(['order' => $photoOwnIds])
        );

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
     * @throws JWTTokenException
     */
    private function getRequest(string $method, string $endpoint, string $body = null): RequestInterface
    {
        $headers['Accept'] = 'application/json';

        if ($body) {
            $headers['Content-type'] = 'application/json';
        }

        if ('/auth/login' != $endpoint) {
            $headers['Authorization'] = 'Bearer ' . $this->getToken();
        }

        return new Request($method, '/v' . self::APIVERSION . $endpoint, $headers, $body);
    }

    /**
     * Request legyártás, elküldés, válasz feldolgozás
     *
     * @param string $method HTTP method
     * @param string $endpoint path
     * @param string|null $body content
     * @return array
     * @throws GuzzleException
     * @throws InvalidJSendException
     * @throws JSendFailException
     * @throws JWTTokenException
     * @throws NotAuthenticatedException
     * @throws ServerErrorException
     */
    private function sendRequest(string $method, string $endpoint, string $body = null): array
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
    private function sendMultiRequest(array $requests): array
    {
        $results = [];

        if (count($requests)) {
            $promises = [];
            foreach ($requests as $photoOwnId => $request) {
                $promises[$photoOwnId] = $this->client->sendAsync($request);
            }

            Promise\Each::ofLimit(
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
     * @param ResponseInterface $response
     * @return ?array
     * @throws InvalidJSendException
     * @throws ServerErrorException
     * @throws JSendFailException
     */
    public function parseResponse(ResponseInterface $response): ?array
    {
        try {
            $jsendResponse = JSendResponse::decode((string) $response->getBody());
        } catch (UnexpectedValueException $exception) {
            $match = preg_match("/<title>\\d+ .*<\\/title>/", (string) $response->getBody(), $message);
            $jsendResponse = JSendResponse::error(
                $match ? strip_tags($message[0]) : "server connection error",
                $match ? $response->getStatusCode() : 503
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

    /**
     * A teljes szinkronizálási folyamat
     *
     * @param string $adOwnId hirdetés saját azonosító
     * @param array $photos iroda rendszerében levő fotók adatai
     * @param bool $forceImageDataUpdate akkor is töltsük le a fotót az iroda rendszeréből, ha már fel van töltve adott azonosítóval
     * @param array|null $uploadedPhotos ingatlan.com rendszerében levő fotók adatai
     * @param bool $paralellDownload párhuzamos fotóletöltés az iroda szerveréről
     * @return PhotoSyncResult
     * @throws InvalidJSendException
     * @throws InvalidValueException
     * @throws JSendFailException
     * @throws JWTTokenException
     * @throws NotAuthenticatedException
     * @throws ServerErrorException
     */
    public function syncPhotos(
        string $adOwnId,
        array $photos,
        bool $forceImageDataUpdate = false,
        array $uploadedPhotos = null,
        bool $paralellDownload = false
    ): PhotoSyncResult {
        $this->photoPutQueue = [];
        $this->photoSortQueue = [];
        $errors = [];

        if (null === $uploadedPhotos) {
            $uploadedPhotos = $this->getPhotos($adOwnId);
        }

        $localPhotosByOwnId = $this->mapArrayByField($photos, 'ownId');
        $uploadedPhotosByOwnId = $this->mapArrayByField($uploadedPhotos, 'ownId');

        //delete
        $photosToDelete = array_diff_key($uploadedPhotosByOwnId, $localPhotosByOwnId);
        $deleteResults = $this->deletePhotosMulti($adOwnId, $photosToDelete);
        $errors['photoDelete'] = $this->parseMultiTransferErrors($deleteResults, $photosToDelete);

        //fetch image data, diff with uploaded
        $errors['photoFetch'] = $this->buildPhotoQueues($localPhotosByOwnId, $uploadedPhotosByOwnId, $forceImageDataUpdate, $paralellDownload);

        //put
        $putResults = $this->putPhotosMulti($adOwnId, $this->photoPutQueue);
        $errors['photoPut'] = $this->parseMultiTransferErrors($putResults, $this->photoPutQueue);

        //fix order
        $photos = $this->syncPhotosPutOrder(
            $adOwnId,
            array_merge(
                array_diff_key(
                    $this->photoSortQueue,
                    $errors['photoPut']
                ),
                $errors['photoDelete']
            )
        );

        return new PhotoSyncResult($photos, $errors);
    }

    /**
     * Tömbből asszociatív tömböt készít valamely mező alapján
     *
     * @param array  $array tömb
     * @param string $field mező
     * @return array
     */
    private function mapArrayByField(array $array, string $field): array
    {
        $result = [];
        foreach ($array as $el) {
            //TODO duplicate key-re exception
            $result[$el[$field]] = $el;
        }

        return $result;
    }

    /**
     * fotó feltöltési és rendezési sorok létrehozása
     *
     * @param array $localPhotosByOwnId A feltölteni kívánt fotók tömbje
     * @param array $uploadedPhotosByOwnId Az ingatlan.com szerverére feltöltött fotók tömbje
     * @param bool $forceImageDataUpdate akkor is töltsük le a fotót az iroda rendszeréből, ha már fel van töltve adott azonosítóval
     * @param bool $paralellDownload párhuzamos fotóletöltés az iroda szerveréről
     * @return array
     */
    private function buildPhotoQueues(
        array $localPhotosByOwnId,
        array $uploadedPhotosByOwnId,
        bool $forceImageDataUpdate,
        bool $paralellDownload
    ): array {
        $downloadQueue = [];

        foreach ($localPhotosByOwnId as $ownId => $photoData) {
            //ha feltoltendo, mert nincs feltolve sajatid alapjan, vagy update szukseges
            if (!array_key_exists($ownId, $uploadedPhotosByOwnId) || $forceImageDataUpdate) {
                $downloadQueue[$ownId] = $photoData;
            } else {
                if ($this->arePhotosDifferent($uploadedPhotosByOwnId[$ownId], $photoData)) {
                    $this->photoPutQueue[$ownId] = $photoData;
                }
                $this->photoSortQueue[$ownId] = $photoData;
            }
        }

        return $this->downloadPhotosToQueues($localPhotosByOwnId, $uploadedPhotosByOwnId, $downloadQueue, $paralellDownload);
    }

    /**
     * Fotók letöltése az iroda szerveréről, ellenőrzés, hogy szükséges-e a betöltés
     * az ingatlan.com rendszerébe
     *
     * @param array $localPhotosByOwnId A feltölteni kívánt fotók tömbje
     * @param array $uploadedPhotosByOwnId Az ingatlan.com szerverére feltöltött fotók tömbje
     * @param array $photosByOwnId hirdetés képeinek adatai, saját azonosító szerint indexelve
     * @param bool $paralellDownload párhuzamos fotóletöltés az iroda szerveréről
     * @return array
     */
    private function downloadPhotosToQueues(
        array $localPhotosByOwnId,
        array $uploadedPhotosByOwnId,
        array $photosByOwnId,
        bool $paralellDownload
    ): array {
        $photoResizeService = new PhotoResizeService();
        $photoFetchErrors = [];
        $imageDatas = $photoResizeService->getResizedPhotosData($photosByOwnId, $paralellDownload);

        foreach ($imageDatas as $ownId => $imageData) {
            $photoData = $localPhotosByOwnId[$ownId];
            if ($imageData instanceof Exception) {
                $photoData['exception'] = $imageData;
                $photoData['errorMessage'] = $imageData->getMessage();
                $photoFetchErrors[$ownId] = $photoData;
            } else {
                //md5 check, full upload
                $needToPutPhoto = $this->needToPutPhoto($uploadedPhotosByOwnId, $photoData, $imageData);
                if ($needToPutPhoto != self::PUT_NOTNEEDED) {
                    if (self::PUT_WITH_IMAGE_DATA == $needToPutPhoto) {
                        $photoData['imageData'] = $imageData;
                    }
                    $this->photoPutQueue[$ownId] = $photoData;
                }

                $this->photoSortQueue[$ownId] = $photoData;
            }
        }

        return $photoFetchErrors;
    }

    /**
     * Feltöltés szükségességének ellenőrzése
     * MD5 hash és képadatok alapján
     *
     * @param array $uploadedPhotosByOwnId
     * @param array $photoData fotó adatok
     * @param string $imageData fotó bináris formátumban
     * @return int feltöltés típusa
     */
    private function needToPutPhoto(array $uploadedPhotosByOwnId, array $photoData, string $imageData): int
    {
        $ownId = $photoData['ownId'];

        //ha nincs feltoltve, vagy md5 nem egyezik
        if (!array_key_exists($ownId, $uploadedPhotosByOwnId)
            || md5($imageData) != $uploadedPhotosByOwnId[$ownId]['md5Hash']
        ) {
            return self::PUT_WITH_IMAGE_DATA;
        } elseif (array_key_exists($ownId, $uploadedPhotosByOwnId)) {
            if ($this->arePhotosDifferent($uploadedPhotosByOwnId[$ownId], $photoData)) {
                return self::PUT_WITHOUT_IMAGE_DATA;
            }
        }

        return self::PUT_NOTNEEDED;
    }

    /**
     * Fotó adatok különbségének vizsgálata
     *
     * @param array $photo1 fotó adatok
     * @param array $photo2 fotó adatok
     * @return bool
     */
    private function arePhotosDifferent(array $photo1, array $photo2): bool
    {
        isset($photo1['labelId']) ?: $photo1['labelId'] = null;
        isset($photo2['labelId']) ?: $photo2['labelId'] = null;
        isset($photo1['title']) ?: $photo1['title'] = null;
        isset($photo2['title']) ?: $photo2['title'] = null;

        return $photo1['title'] != $photo2['title'] || $photo1['labelId'] != $photo2['labelId'];
    }

    /**
     * Megnézi, hogy a párhuzamos kérések között volt-e, ami sikertelen
     *
     * @param array $results A requestek eredményei
     * @param array $photosByOwnId
     * @return array
     * @throws InvalidJSendException
     */
    private function parseMultiTransferErrors(array $results, array $photosByOwnId): array
    {
        $errors = [];
        foreach ($results as $index => $result) {
            if ($result['state'] == PromiseInterface::REJECTED && $result['value'] instanceof Exception) {
                $exception = $result['value'];

                $errorPhoto = $photosByOwnId[$index];
                $errorPhoto['exception'] = $exception;
                if ($exception instanceof RequestException) {
                    try {
                        if ($exception->getResponse()) {
                            $error = $this->parseResponse($exception->getResponse());
                        } else {
                            $error = $exception->getMessage();
                        }
                    } catch (ServerErrorException $jse) {
                        $error = 'Server error: ' . $jse->getJSendResponse()->getErrorMessage();
                    } catch (JSendFailException $jse) {
                        $error = $jse->getJSendResponse()->getData();
                        $error = $error['message'] ?? $error;
                    } catch (UnexpectedValueException $uve) {
                        $error = "JSON decode error";
                    }
                    $errorPhoto['errorMessage'] = $error;
                } else {
                    $errorPhoto['errorMessage'] = $exception->getMessage();
                }
                $errors[$errorPhoto['ownId']] = $errorPhoto;
            }
        }

        return $errors;
    }

    /**
     * Fotók sorrdendezése
     *
     * @param string $adOwnId hirdetés saját azonosító
     * @param array $photosByOwnId hirdetés képeinek adatai, saját azonosító szerint indexelve
     * @return array hirdetés fotói
     * @throws InvalidJSendException
     * @throws JSendFailException
     * @throws ServerErrorException
     */
    private function syncPhotosPutOrder(string $adOwnId, array $photosByOwnId): array
    {
        isset($a['order']) ?: $a['order'] = null;
        isset($b['order']) ?: $b['order'] = null;
        usort($photosByOwnId, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        $order = array_map(function ($photo) {
            return $photo['ownId'];
        }, $photosByOwnId);

        return $this->putPhotoOrder($adOwnId, $order);
    }

    /**
     * @return bool
     * @throws GuzzleException
     * @throws InvalidJSendException
     * @throws JSendFailException
     */
    public function checkApiStatus(): bool
    {
        $headers = ['Accept' => 'application/json'];
        $request = new Request('GET', "/status/", $headers);

        try {
            $response = $this->client->send($request);
            $this->parseResponse($response);
        } catch (GuzzleException | ServerErrorException | TransferException $e) {
            return false;
        }

        return true;
    }
}
