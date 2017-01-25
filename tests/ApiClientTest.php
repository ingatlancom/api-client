<?php

use IngatlanCom\ApiClient\ApiClient;

/**
 * ApiClient tesztek
 */
class ApiClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param array $mock
     * @param int    $statusCode
     * @return ApiClient
     */
    public function getClient (array $mock, $statusCode) {
        return new ApiClient('/', null, new ClientFactoryMockService($mock, $statusCode));
    }

    public function testLoginSuccess()
    {
        $client = $this->getClient(['loginSuccess'], 200);
        $client->login('lolka', 'bolka');
    }

    /**
     * @expectedException IngatlanCom\ApiClient\Exception\NotAuthenticatedException
     */
    public function testLoginFail()
    {
        $client = $this->getClient(['loginFail'], 401);
        $client->login('lolka', 'bolka');
    }

    public function testPutAdSuccess()
    {
        $client = $this->getClient(['loginSuccess', 'putAdSuccess'], 200);
        $client->login('lolka', 'bolka');
        $client->putAd(array('ownId' => 'i12345'));
    }

    public function testPutAdFail()
    {
        $client = $this->getClient(['loginSuccess', 'putAdFail'], 400);
        $client->login('lolka', 'bolka');

        try {
            $client->putAd(['ownId' => 'i12345']);
        } catch (\IngatlanCom\ApiClient\Exception\JSendFailException $e) {
            $this->assertArrayHasKey('listingType', $e->getJSendResponse()->getData());
        }
    }

    public function testSyncAds()
    {
        $client = $this->getClient(['loginSuccess', 'getAdIdsSuccess', 'deleteAdSuccess', 'deleteAdSuccess'], 400);
        $client->login('lolka', 'bolka');

        $deleted = $client->syncAds(['ad2', 'ad4']);

        $this->assertEquals(['ad1', 'ad3'], array_values($deleted));
    }

    public function testPutPhotosMultiSuccess()
    {
        $client = $this->getClient(['loginSuccess', 'putPhotoSuccess', 'putPhotoSuccess'], 400);
        $client->login('lolka', 'bolka');

        $client->putPhotosMulti('i12345', array(
            'p1' => array(
                'ownId' => 'p1'
            ),
            'p2' => array(
                'ownId' => 'p2'
            )
        ));
    }

    /**
     * @expectedException \GuzzleHttp\Exception\TransferException
     */
    public function testPutPhotosMultiFail()
    {
        $client = $this->getClient(['loginSuccess', 'putPhotoSuccess', 'putPhotoFail'], 400);
        $client->login('lolka', 'bolka');

        $client->putPhotosMulti(
            'i12345',
            array(
                'p1' => array(
                    'ownId' => 'p1'
                ),
                'p2' => array(
                    'ownId' => 'p2'
                )
            )
        );
    }

    public function testSyncPhotos()
    {
        $client = $this->getClient([
            'loginSuccess',
            'getPhotosSuccess',
            'deletePhotoSuccess',//1
            'deletePhotoSuccess',//4
            'putPhotoSuccess',//5
            //'putPhotoSuccess',//6, nem letezo kep
            'getPhotosSuccess'//putPhotoOrderSuccess ugyanolyan
        ], 400);
        $client->login('lolka', 'bolka');

        $result = $client->syncPhotos('ad1', array(
            array(
                'ownId' => 'p2',
                'title' => 'photo2',
                'labelId' => null,
                'order' => 1
            ),
            array(
                'ownId' => 'p3',
                'title' => 'photo3',
                'labelId' => null,
                'order' => 2
            ),
            array(
                'ownId' => 'p5',
                'title' => 'photo5',
                'labelId' => null,
                'location' => __DIR__ . '/mock/photos/1.jpg',
                'order' => 3
            ),
            array(
                'ownId' => 'p6',
                'title' => 'photo6',
                'labelId' => null,
                'location' => __DIR__ . '/mock/photos/2.jpg',
                'order' => 4
            )
        ));

        //photos/2.jpg nincs
        $this->assertCount(0, $result->getDeletePhotoErrors());
        $this->assertCount(1, $result->getFetchPhotoErrors());
        $this->assertCount(0, $result->getPutPhotoErrors());
    }
}
