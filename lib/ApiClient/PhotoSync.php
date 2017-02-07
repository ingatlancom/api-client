<?php
namespace IngatlanCom\ApiClient;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Promise\PromiseInterface;
use IngatlanCom\ApiClient\Exception\JSendFailException;
use IngatlanCom\ApiClient\Exception\ServerErrorException;
use IngatlanCom\ApiClient\Service\PhotoResizeService;

/**
 * Class PhotoSync
 *
 * hirdetés fotóit szinkronba hozza az iroda saját rendszerében lévőkkel
 * feltölti, ami még nincs meg az ingatlan.com rendszerében
 * kitörli, ami nincs meg már az irodánál
 * rendberakja a sorrendet
 *
 * A képadatok struktúrája
 *  ownId (string) kép egyedi azonosítója az iroda rendszerében
 *  title (string) kép felirat
 *  order (int) kép sorrend
 *  labelId (int) képfelirat azonosító
 *  location (string) képfájl elérési útja, http vagy fájlrendszer
 *
 * @package IngatlanCom\ApiClient
 */
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
     * iroda rendszerében levő fotók
     * @var array
     */
    private $localPhotosByOwnId;

    /**
     * ingatlan.com rendszerében levő képek
     * @var array
     */
    private $uploadedPhotosByOwnId;

    /**
     * rendezendő kepek
     * @var array
     */
    private $photoSortQueue = array();

    /**
     * feltoltendő képek
     * @var array
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
     * Képszinkron eredménye, feltöltött képek
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
     * A teljes szinkronizálási folyamat
     *
     * @param string     $adOwnId hirdetés saját azonosító
     * @param array      $photos iroda rendszerében levő fotók adatai
     * @param bool       $forceImageDataUpdate akkor is töltsük le a fotót az iroda rendszeréből, ha már fel van töltve adott azonosítóval
     * @param array|null $uploadedPhotos ingatlan.com rendszerében levő fotók adatai
     * @param bool       $paralellDownload párhuzamos fotóletöltés az iroda szerveréről
     * @return PhotoSync
     * @throws TransferException
     */
    public function syncPhotos(
        $adOwnId,
        array $photos,
        $forceImageDataUpdate = false,
        array $uploadedPhotos = null,
        $paralellDownload = false
    ) {
        if (null === $uploadedPhotos) {
            $uploadedPhotos = $this->apiClient->getPhotos($adOwnId);
        }

        $this->localPhotosByOwnId = $this->mapArrayByField($photos, 'ownId');
        $this->uploadedPhotosByOwnId = $this->mapArrayByField($uploadedPhotos, 'ownId');

        //delete
        $photosToDelete = array_diff_key($this->uploadedPhotosByOwnId, $this->localPhotosByOwnId);
        $deleteResults = $this->apiClient->deletePhotosMulti($adOwnId, $photosToDelete);
        $this->deletePhotoErrors = $this->parseMultiTransferErrors($deleteResults, $photosToDelete);

        //fetch image data, diff with uploaded
        $this->buildPhotoQueues($forceImageDataUpdate, $paralellDownload);

        //put
        $putResults = $this->apiClient->putPhotosMulti($adOwnId, $this->photoPutQueue);
        $this->putPhotoErrors = $this->parseMultiTransferErrors($putResults, $this->photoPutQueue);

        //fix order
        $this->photos = $this->syncPhotosPutOrder($adOwnId,
            array_merge(array_diff_key($this->photoSortQueue, $this->putPhotoErrors), $this->deletePhotoErrors));

        return $this;
    }

    /**
     * Tömbből asszociatív tömböt készít valamely mező alapján
     *
     * @param array  $array tömb
     * @param string $field mező
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
     * fotó feltöltési és rendezési sorok létrehozása
     *
     * @param bool $forceImageDataUpdate akkor is töltsük le a fotót az iroda rendszeréből, ha már fel van töltve adott azonosítóval
     * @param bool $paralellDownload párhuzamos fotóletöltés az iroda szerveréről
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
     * Fotók letöltése az iroda szerveréről, ellenőrzés, hogy szükséges-e a betöltés
     * az ingatlan.com rendszerébe
     *
     * @param array $photosByOwnId hirdetés képeinek adatai, saját azonosító szerint indexelve
     * @param bool  $paralellDownload párhuzamos fotóletöltés az iroda szerveréről
     */
    private function downloadPhotosToQueues(array $photosByOwnId, $paralellDownload)
    {
        $imageDatas = $this->photoResizeService->getResizedPhotosData($photosByOwnId, $paralellDownload);

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
     * Feltöltés szükségességének ellenőrzése
     * MD5 hash és képadatok alapján
     *
     * @param array  $photoData fotó adatok
     * @param string $imageData fotó bináris formátumban
     * @return int feltöltés típusa
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
     * Fotó adatok különbségének vizsgálata
     *
     * @param array $photo1 fotó adatok
     * @param array $photo2 fotó adatok
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
     * Megnézi, hogy a párhuzamos kérések között volt-e, ami sikertelen
     *
     * @param array $results A requestek eredményei
     * @param array $photosByOwnId
     * @return array
     */
    private function parseMultiTransferErrors(array $results, array $photosByOwnId)
    {
        $errors = [];
        foreach ($results as $index => $result) {
            if ($result['state'] == PromiseInterface::REJECTED && $result['value'] instanceof \Exception) {
                /** @var \Exception $exception */
                $exception = $result['value'];

                $errorPhoto = $photosByOwnId[$index];
                $errorPhoto['exception'] = $exception;
                if ($exception instanceof RequestException) {
                    try {
                        $error = $this->apiClient->parseResponse($exception->getResponse());
                    } catch (ServerErrorException $jse) {
                        $error = 'Server error: ' . $jse->getJSendResponse()->getErrorMessage();
                    } catch (JSendFailException $jse) {
                        $error = $jse->getJSendResponse()->getData();
                        $error = isset($error['message']) ? $error['message'] : $error;
                    } catch (\UnexpectedValueException $uve) {
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
     * @param array  $photosByOwnId hirdetés képeinek adatai, saját azonosító szerint indexelve
     * @return array hirdetés fotói
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
