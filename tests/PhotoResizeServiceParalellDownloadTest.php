<?php
use GuzzleHttp\Psr7\Response;
use IngatlanCom\ApiClient\Service\PhotoResizeService;

/**
 * Parhuzamos kepletoltes teszt
 */
class PhotoResizeServiceParalellDownloadTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ClientFactoryMockService
     */
    private $clientFactoryService;

    /**
     * @var PhotoResizeService
     */
    private $service;

    public function setUp()
    {
        $this->clientFactoryService = new ClientFactoryMockService();
        $this->service = new PhotoResizeService(null, $this->clientFactoryService);
    }

    public function testGetResizedPhotos()
    {
        $this->setMockResponse($this->clientFactoryService->getClient(), array(
            new Response(200, null, file_get_contents(__DIR__ . '/mock/photos/1.jpg')),
            new Response(200, null, file_get_contents(__DIR__ . '/mock/photos/toosmall.png')),
            new Response(404)
        ));

        $photos = array(
            1 => array('location' => 'http://1.jpg'),
            2 => array('location' => 'http://toosmall.png'),
            3 => array('location' => 'http://notfound.jpg'),
            4 => array('location' => __DIR__ . '/mock/photos/1.jpg')
        );

        $res = $this->service->getResizedPhotosData($photos, true);

        $this->assertTrue(is_string($res[1]));
        $this->assertTrue(is_string($res[4]));
        $this->assertTrue($res[2] instanceof \IngatlanCom\ApiClient\Service\Image\ImageException);
        $this->assertTrue($res[3] instanceof \GuzzleHttp\Exception\ClientException);

        $sizes1 = getimagesizefromstring($res[1]);
        $sizes4 = getimagesizefromstring($res[4]);
        $this->assertEquals($sizes1, $sizes4);
    }
}
