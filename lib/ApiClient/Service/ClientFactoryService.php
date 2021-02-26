<?php

namespace IngatlanCom\ApiClient\Service;

use GuzzleHttp\Client;
use IngatlanCom\ApiClient\ApiClient;

/**
 * Guzzle Client factory
 */
class ClientFactoryService
{
    /**
     * @param string $baseUrl
     * @param array|null $config
     * @return Client
     */
    public function getClient(string $baseUrl = '', array $config = null): Client
    {
        $config['base_uri'] = $baseUrl;
        $config['headers'] = ['X-Icom-Client-Version' => ApiClient::CLIENT_VERSION];

        return new Client($config);
    }
}
