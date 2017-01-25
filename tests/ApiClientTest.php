<?php

use IngatlanCom\ApiClient\ApiClient;

/**
 * ApiClient tesztek
 */
class ApiClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param array $mocks
     * @return ApiClient
     */
    public function getClient(array $mocks)
    {
        return new ApiClient('/', null, new ClientFactoryMockService('responses', $mocks));
    }

    public function testLoginSuccess()
    {
        $client = $this->getClient(
            [
                ['statusCode' => 200, 'fileName' => 'loginSuccess'],
            ]
        );
        $client->login('lolka', 'bolka');
    }

    /**
     * @expectedException IngatlanCom\ApiClient\Exception\NotAuthenticatedException
     */
    public function testLoginFail()
    {
        $client = $this->getClient(
            [
                ['statusCode' => 401, 'fileName' => 'loginFail'],
            ]
        );
        $client->login('lolka', 'bolka');
    }

    public function testPutAdSuccess()
    {
        $client = $this->getClient(
            [
                ['statusCode' => 200, 'fileName' => 'loginSuccess'],
                ['statusCode' => 200, 'fileName' => 'putAdSuccess'],
            ]
        );
        $client->login('lolka', 'bolka');
        $client->putAd(array('ownId' => 'i12345'));
    }

    public function testPutAdFail()
    {
        $client = $this->getClient(
            [
                ['statusCode' => 200, 'fileName' => 'loginSuccess'],
                ['statusCode' => 400, 'fileName' => 'putAdFail'],
            ]
        );
        $client->login('lolka', 'bolka');

        try {
            $client->putAd(['ownId' => 'i12345']);
        } catch (\IngatlanCom\ApiClient\Exception\JSendFailException $e) {
            $this->assertArrayHasKey('listingType', $e->getJSendResponse()->getData());
        }
    }

    public function testSyncAds()
    {
        $client = $this->getClient(
            [
                ['statusCode' => 200, 'fileName' => 'loginSuccess'],
                ['statusCode' => 200, 'fileName' => 'getAdIdsSuccess'],
                ['statusCode' => 200, 'fileName' => 'deleteAdSuccess'],
                ['statusCode' => 200, 'fileName' => 'deleteAdSuccess'],
            ]
        );
        $client->login('lolka', 'bolka');

        $deleted = $client->syncAds(['ad2', 'ad4']);

        $this->assertEquals(['ad1', 'ad3'], array_values($deleted));
    }

    public function testPutPhotosMultiSuccess()
    {
        $client = $this->getClient(
            [
                ['statusCode' => 200, 'fileName' => 'loginSuccess'],
                ['statusCode' => 200, 'fileName' => 'putPhotoSuccess'],
                ['statusCode' => 200, 'fileName' => 'putPhotoSuccess'],
            ]
        );
        $client->login('lolka', 'bolka');

        $client->putPhotosMulti(
            'i12345',
            array(
                'p1' => array(
                    'ownId' => 'p1',
                ),
                'p2' => array(
                    'ownId' => 'p2',
                ),
            )
        );
    }

    public function testPutPhotosMultiFail()
    {
        $client = $this->getClient(
            [
                ['statusCode' => 200, 'fileName' => 'loginSuccess'],
                ['statusCode' => 200, 'fileName' => 'putPhotoSuccess'],
                ['statusCode' => 400, 'fileName' => 'putPhotoFail'],
            ]
        );
        $client->login('lolka', 'bolka');

        $client->putPhotosMulti(
            'i12345',
            array(
                'p1' => array(
                    'ownId' => 'p1',
                ),
                'p2' => array(
                    'ownId' => 'p2',
                ),
            )
        );
    }

    public function testSyncPhotos()
    {
        $client = $this->getClient(
            [
                ['statusCode' => 200, 'fileName' => 'loginSuccess'],
                ['statusCode' => 200, 'fileName' => 'getPhotosSuccess'],
                ['statusCode' => 200, 'fileName' => 'deletePhotoSuccess'], //1
                ['statusCode' => 200, 'fileName' => 'deletePhotoSuccess'], //4
                ['statusCode' => 200, 'fileName' => 'getPhotosSuccess'],
                ['statusCode' => 200, 'fileName' => 'putPhotoSuccess'], //5
                ['statusCode' => 200, 'fileName' => 'getPhotosSuccess'], //putPhotoOrderSuccess ugyanolyan,
            ]
        );
        $client->login('lolka', 'bolka');

        $result = $client->syncPhotos(
            'ad1',
            array(
                array(
                    'ownId'   => 'p2',
                    'title'   => 'photo2',
                    'labelId' => null,
                    'order'   => 1,
                ),
                array(
                    'ownId'   => 'p3',
                    'title'   => 'photo3',
                    'labelId' => null,
                    'order'   => 2,
                ),
                array(
                    'ownId'    => 'p5',
                    'title'    => 'photo5',
                    'labelId'  => null,
                    'location' => __DIR__ . '/mock/photos/1.jpg',
                    'order'    => 3,
                ),
                array(
                    'ownId'    => 'p6',
                    'title'    => 'photo6',
                    'labelId'  => null,
                    'location' => __DIR__ . '/mock/photos/2.jpg',
                    'order'    => 4,
                ),
            )
        );

        //photos/2.jpg nincs
        $this->assertCount(0, $result->getDeletePhotoErrors());
        $this->assertCount(1, $result->getFetchPhotoErrors());
        $this->assertCount(0, $result->getPutPhotoErrors());
    }
}
