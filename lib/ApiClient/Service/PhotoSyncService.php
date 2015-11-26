<?php
/**
 * Created by PhpStorm.
 * User: zooli
 * Date: 2015.11.19.
 * Time: 11:37
 */

namespace IngatlanCom\ApiClient\Service;

use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Exception\MultiTransferException;
use IngatlanCom\ApiClient\ApiClient;
use IngatlanCom\ApiClient\Exception\JSendErrorException;
use IngatlanCom\ApiClient\Exception\JSendFailException;

class PhotoSyncService
{
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
    private $photoSortQueue;

    /**
     * @var array feltoltendo kepek
     */
    private $photoPutQueue;

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
     * @return array
     */
    public function syncPhotos($adOwnId, array $photos, $forceImageDataUpdate = false, array $uploadedPhotos = null)
    {
        if (null === $uploadedPhotos) {
            $uploadedPhotos = $this->apiClient->getPhotos($adOwnId);
        }

        $this->localPhotosByOwnId = $this->mapArrayByField($photos, 'ownId');
        $this->uploadedPhotosByOwnId = $this->mapArrayByField($uploadedPhotos, 'ownId');

        //delete
        $photosToDelete = array_diff_key($this->uploadedPhotosByOwnId, $this->localPhotosByOwnId);
        $deleteErrors = array();
        try {
            $this->apiClient->deletePhotosMulti($adOwnId, $photosToDelete);
        } catch (MultiTransferException $e) {
            $deleteErrors = $this->parseMultiTransferException($e, $photosToDelete);
            //TODO: ha delete error van, akkor a sort queue-be is bele kell tenni, vagy el kell szallni!
        }

        //fetch image data, diff with uploaded
        $getImageErrors = $this->buildPhotoQueues($forceImageDataUpdate);

        //put
        $putErrors = array();
        try {
            $this->apiClient->putPhotosMulti($adOwnId, $this->photoPutQueue);
        } catch (MultiTransferException $e) {
            $putErrors = $this->parseMultiTransferException($e, $this->photoPutQueue);
        }

        //fix order
        $photos = $this->syncPhotosPutOrder($adOwnId, array_diff_key($this->photoSortQueue, $putErrors));

        return array(
            'photos' => $photos,
            'errors' => array_merge($deleteErrors, $getImageErrors, $putErrors)
        );
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
     * @return array errors
     */
    private function buildPhotoQueues($forceImageDataUpdate)
    {
        $getImageErrors = array();

        foreach ($this->localPhotosByOwnId as $ownId => $photoData) {
            //ha feltoltendo, mert nincs feltolve sajatid alapjan, vagy update szukseges
            if (!array_key_exists($ownId, $this->uploadedPhotosByOwnId) || $forceImageDataUpdate) {
                //md5 check, full upload
                try {
                    $imageData = $this->photoResizeService->getResizedPhotoData($photoData['location']);
                    if ($this->needToPutPhoto($photoData, $imageData)) {
                        $photoData['imageData'] = $imageData;
                        $this->photoPutQueue[$ownId] = $photoData;
                    }

                    $this->photoSortQueue[$ownId] = $photoData;
                } catch (\Exception $e) {
                    $photoData['exception'] = $e;
                    $photoData['errorMessage'] = $e->getMessage();
                    $getImageErrors[$ownId] = $photoData;
                }
            } else {
                if ($this->arePhotosDifferent($this->uploadedPhotosByOwnId[$ownId], $photoData)) {
                    $this->photoPutQueue[$ownId] = $photoData;
                }
                $this->photoSortQueue[$ownId] = $photoData;
            }
        }

        return $getImageErrors;
    }

    /**
     * @param array $photoData
     * @param string $imageData
     * @return bool
     */
    private function needToPutPhoto($photoData, $imageData)
    {
        $ownId = $photoData['ownId'];

        //ha nincs feltoltve, vagy md5 nem egyezik
        if (!array_key_exists($ownId, $this->uploadedPhotosByOwnId)
            || md5($imageData) != $this->uploadedPhotosByOwnId[$ownId]['md5Hash']
        ) {
            return true;
        } elseif (array_key_exists($ownId, $this->uploadedPhotosByOwnId)) {
            if ($this->arePhotosDifferent($this->uploadedPhotosByOwnId[$ownId], $photoData)) {
                return true;
            }
        }

        return false;
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

    private function parseMultiTransferException(MultiTransferException $es, array $photosByOwnId)
    {
        $errors = array();
        foreach ($es as $e) {
            if ($e instanceof BadResponseException) {
                try {
                    $error = $this->apiClient->parseResponse($e->getResponse());
                } catch (JSendErrorException $jse) {
                    $error = $jse->getJSendResponse()->getErrorMessage();
                } catch (JSendFailException $jse) {
                    $error = $jse->getJSendResponse()->getData();
                } catch (\UnexpectedValueException $uve) {
                    $error = $e->getResponse()->getBody(true);
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
}
