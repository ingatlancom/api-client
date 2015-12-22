<?php

/**
 * ApiClient tesztek
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
        $this->apiClient = new \IngatlanCom\ApiClient\ApiClient('', null, $this->clientFactoryService);
    }

    public function testLoginSuccess()
    {
        $this->setMockResponse($this->clientFactoryService->getClient(null), array('loginSuccess'));
        $this->apiClient->login('lolka', 'bolka');
    }

    /**
     * @expectedException \IngatlanCom\ApiClient\Exception\NotAuthenticatedException
     */
    public function testLoginFail()
    {
        $this->setMockResponse($this->clientFactoryService->getClient(null), array('loginFail'));
        $this->apiClient->login('lolka', 'bolka');
    }

    public function testPutAdSuccess()
    {
        $this->setMockResponse($this->clientFactoryService->getClient(null), array('loginSuccess', 'putAdSuccess'));
        $this->apiClient->login('lolka', 'bolka');
        $this->apiClient->putAd(array('ownId' => 'i12345'));
    }

    public function testPutAdFail()
    {
        $this->setMockResponse($this->clientFactoryService->getClient(null), array('loginSuccess', 'putAdFail'));
        $this->apiClient->login('lolka', 'bolka');
        try {
            $this->apiClient->putAd(array('ownId' => 'i12345'));
        } catch (\IngatlanCom\ApiClient\Exception\JSendFailException $e) {
            $this->assertArrayHasKey('listingType', $e->getJSendResponse()->getData());
        }
    }

    public function testSyncAds()
    {
        $this->setMockResponse($this->clientFactoryService->getClient(null), array('loginSuccess', 'getAdIdsSuccess', 'deleteAdSuccess', 'deleteAdSuccess'));
        $this->apiClient->login('lolka', 'bolka');

        $deleted = $this->apiClient->syncAds(array('ad2', 'ad4'));

        $this->assertEquals(array('ad1', 'ad3'), array_values($deleted));
    }

    public function testPutPhotosMultiSuccess()
    {
        $this->setMockResponse($this->clientFactoryService->getClient(null), array('loginSuccess', 'putPhotoSuccess', 'putPhotoSuccess'));
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
        $this->setMockResponse($this->clientFactoryService->getClient(null), array('loginSuccess', 'putPhotoSuccess', 'putPhotoFail'));
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
        $this->setMockResponse($this->clientFactoryService->getClient(null), array(
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

        //photos/2.jpg nincs
        $this->assertCount(0, $result->getDeletePhotoErrors());
        $this->assertCount(1, $result->getFetchPhotoErrors());
        $this->assertCount(0, $result->getPutPhotoErrors());
    }
}
