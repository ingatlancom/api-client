<?php

use IngatlanCom\ApiClient\Service\PhotoResizeService;

/**
 * Photo Resize tests with GD
 */
class PhotoResizeServiceGDTest extends PhotoResizeServiceTestAbstract
{
    /**
     * @var integer PhotoResizeService::LIB_GB, PhotoResizeService::LIB_IMAGICK
     */
    protected $imageLibrary = PhotoResizeService::LIB_GD;
}
