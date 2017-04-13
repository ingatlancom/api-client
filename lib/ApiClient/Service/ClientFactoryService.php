<?php

namespace IngatlanCom\ApiClient\Service;

use GuzzleHttp\Client;
use IngatlanCom\ApiClient\ApiClient;

/**
 * Class ClientFactoryService
 *
 * Guzzle Client factory
 *
 * @package IngatlanCom\ApiClient\Service
 */
class ClientFactoryService
{
    /**
     * @param string $baseUrl
     * @param null   $config
     * @return Client
     */
    public function getClient($baseUrl = '', $config = null)
    {
        $config['base_uri'] = $baseUrl;
        $config['headers'] = ['X-Icom-Client-Version' => ApiClient::CLIENT_VERSION];

        return new Client($config);
    }
}
