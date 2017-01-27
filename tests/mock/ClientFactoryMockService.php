<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use IngatlanCom\ApiClient\Service\ClientFactoryService;

class ClientFactoryMockService extends ClientFactoryService
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var HandlerStack
     */
    private $handler;

    /**
     * ClientFactoryMockService constructor.
     * @param       $mockDirectory
     * @param array $mocks
     */
    public function __construct($mockDirectory, array $mocks)
    {
        $responses = [];
        foreach ($mocks as $mockFile) {
            $body = isset($mockFile['fileName'])
                ? file_get_contents(__DIR__ . "/$mockDirectory/" . $mockFile['fileName'])
                : null;

            $responses[] = new Response($mockFile['statusCode'], [], $body);
        }

        $mock = new MockHandler($responses);
        $this->handler = HandlerStack::create($mock);
    }

    /**
     * @param string $baseUrl
     * @param null   $config
     * @return Client
     */
    public function getClient($baseUrl = '', $config = null)
    {
        if (!$this->client) {
            $config['handler'] = $this->handler;
            $this->client = parent::getClient($baseUrl, $config);
        }

        return $this->client;
    }
}
