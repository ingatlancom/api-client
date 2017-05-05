<?php
namespace IngatlanCom\ApiClient;

/**
 * A fotók szinkronizálásának eredménye (ApiClient::syncPhotos() hívásakor kapjuk vissza)
 */
class PhotoSyncResult
{
    /**
     * @var $photos
     */
    private $photos = [];

    /**
     * @var $errors
     */
    private $errors = [];

    /**
     * PhotoSyncResult constructor.
     * @param array $photos
     * @param array $errors
     */
    public function __construct(array $photos, array $errors)
    {
        $this->photos = $photos;
        $this->errors = $errors;
    }

    /**
     * @return array
     */
    public function getPhotos()
    {
        return $this->photos;
    }

    /**
     * @param string $arrayName
     * @return array
     */
    private function getErrorsFromArray($arrayName)
    {
        return isset($this->errors[$arrayName]) ?  $this->errors[$arrayName] : [];
    }

    /**
     * @return array
     */
    public function getFetchPhotoErrors()
    {
        return $this->getErrorsFromArray('photoFetch');
    }

    /**
     * A fotók letöltésének hibaüzenetei own id indexű tömbben
     *
     * @return array
     */
    public function getFetchPhotoErrorMessages()
    {
        return array_column($this->getFetchPhotoErrors(), 'errorMessage', 'ownId');
    }

    /**
     * @return array
     */
    public function getDeletePhotoErrors()
    {
        return $this->getErrorsFromArray('photoDelete');
    }

    /**
     * A fotók törlésének hibaüzenetei own id indexű tömbben
     *
     * @return array
     */
    public function getDeletePhotoErrorMessages()
    {
        return array_column($this->getDeletePhotoErrors(), 'errorMessage', 'ownId');
    }

    /**
     * @return array
     */
    public function getPutPhotoErrors()
    {
        return $this->getErrorsFromArray('photoPut');
    }

    /**
     * A fotók feltöltésének hibaüzenetei own id indexű tömbben
     *
     * @return array
     */
    public function getPutPhotoErrorMessages()
    {
        return array_column($this->getPutPhotoErrors(), 'errorMessage', 'ownId');
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return array_merge(
            $this->getDeletePhotoErrors(),
            $this->getFetchPhotoErrors(),
            $this->getPutPhotoErrors()
        );
    }

    /**
     * A szinkronizálás közben történt hibák own id indexű tömbben
     *
     * @return array
     */
    public function getErrorMessages()
    {
        return $this->getFetchPhotoErrorMessages() + $this->getDeletePhotoErrorMessages() + $this->getPutPhotoErrorMessages();
    }
}
