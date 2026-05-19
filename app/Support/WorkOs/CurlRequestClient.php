<?php

namespace App\Support\WorkOs;

use WorkOS\Client;
use WorkOS\Exception\GenericException;
use WorkOS\RequestClient\RequestClientInterface;

class CurlRequestClient implements RequestClientInterface
{
    public function __construct(
        private readonly ?int $ipResolve = null,
    ) {}

    public function request($method, $url, ?array $headers = null, ?array $params = null)
    {
        $headers ??= [];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($this->ipResolve) {
            $opts[CURLOPT_IPRESOLVE] = $this->ipResolve;
        }

        match ($method) {
            Client::METHOD_GET => $this->configureGetRequest($opts, $url, $params),
            Client::METHOD_POST => $this->configureJsonRequest($opts, $headers, CURLOPT_POST, true, $params),
            Client::METHOD_DELETE => $this->configureCustomRequest($opts, Client::METHOD_DELETE, $params),
            Client::METHOD_PUT => $this->configureJsonRequest($opts, $headers, CURLOPT_CUSTOMREQUEST, 'PUT', $params),
            Client::METHOD_PATCH => $this->configureJsonRequest($opts, $headers, CURLOPT_CUSTOMREQUEST, 'PATCH', $params),
            default => null,
        };

        $opts[CURLOPT_URL] = $url;
        $opts[CURLOPT_HTTPHEADER] = $headers;

        return $this->execute($opts);
    }

    private function configureGetRequest(array &$opts, string &$url, ?array $params): void
    {
        if ($params) {
            $url .= '?'.http_build_query($params);
        }
    }

    private function configureJsonRequest(array &$opts, array &$headers, int $methodOption, mixed $methodValue, ?array $params): void
    {
        $headers[] = 'Content-Type: application/json';
        $opts[$methodOption] = $methodValue;

        if ($methodOption !== CURLOPT_POST) {
            $opts[CURLOPT_POST] = true;
        }

        if ($params) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($params);
        }
    }

    private function configureCustomRequest(array &$opts, string $method, ?array $params): void
    {
        $opts[CURLOPT_CUSTOMREQUEST] = strtoupper($method);

        if ($params) {
            $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
        }
    }

    private function execute(array $opts): array
    {
        $curl = curl_init();
        $headers = [];

        $opts[CURLOPT_HEADERFUNCTION] = function ($curl, string $headerLine) use (&$headers): int {
            if (! str_contains($headerLine, ':')) {
                return strlen($headerLine);
            }

            [$key, $value] = explode(':', trim($headerLine), 2);
            $headers[trim($key)] = trim($value);

            return strlen($headerLine);
        };

        curl_setopt_array($curl, $opts);

        $result = curl_exec($curl);

        if ($result === false) {
            $errno = curl_errno($curl);
            $message = curl_error($curl);
            curl_close($curl);

            throw new GenericException($message, ['curlErrno' => $errno]);
        }

        $statusCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return [$result, $headers, $statusCode];
    }
}
