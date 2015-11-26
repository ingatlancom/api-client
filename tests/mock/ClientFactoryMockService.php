<?php

/**
 * Created by PhpStorm.
 * User: zooli
 * Date: 2015.11.25.
 * Time: 14:00
 */
class ClientFactoryMockService extends \IngatlanCom\ApiClient\Service\ClientFactoryService
{
    /**
     * @var \Guzzle\Tests\GuzzleTestCase
     */
    private $testCase;

    /**
     * @var \Guzzle\Http\Client
     */
    private $client;

    /**
     * @param string $baseUrl
     * @param null $config
     * @return \Guzzle\Http\Client
     */
    public function getClient($baseUrl = '', $config = null)
    {
        $this->client = parent::getClient($baseUrl, $config);
        return $this->client;
    }

    public function setMocks(array $mocks)
    {
        $this->testCase->setMockResponse($this->client, $mocks);
    }

    public function setTestCase(\Guzzle\Tests\GuzzleTestCase $testCase)
    {
        $this->testCase = $testCase;
    }
}
