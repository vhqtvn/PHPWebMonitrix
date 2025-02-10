<?php

namespace App\Framework;

use PhpOption\Option;

class Curl
{
    private \CurlHandle $client;
    private ?string $lastResponse;

    private function __construct()
    {
        $this->client = curl_init();
    }

    public function __destruct()
    {
        @curl_close($this->client);
    }

    public function setOption(int $option, mixed $value)
    {
        curl_setopt($this->client, $option, $value);
    }

    public function setOptionsArray(array $options)
    {
        curl_setopt_array($this->client, $options);
    }

    public function getLastError()
    {
        return curl_error($this->client);
    }

    public function getLastErrorNo()
    {
        return curl_errno($this->client);
    }

    public function getLastInfo()
    {
        return curl_getinfo($this->client);
    }

    public function execute(
        bool $throwError = true,
    ) {
        $result = curl_exec($this->client);
        if (is_string($result)) {
            $this->lastResponse = $result;
        } else {
            if ($result === false) {
                // error
                $this->lastResponse = null;
                if ($throwError) {
                    $this->throwLastError();
                }
            } else {
                // success, but CURLOPT_RETURNTRANSFER is false
                $this->lastResponse = null;
            }
        }
        return $this;
    }

    public function html()
    {
        return $this->lastResponse;
    }

    private function throwLastError()
    {
        $info = $this->getLastInfo();
        throw new CurlException(
            $this->getLastError(),
            $info['url'] ?? '',
            $this->lastResponse,
            $info['http_code'] ?? 0
        );
    }

    public static function createDefaultClient()
    {
        return new static();
    }

    public static function N(
        $url,
        $option = [],
        $method = 'GET',
        $body = false,
        $contentType = false,
        $header = null,
        $httpHeaders = null,
        $timeout = null,
        $proxy = null,
        $throwError = true,
    ) {
        $client = static::createDefaultClient();

        $client->setOptionsArray($option + [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        ]);

        $client->setHeaderCond(CURLOPT_TIMEOUT, $timeout, null);
        $client->setHeaderCond(CURLOPT_PROXY, $proxy, null);
        $client->setHeaderCond(CURLOPT_HEADER, $header, null);
        $client->setHeaderCond(CURLOPT_HTTPHEADER, $httpHeaders, null);

        $method = strtoupper($method);

        $needBody = in_array($method, ['POST', 'PUT', 'PATCH']);

        if ($needBody) {
            if ($body === false) {
                throw new \Exception("Body is required for {$method}");
            }

            $client->setOption(CURLOPT_POST, true);
            $client->setOption(CURLOPT_POSTFIELDS, $body);

            if ($contentType !== false) {
                $client->setOption(CURLOPT_HTTPHEADER, [
                    "Content-Type: {$contentType}"
                ]);
            }
        }

        return $client->execute(throwError: $throwError);
    }

    public function setHeaderCond(int $option, $value, $nullValue)
    {
        if ($value !== $nullValue) {
            curl_setopt($this->client, $option, $value);
        }
    }
}
