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
     * @var array
     */
    private $mocks = array();

    /**
     * @var \Guzzle\Tests\GuzzleTestCase
     */
    private $testCase;

    /**
     * @param string $baseUrl
     * @param null $config
     * @return \Guzzle\Http\Client
     */
    public function getClient($baseUrl = '', $config = null)
    {
        $client = parent::getClient($baseUrl, $config);
        $this->testCase->setMockResponse($client, $this->getMocks());

        return $client;
    }

    public function getMocks()
    {
        return (array)array_shift($this->mocks);
    }

    public function setMocks(array $mocks)
    {
        $this->mocks = $mocks;
    }

    public function setTestCase(\Guzzle\Tests\GuzzleTestCase $testCase)
    {
        $this->testCase = $testCase;
    }
}
