<?php
use IngatlanCom\ApiClient\Service\PhotoResizeService;

/**
 * Parhuzamos kepletoltes teszt
 */
class PhotoResizeServiceParalellDownloadTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \GuzzleHttp\Exception\ClientException
     */
    public function testGetResizedPhotos()
    {
        $service = $this->getPhotoResizeService(
            [
                ['statusCode' => 200, 'fileName' => '1.jpg'],
                ['statusCode' => 200, 'fileName' => 'toosmall.png'],
                ['statusCode' => 404],
            ]
        );

        $photos = array(
            1 => array('location' => 'http://1.jpg'),
            2 => array('location' => 'http://toosmall.png'),
            3 => array('location' => 'http://notfound.jpg'),
            4 => array('location' => __DIR__ . '/mock/photos/1.jpg'),
        );

        $res = $service->getResizedPhotosData($photos, true);

        $this->assertTrue(is_string($res[1]));
        $this->assertTrue(is_string($res[4]));
        $this->assertTrue($res[2] instanceof \IngatlanCom\ApiClient\Service\Image\ImageException);
        $this->assertTrue($res[3] instanceof \GuzzleHttp\Exception\ClientException);

        $sizes1 = getimagesizefromstring($res[1]);
        $sizes4 = getimagesizefromstring($res[4]);
        $this->assertEquals($sizes1, $sizes4);
    }

    /**
     * @param array $mocks
     * @return PhotoResizeService
     */
    private function getPhotoResizeService(array $mocks)
    {
        return new PhotoResizeService(null, new ClientFactoryMockService('photos', $mocks));
    }
}
