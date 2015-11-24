<?php
/**
 * Created by PhpStorm.
 * User: zooli
 * Date: 2015.11.12.
 * Time: 16:28
 */

namespace IngatlanCom\ApiClient\Exception;

use JSend\JSendResponse;

abstract class JSendException extends \Exception
{
    /**
     * @var JSendResponse
     */
    protected $response;

    /**
     * JSendException constructor.
     * @param string $message
     * @param int $code
     * @param \Exception|null $previous
     * @param JSendResponse $response
     */
    public function __construct($message = "", $code = 0, \Exception $previous = null, JSendResponse $response)
    {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    /**
     * @return JSendResponse
     */
    public function getJSendResponse()
    {
        return $this->response;
    }
}
