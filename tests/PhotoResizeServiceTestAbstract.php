<?php

use IngatlanCom\ApiClient\Service\PhotoResizeService;

/**
 * Képátméretezés tesztek
 */
abstract class PhotoResizeServiceTestAbstract extends \Guzzle\Tests\GuzzleTestCase
{
    /**
     * @var PhotoResizeService
     */
    private $service;

    /**
     * @var integer PhotoResizeService::LIB_GB, PhotoResizeService::LIB_IMAGICK
     */
    protected $imageLibrary = PhotoResizeService::LIB_IMAGICK;

    public function setUp()
    {
        if (PhotoResizeService::LIB_IMAGICK == $this->imageLibrary && !extension_loaded('imagick')) {
            $this->markTestSkipped(
                'The imagick extension is not available.'
            );
        }

        if (PhotoResizeService::LIB_GD == $this->imageLibrary && !extension_loaded('gd')) {
            $this->markTestSkipped(
                'The gd extension is not available.'
            );
        }

        $this->service = new PhotoResizeService($this->imageLibrary);
    }

    public function tearDown()
    {
        $this->service = null;
    }

    public function testResize()
    {
        $this->service->getResizedPhotoData(__DIR__ . '/mock/photos/1.jpg');
    }

    /**
     * @expectedException \Exception
     */
    public function testResizeTooSmallNotGroundPlan()
    {
        $this->service->getResizedPhotoData(__DIR__ . '/mock/photos/toosmall.png');
    }

    public function testResizeTooSmall450x450ButGroundPlan()
    {
        $this->service->getResizedPhotoData(__DIR__ . '/mock/photos/groundplan450.jpg');
    }

    /**
     * @expectedException \Exception
     */
    public function testResizeTooSmall450x450NotGroundPlan()
    {
        $this->service->getResizedPhotoData(__DIR__ . '/mock/photos/notgroundplan450.jpg');
    }
}
