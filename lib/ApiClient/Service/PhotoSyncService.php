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

    public function __construct(ApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
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
        $photoHandler = new PhotoResizeService();

        if (null === $uploadedPhotos) {
            $uploadedPhotos = $this->apiClient->getPhotos($adOwnId);
        }

        $localPhotosByOwnId = $this->mapArrayByField($photos, 'ownId');
        $uploadedPhotosByOwnId = $this->mapArrayByField($uploadedPhotos, 'ownId');

        $photosToDelete = array_diff_key($uploadedPhotosByOwnId, $localPhotosByOwnId);

        $deleteErrors = array();
        try {
            $this->apiClient->deletePhotosMulti($adOwnId, $photosToDelete);
        } catch (MultiTransferException $e) {
            $deleteErrors = $this->parseMultiTransferException($e, $photosToDelete);
        }

        $getImageErrors = array();
        //feltoltendo kepek
        $photoQueue = array();
        //rendezendo kepek
        $photoSortQueue = array();

        foreach ($localPhotosByOwnId as $ownId => $photoData) {
            //ha feltoltendo, mert nincs feltolve sajatid alapjan, vagy update szukseges
            if (!array_key_exists($ownId, $uploadedPhotosByOwnId) || $forceImageDataUpdate) {
                //md5 check, full upload
                try {
                    $imageData = $photoHandler->getResizedPhotoData($photoData['location']);

                    //ha fel kell tolteni, vagy md5 nem egyezik
                    if (!array_key_exists($ownId, $uploadedPhotosByOwnId)
                        || md5($imageData) != $uploadedPhotosByOwnId[$ownId]['md5Hash']
                    ) {
                        $photoData['imageData'] = $imageData;
                        unset($photoData['location']);

                        $photoQueue[$photoData['ownId']] = $photoData;
                    } elseif (array_key_exists($ownId, $uploadedPhotosByOwnId)) {
                        if (
                            $uploadedPhotosByOwnId[$ownId]['title'] != $photoData['title']
                            || $uploadedPhotosByOwnId[$ownId]['labelId'] != $photoData['labelId']
                        ) {
                            $photoQueue[$photoData['ownId']] = $photoData;
                        }
                    }

                    $photoSortQueue[$photoData['ownId']] = $photoData;
                } catch (\Exception $e) {
                    $photoData['exception'] = $e;
                    $photoData['errorMessage'] = $e->getMessage();
                    $getImageErrors[$photoData['ownId']] = $photoData;
                }
            } else {
                if (
                    $uploadedPhotosByOwnId[$ownId]['title'] != $photoData['title']
                    || $uploadedPhotosByOwnId[$ownId]['labelId'] != $photoData['labelId']
                ) {
                    $photoQueue[$photoData['ownId']] = $photoData;
                }
                $photoSortQueue[$photoData['ownId']] = $photoData;
            }
        }

        $putErrors = array();
        try {
            $this->apiClient->putPhotosMulti($adOwnId, $photoQueue);
        } catch (MultiTransferException $e) {
            $putErrors = $this->parseMultiTransferException($e, $photoQueue);
        }

        $photos = $this->syncPhotosPutOrder($adOwnId, array_diff_key($photoSortQueue, $putErrors));

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
