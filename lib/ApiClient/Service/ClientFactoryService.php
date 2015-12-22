<?php

namespace IngatlanCom\ApiClient\Service;

use Guzzle\Http\Client;

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
        return new Client($baseUrl, $config);
    }
}
