<?php

use GuzzleHttp\Promise\PromiseInterface;
use IngatlanCom\ApiClient\ApiClient;
use IngatlanCom\ApiClient\Exception\JSendFailException;
use IngatlanCom\ApiClient\Exception\NotAuthenticatedException;
use PHPUnit\Framework\TestCase;

/**
 * ApiClient tesztek
 */
class ApiClientTest extends TestCase
{
    /**
     * @param array $mocks
     * @return ApiClient
     */
    public function getClient(array $mocks): ApiClient
    {
        return new ApiClient(
            '/',
            null,
            new ClientFactoryMockService('responses', $mocks)
        );
    }

    public function testLoginSuccess(): void
    {
        self::expectNotToPerformAssertions();
        $client = $this->getClient(
            [
                ['statusCode' => 200, 'fileName' => 'loginSuccess'],
            ]
        );
        $client->login('lolka', 'bolka');
    }

    public function testLoginFail(): void
    {
        self::expectException(NotAuthenticatedException::class);
        $client = $this->getClient(
            [
                ['statusCode' => 401, 'fileName' => 'loginFail'],
            ]
        );
        $client->login('lolka', 'bolka');
    }

    public function testPutAdSuccess(): void
    {
        self::expectNotToPerformAssertions();
        $client = $this->getClient(
            [
                ['statusCode' => 200, 'fileName' => 'loginSuccess'],
                ['statusCode' => 200, 'fileName' => 'putAdSuccess'],
            ]
        );
        $client->login('lolka', 'bolka');
        $client->putAd(array('ownId' => 'i12345'));
    }

    public function testPutAdFail(): void
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
        } catch (JSendFailException $e) {
            self::assertArrayHasKey('listingType', $e->getJSendResponse()->getData());
        }
    }

    public function testSyncAds(): void
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

        self::assertEquals(['ad1', 'ad3'], array_values($deleted));
    }

    public function testPutPhotosMultiSuccess(): void
    {
        $client = $this->getClient(
            [
                ['statusCode' => 200, 'fileName' => 'loginSuccess'],
                ['statusCode' => 200, 'fileName' => 'putPhotoSuccess'],
                ['statusCode' => 200, 'fileName' => 'putPhotoSuccess'],
            ]
        );
        $client->login('lolka', 'bolka');

        $result = $client->putPhotosMulti(
            'i12345',
            [
                'p1' => [
                    'ownId' => 'p1'
                ],
                'p2' => [
                    'ownId' => 'p2'
                ]
            ]
        );

        self::assertEquals(PromiseInterface::FULFILLED, $result['p1']['state']);
        self::assertEquals(PromiseInterface::FULFILLED, $result['p2']['state']);
    }

    public function testPutPhotosMultiFail(): void
    {
        $client = $this->getClient(
            [
                ['statusCode' => 200, 'fileName' => 'loginSuccess'],
                ['statusCode' => 200, 'fileName' => 'putPhotoSuccess'],
                ['statusCode' => 400, 'fileName' => 'putPhotoFail'],
            ]
        );
        $client->login('lolka', 'bolka');

        $result = $client->putPhotosMulti(
            'i12345',
            [
                'p1' => [
                    'ownId' => 'p1'
                ],
                'p2' => [
                    'ownId' => 'p2'
                ]
            ]
        );

        self::assertEquals(PromiseInterface::FULFILLED, $result['p1']['state']);
        self::assertEquals(PromiseInterface::REJECTED, $result['p2']['state']);
    }

    public function testSyncPhotos(): void
    {
        $client = $this->getClient(
            [
                ['statusCode' => 200, 'fileName' => 'loginSuccess'],
                ['statusCode' => 200, 'fileName' => 'getPhotosSuccess'],
                ['statusCode' => 200, 'fileName' => 'deletePhotoSuccess'], //1
                ['statusCode' => 200, 'fileName' => 'deletePhotoSuccess'], //4
                ['statusCode' => 200, 'fileName' => 'putPhotoSuccess'], //5
                ['statusCode' => 200, 'fileName' => 'getPhotosSuccess'], //putPhotoOrderSuccess ugyanolyan,
            ]
        );
        $client->login('lolka', 'bolka');

        $result = $client->syncPhotos(
            'ad1',
            [
                [
                    'ownId'   => 'p2',
                    'title'   => 'photo2',
                    'labelId' => null,
                    'order'   => 1
                ],
                [
                    'ownId'   => 'p3',
                    'title'   => 'photo3',
                    'labelId' => null,
                    'order'   => 2
                ],
                [
                    'ownId'    => 'p5',
                    'title'    => 'photo5',
                    'labelId'  => null,
                    'location' => __DIR__ . '/mock/photos/1.jpg',
                    'order'    => 3
                ],
                [
                    'ownId'    => 'p6',
                    'title'    => 'photo6',
                    'labelId'  => null,
                    'location' => __DIR__ . '/mock/photos/2.jpg',
                    'order'    => 4
                ]
            ]
        );

        //photos/2.jpg nincs
        self::assertCount(0, $result->getDeletePhotoErrors());
        self::assertCount(1, $result->getFetchPhotoErrors());
        self::assertCount(0, $result->getPutPhotoErrors());
    }

    public function testCheckApiStatus(): void
    {
        $client = $this->getClient(
            [
                ['statusCode' => 200, 'fileName' => 'checkApiStatus']
            ]
        );
        $isOk = $client->checkApiStatus();
        self::assertTrue($isOk);
    }
}
