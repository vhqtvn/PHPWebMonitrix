<?php

namespace App\Framework;

class CurlException extends \Exception
{
    private $response;
    private $url;
    private $httpCode;

    public function __construct(string $message, string $url = '', $response = null, int $httpCode = 0, \Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->url = $url;
        $this->response = $response;
        $this->httpCode = $httpCode;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
} 