<?php
/**
 * Created by PhpStorm.
 * User: zooli
 * Date: 2015.11.25.
 * Time: 13:55
 */

namespace IngatlanCom\ApiClient\Service;

use Guzzle\Http\Client;

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
