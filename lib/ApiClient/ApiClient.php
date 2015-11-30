<?php
/**
 * Created by PhpStorm.
 * User: zooli
 * Date: 2015.10.26.
 * Time: 16:27
 */

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
     * @var Client
     */
    private $client;

    /**
     * ApiClient constructor.
     *
     * @param string $apiUrl
     * @param Pool $stashPool
     * @param ClientFactoryService $clientFactoryService
     */
    public function __construct($apiUrl, Pool $stashPool = null, ClientFactoryService $clientFactoryService = null)
    {
        $this->stashPool = $stashPool;
        $clientFactoryService = null != $clientFactoryService ? $clientFactoryService : new ClientFactoryService();
        $this->client = $clientFactoryService->getClient($apiUrl);
    }

    public function login($username, $password)
    {
        if (null == $this->token) {
            $this->token = $this->getToken($username, $password);
        }
    }

    /**
     * @param string $username
     * @param string $password
     * @return string
     * @throws \Exception
     */
    private function getToken($username, $password)
    {
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
     * @param string $username
     * @param string $password
     * @return string
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
     * @param array $ad
     * @return array
     */
    public function putAd(array $ad)
    {
        $result = $this->sendRequest(RequestInterface::PUT, '/ads/' . $ad['ownId'], json_encode($ad));
        return $result;
    }

    /**
     * @param $adOwnId
     * @return array
     */
    public function getAd($adOwnId)
    {
        $result = $this->sendRequest(RequestInterface::GET, '/ads/' . $adOwnId);

        return $result;
    }

    /**
     * @param $adOwnId
     * @return array
     */
    public function deleteAd($adOwnId)
    {
        $result = $this->sendRequest(RequestInterface::DELETE, '/ads/' . $adOwnId);
        return $result;
    }

    /**
     * visszaadja az iroda összes hirdetésének ID-it
     *
     * @return JSendResponse
     */
    public function getAdIds()
    {
        $ids = $this->sendRequest(RequestInterface::GET, '/ads/ids');

        return $ids;
    }

    /**
     * iroda ingatlan.com-on lévő hirdetéseit szinkronba hozza az iroda saját rendszerében lévő hirdetésekkel
     * (kitölri az ingatlan.com-ról azokat a hirdetéseket, amik már nincsenek meg az iroda saját rendszerében)
     *
     * @param array $adIds az iroda saját rendszerében lévő összes hirdetés ID-ja
     * @return array a törölt hirdetések ID-i
     * @throws \Exception
     */
    public function syncAds(array $adIds)
    {
        try {
            $data = $this->getAdIds();
        }catch (\Exception $e) {
            throw new \Exception('A hirdetés ID-k lekérése nem sikerült', 0, $e);
        }

        $ownIds = array_reduce(
            $data['ids'],
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
     * @param $adOwnId
     * @param array $photos
     * @param bool $forceImageDataUpdate
     * @param array|null $uploadedPhotos
     * @return PhotoSync
     */
    public function syncPhotos($adOwnId, array $photos, $forceImageDataUpdate = false, array $uploadedPhotos = null)
    {
        $service = new PhotoSync($this);
        return $service->syncPhotos($adOwnId, $photos, $forceImageDataUpdate, $uploadedPhotos);
    }

    /**
     * @param string $adOwnId
     * @param array $photoData
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
     * @param string $adOwnId
     * @param array $photoData
     * @return JSendResponse
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

        return $response;
    }

    /**
     * @param string $adOwnId
     * @param string $photoId
     * @return array
     */
    public function deletePhoto($adOwnId, $photoId)
    {
        $result = $this->sendRequest(RequestInterface::DELETE, '/ads/' . $adOwnId . '/photos/' . $photoId);
        return $result;
    }

    /**
     * @param string $adOwnId
     * @return array
     */
    public function getPhotos($adOwnId)
    {
        $photos = $this->sendRequest(RequestInterface::GET, '/ads/' . $adOwnId . '/photos');
        return $photos['photos'];
    }

    /**
     * @param string $adOwnId
     * @param array $photoOwnIds
     * @return array
     */
    public function putPhotoOrder($adOwnId, $photoOwnIds)
    {
        $photos = $this->sendRequest(RequestInterface::PUT, '/ads/' . $adOwnId . '/photoOrder', json_encode(array('order' => $photoOwnIds)));
        return $photos['photos'];
    }

    /**
     * @param string $adOwnId
     * @param array $photosByOwnId
     * @return array
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
     * @param string $adOwnId
     * @param array $photosByOwnId
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
     * @param string $method
     * @param string $endpoint
     * @param string|null $body
     * @return RequestInterface
     * @throws \Exception
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
     * @param string $method
     * @param string $endpoint
     * @param string|null $body
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
