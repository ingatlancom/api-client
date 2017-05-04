<?php
namespace IngatlanCom\ApiClient;

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
     * @return array
     */
    public function getFetchPhotoErrors()
    {
        return isset($this->errors['photoFetch']) ?  $this->errors['photoFetch'] : [];
    }

    /**
     * @return array
     */
    public function getDeletePhotoErrors()
    {
        return isset($this->errors['photoDelete']) ? $this->errors['photoDelete'] : [];
    }

    /**
     * @return array
     */
    public function getPutPhotoErrors()
    {
        return isset($this->errors['photoPut']) ? $this->errors['photoPut'] : [];
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
}
