<?php
/**
 * Created by PhpStorm.
 * User: zooli
 * Date: 2015.11.19.
 * Time: 11:37
 */

namespace IngatlanCom\ApiClient;

use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Exception\MultiTransferException;
use IngatlanCom\ApiClient\Exception\ServerErrorException;
use IngatlanCom\ApiClient\Exception\JSendFailException;
use IngatlanCom\ApiClient\Service\PhotoResizeService;

class PhotoSync
{
    const PUT_WITH_IMAGE_DATA = 2;
    const PUT_WITHOUT_IMAGE_DATA = 1;
    const PUT_NOTNEEDED = 0;

    /**
     * @var ApiClient
     */
    private $apiClient;

    /**
     * @var PhotoResizeService
     */
    private $photoResizeService;

    /**
     * @var array
     */
    private $localPhotosByOwnId;

    /**
     * @var array
     */
    private $uploadedPhotosByOwnId;

    /**
     * @var array rendezendo kepek
     */
    private $photoSortQueue = array();

    /**
     * @var array feltoltendo kepek
     */
    private $photoPutQueue = array();

    /**
     * Kép file kezelés hibák
     * @var array
     */
    private $fetchPhotoErrors = array();

    /**
     * Kép törlés API hibák
     * @var array
     */
    private $deletePhotoErrors = array();

    /**
     * Kép feltöltés API hibák
     * @var array
     */
    private $putPhotoErrors = array();

    /**
     * Képek
     * @var array
     */
    private $photos;

    /**
     * PhotoSyncService constructor.
     *
     * @param ApiClient $apiClient
     */
    public function __construct(ApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
        $this->photoResizeService = new PhotoResizeService();
    }

    /**
     * $photos tomb elemei:
     * ownId
     * title
     * order
     * labelId
     * location
     *
     * @param string $adOwnId
     * @param array $photos
     * @param bool $forceImageDataUpdate
     * @param array|null $uploadedPhotos
     * @param bool $paralellDownload
     * @return PhotoSync
     * @throws MultiTransferException
     */
    public function syncPhotos($adOwnId, array $photos, $forceImageDataUpdate = false, array $uploadedPhotos = null, $paralellDownload = false)
    {
        if (null === $uploadedPhotos) {
            $uploadedPhotos = $this->apiClient->getPhotos($adOwnId);
        }

        $this->localPhotosByOwnId = $this->mapArrayByField($photos, 'ownId');
        $this->uploadedPhotosByOwnId = $this->mapArrayByField($uploadedPhotos, 'ownId');

        //delete
        $photosToDelete = array_diff_key($this->uploadedPhotosByOwnId, $this->localPhotosByOwnId);
        try {
            $this->apiClient->deletePhotosMulti($adOwnId, $photosToDelete);
        } catch (MultiTransferException $e) {
            $this->deletePhotoErrors = $this->parseMultiTransferException($e, $photosToDelete);
        }

        //fetch image data, diff with uploaded
        $this->buildPhotoQueues($forceImageDataUpdate, $paralellDownload);

        //put
        try {
            $this->apiClient->putPhotosMulti($adOwnId, $this->photoPutQueue);
        } catch (MultiTransferException $e) {
            $this->putPhotoErrors = $this->parseMultiTransferException($e, $this->photoPutQueue);
        }

        //fix order
        $this->photos = $this->syncPhotosPutOrder($adOwnId, array_merge(array_diff_key($this->photoSortQueue, $this->putPhotoErrors), $this->deletePhotoErrors));

        return $this;
    }

    /**
     * @param array $array
     * @param string $field
     * @return array
     */
    private function mapArrayByField(array $array, $field)
    {
        $result = array();
        foreach ($array as $el) {
            //TODO duplicate key-re exception
            $result[$el[$field]] = $el;
        }
        return $result;
    }

    /**
     * @param bool $forceImageDataUpdate
     * @param bool $paralellDownload
     * @return array errors
     */
    private function buildPhotoQueues($forceImageDataUpdate, $paralellDownload)
    {
        $downloadQueue = array();

        foreach ($this->localPhotosByOwnId as $ownId => $photoData) {
            //ha feltoltendo, mert nincs feltolve sajatid alapjan, vagy update szukseges
            if (!array_key_exists($ownId, $this->uploadedPhotosByOwnId) || $forceImageDataUpdate) {
                $downloadQueue[$ownId] = $photoData;
            } else {
                if ($this->arePhotosDifferent($this->uploadedPhotosByOwnId[$ownId], $photoData)) {
                    $this->photoPutQueue[$ownId] = $photoData;
                }
                $this->photoSortQueue[$ownId] = $photoData;
            }
        }

        $this->downloadPhotosToQueues($downloadQueue, $paralellDownload);
    }

