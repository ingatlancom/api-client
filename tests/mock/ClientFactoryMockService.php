<?php

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class ClientFactoryMockService extends \IngatlanCom\ApiClient\Service\ClientFactoryService
{
    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    private $handler;

    /**
     * @param string $baseUrl
     * @param null $config
     * @return \GuzzleHttp\Client
     */
    public function getClient($baseUrl = '', $config = null)
    {
        if (!$this->client) {
            $config['handler'] = $this->handler;
            $this->client = parent::getClient($baseUrl, $config);
        }
        return $this->client;
    }

    public function __construct(array $mocks, $statusCode)
    {
        $responses = [];
        foreach ($mocks as $mock) {
            $responses[] = new Response($statusCode, [], file_get_contents(__DIR__ . '/responses/'.$mock));
        }

        $mock = new MockHandler($responses);
        $this->handler = HandlerStack::create($mock);
    }
}
