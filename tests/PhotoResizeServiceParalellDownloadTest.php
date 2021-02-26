<?php

use GuzzleHttp\Exception\ClientException;
use IngatlanCom\ApiClient\Service\Image\ImageException;
use IngatlanCom\ApiClient\Service\PhotoResizeService;
use PHPUnit\Framework\TestCase;

/**
 * Parhuzamos kepletoltes teszt
 */
class PhotoResizeServiceParalellDownloadTest extends TestCase
{
    public function testGetResizedPhotos(): void
    {
        $service = $this->getPhotoResizeService(
            [
                ['statusCode' => 200, 'fileName' => '1.jpg'],
                ['statusCode' => 200, 'fileName' => 'toosmall.png'],
                ['statusCode' => 404],
            ]
        );

        $photos = [
            1 => ['location' => 'http://1.jpg'],
            2 => ['location' => 'http://toosmall.png'],
            3 => ['location' => 'http://notfound.jpg'],
            4 => ['location' => __DIR__ . '/mock/photos/1.jpg']
        ];

        $res = $service->getResizedPhotosData($photos, true);

        self::assertTrue(is_string($res[1]));
        self::assertTrue(is_string($res[4]));
        self::assertTrue($res[2] instanceof ImageException);
        self::assertTrue($res[3] instanceof ClientException);

        $sizes1 = getimagesizefromstring($res[1]);
        $sizes4 = getimagesizefromstring($res[4]);
        self::assertEquals($sizes1, $sizes4);
    }

    /**
     * @param array $mocks
     * @return PhotoResizeService
     */
    private function getPhotoResizeService(array $mocks): PhotoResizeService
    {
        return new PhotoResizeService(null, new ClientFactoryMockService('photos', $mocks));
    }
}