    /**
     * @param array $photos
     * @param bool $paralellDownload
     */
    private function downloadPhotosToQueues(array $photos, $paralellDownload)
    {
        $imageDatas = $this->photoResizeService->getResizedPhotosData($photos, $paralellDownload);

        foreach ($imageDatas as $ownId => $imageData) {
            $photoData = $this->localPhotosByOwnId[$ownId];
            if ($imageData instanceof \Exception) {
                $photoData['exception'] = $imageData;
                $photoData['errorMessage'] = $imageData->getMessage();
                $this->fetchPhotoErrors[$ownId] = $photoData;
            } else {
                //md5 check, full upload
                $needToPutPhoto = $this->needToPutPhoto($photoData, $imageData);
                if ($needToPutPhoto != self::PUT_NOTNEEDED) {
                    if (self::PUT_WITH_IMAGE_DATA == $needToPutPhoto) {
                        $photoData['imageData'] = $imageData;
                    }
                    $this->photoPutQueue[$ownId] = $photoData;
                }

                $this->photoSortQueue[$ownId] = $photoData;
            }
        }
    }

    /**
     * @param array $photoData
     * @param string $imageData
     * @return int
     */
    private function needToPutPhoto($photoData, $imageData)
    {
        $ownId = $photoData['ownId'];

        //ha nincs feltoltve, vagy md5 nem egyezik
        if (!array_key_exists($ownId, $this->uploadedPhotosByOwnId)
            || md5($imageData) != $this->uploadedPhotosByOwnId[$ownId]['md5Hash']
        ) {
            return self::PUT_WITH_IMAGE_DATA;
        } elseif (array_key_exists($ownId, $this->uploadedPhotosByOwnId)) {
            if ($this->arePhotosDifferent($this->uploadedPhotosByOwnId[$ownId], $photoData)) {
                return self::PUT_WITHOUT_IMAGE_DATA;
            }
        }

        return self::PUT_NOTNEEDED;
    }

    /**
     * @param array $photo1
     * @param array $photo2
     * @return bool
     */
    private function arePhotosDifferent(array $photo1, array $photo2)
    {
        if (
            $photo1['title'] != $photo2['title'] ||
            $photo1['labelId'] != $photo2['labelId']
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param MultiTransferException $es
     * @param array $photosByOwnId
     * @return array
     * @throws MultiTransferException
     */
    private function parseMultiTransferException(MultiTransferException $es, array $photosByOwnId)
    {
        $errors = array();
        foreach ($es as $e) {
            if ($e instanceof BadResponseException) {
                try {
                    $error = $this->apiClient->parseResponse($e->getResponse());
                } catch (ServerErrorException $jse) {
                    $error = 'Server error: ' . $jse->getJSendResponse()->getErrorMessage();
                } catch (JSendFailException $jse) {
                    $error = $jse->getJSendResponse()->getData();
                } catch (\UnexpectedValueException $uve) {
                    $error = "JSON decode error";
                }

                $urlParts = explode('/photos/', $e->getRequest()->getUrl());
                $ownId = end($urlParts);

                $errorPhoto = $photosByOwnId[$ownId];
                $errorPhoto['exception'] = $e;
                $errorPhoto['errorMessage'] = $error;
                $errors[$errorPhoto['ownId']] = $errorPhoto;
            } else {
                //valami nagyon szornyu tortent, kezdjen vele valamit valaki
                throw $es;
            }
        }
        return $errors;
    }


    /**
     * @param string $adOwnId
     * @param array $photosByOwnId
     * @return array
     */
    private function syncPhotosPutOrder($adOwnId, array $photosByOwnId)
    {
        usort($photosByOwnId, function ($a, $b) {
            if ($a['order'] == $b['order']) {
                return 0;
            }

            return $a['order'] < $b['order'] ? -1 : 1;
        });

        $order = array_map(function ($photo) {
            return $photo['ownId'];
        }, $photosByOwnId);

        return $this->apiClient->putPhotoOrder($adOwnId, $order);
    }

    /**
     * @return array
     */
    public function getFetchPhotoErrors()
    {
        return $this->fetchPhotoErrors;
    }

    /**
     * @return array
     */
    public function getDeletePhotoErrors()
    {
        return $this->deletePhotoErrors;
    }

    /**
     * @return array
     */
    public function getPutPhotoErrors()
    {
        return $this->putPhotoErrors;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return array_merge($this->deletePhotoErrors, $this->fetchPhotoErrors, $this->putPhotoErrors);
    }

    /**
     * @return array
     */
    public function getPhotos()
    {
        return $this->photos;
    }
}
