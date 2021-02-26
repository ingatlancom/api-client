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
     * @param string $mockDirectory
     * @param array $mocks
     */
    public function __construct(string $mockDirectory, array $mocks)
    {
        $responses = [];
        foreach ($mocks as $mockFile) {
            $body = null;
            if (
                isset($mockFile['fileName']) &&
                file_exists(__DIR__ . "/$mockDirectory/" . $mockFile['fileName'])
            ) {
                $content = file_get_contents(__DIR__ . "/$mockDirectory/" . $mockFile['fileName']);
                if ($content !== false) {
                    $body = $content;
                }
            }

            $responses[] = new Response($mockFile['statusCode'], [], $body);
        }

        $mock = new MockHandler($responses);
        $this->handler = HandlerStack::create($mock);
    }

    /**
     * @param string $baseUrl
     * @param array|null $config
     * @return Client
     */
    public function getClient(string $baseUrl = '', array $config = null): Client
    {
        if (!isset($this->client)) {
            $config['handler'] = $this->handler;
            $this->client = parent::getClient($baseUrl, $config);
        }

        return $this->client;
    }
}
