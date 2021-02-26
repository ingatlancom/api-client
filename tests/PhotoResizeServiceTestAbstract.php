<?php

use IngatlanCom\ApiClient\Service\PhotoResizeService;
use PHPUnit\Framework\TestCase;

/**
 * Képátméretezés tesztek
 */
abstract class PhotoResizeServiceTestAbstract extends TestCase
{
    /**
     * @var PhotoResizeService
     */
    private $service;

    /**
     * @var integer PhotoResizeService::LIB_GB, PhotoResizeService::LIB_IMAGICK
     */
    protected $imageLibrary = PhotoResizeService::LIB_IMAGICK;

    public function setUp(): void
    {
        if (PhotoResizeService::LIB_IMAGICK == $this->imageLibrary && !extension_loaded('imagick')) {
            self::markTestSkipped(
                'The imagick extension is not available.'
            );
        }

        if (PhotoResizeService::LIB_GD == $this->imageLibrary && !extension_loaded('gd')) {
            self::markTestSkipped(
                'The gd extension is not available.'
            );
        }

        $this->service = new PhotoResizeService($this->imageLibrary);
    }

    public function tearDown(): void
    {
        unset($this->service);
    }

    public function testResize(): void
    {
        self::expectNotToPerformAssertions();
        $this->service->getResizedPhotoData(__DIR__ . '/mock/photos/1.jpg');
    }

    public function testResizeTooSmallNotGroundPlan(): void
    {
        self::expectException(Exception::class);
        $this->service->getResizedPhotoData(__DIR__ . '/mock/photos/toosmall.png');
    }

    public function testResizeTooSmall450x450ButGroundPlan(): void
    {
        self::expectNotToPerformAssertions();
        $this->service->getResizedPhotoData(__DIR__ . '/mock/photos/groundplan450.jpg');
    }

    public function testResizeTooSmall450x450NotGroundPlan(): void
    {
        self::expectException(Exception::class);
        $this->service->getResizedPhotoData(__DIR__ . '/mock/photos/notgroundplan450.jpg');
    }
}
