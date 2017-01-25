<?php

namespace IngatlanCom\ApiClient\Service;

use GuzzleHttp\Client;

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
     * @param null $config
     * @return Client
     */
    public function getClient($baseUrl = '', $config = null)
    {
        $config['base_uri'] = $baseUrl;

        return new Client($config);
    }
}
