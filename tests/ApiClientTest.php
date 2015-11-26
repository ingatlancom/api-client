<?php

/**
 * Created by PhpStorm.
 * User: zooli
 * Date: 2015.11.25.
 * Time: 12:14
 */
class ApiClientTest extends \Guzzle\Tests\GuzzleTestCase
{
    /**
     * @var ClientFactoryMockService
     */
    private $clientFactoryService;

    /**
     * @var \IngatlanCom\ApiClient\ApiClient
     */
    private $apiClient;

    public function setUp()
    {
        $this->setMockBasePath(__DIR__ . '/mock/responses');
        $this->clientFactoryService = new ClientFactoryMockService();
        $this->clientFactoryService->setTestCase($this);
        $this->apiClient = new \IngatlanCom\ApiClient\ApiClient('', null, $this->clientFactoryService);
    }

    public function testLoginSuccess()
    {
        $this->clientFactoryService->setMocks(array('loginSuccess'));
        $this->apiClient->login('lolka', 'bolka');
    }

    /**
     * @expectedException Exception
     */
    public function testLoginFail()
    {
        $this->clientFactoryService->setMocks(array('loginFail'));
        $this->apiClient->login('lolka', 'bolka');
    }

    public function testPutAdSuccess()
    {
        $this->clientFactoryService->setMocks(array('loginSuccess', 'putAdSuccess'));
        $this->apiClient->login('lolka', 'bolka');
        $this->apiClient->putAd(array('ownId' => 'i12345'));
    }

    public function testPutAdFail()
    {
        $this->clientFactoryService->setMocks(array('loginSuccess', 'putAdFail'));
        $this->apiClient->login('lolka', 'bolka');
        try {
            $this->apiClient->putAd(array('ownId' => 'i12345'));
        } catch (\IngatlanCom\ApiClient\Exception\JSendFailException $e) {
            $this->assertArrayHasKey('listingType', $e->getJSendResponse()->getData());
        }
    }

    public function testSyncAds()
    {
        $this->clientFactoryService->setMocks(array('loginSuccess', 'getAdIdsSuccess', 'deleteAdSuccess', 'deleteAdSuccess'));
        $this->apiClient->login('lolka', 'bolka');

        $deleted = $this->apiClient->syncAds(array('ad2', 'ad4'));

        $this->assertEquals(array('ad1', 'ad3'), array_values($deleted));
    }

    public function testPutPhotosMultiSuccess()
    {
        $this->clientFactoryService->setMocks(array('loginSuccess', 'putPhotoSuccess', 'putPhotoSuccess'));
        $this->apiClient->login('lolka', 'bolka');

        $this->apiClient->putPhotosMulti('i12345', array(
            'p1' => array(
                'ownId' => 'p1'
            ),
            'p2' => array(
                'ownId' => 'p2'
            )
        ));
    }

    /**
     * @expectedException \Guzzle\Http\Exception\MultiTransferException
     */
    public function testPutPhotosMultiFail()
    {
        $this->clientFactoryService->setMocks(array('loginSuccess', 'putPhotoSuccess', 'putPhotoFail'));
        $this->apiClient->login('lolka', 'bolka');

        $this->apiClient->putPhotosMulti(
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
        $this->clientFactoryService->setMocks(array(
            'loginSuccess',
            'getPhotosSuccess',
            'deletePhotoSuccess',//1
            'deletePhotoSuccess',//4
            'putPhotoSuccess',//5
            //'putPhotoSuccess',//6, nem letezo kep
            'getPhotosSuccess'//putPhotoOrderSuccess ugyanolyan
        ));

        $this->apiClient->login('lolka', 'bolka');

        $result = $this->apiClient->syncPhotos('ad1', array(
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

        //photos2 nincs
        $this->assertCount(1, $result['errors']);
    }
}
