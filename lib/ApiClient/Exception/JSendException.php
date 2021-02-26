<?php

namespace IngatlanCom\ApiClient\Exception;

use Exception;
use JSend\JSendResponse;

abstract class JSendException extends Exception
{
    /**
     * @var JSendResponse|null
     */
    protected $response;

    /**
     * JSendException constructor.
     *
     * @param string $message
     * @param int $code
     * @param Exception|null $previous
     * @param JSendResponse|null $response
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        Exception $previous = null,
        JSendResponse $response = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    /**
     * @return JSendResponse|null
     */
    public function getJSendResponse(): ?JSendResponse
    {
        return $this->response;
    }
}
