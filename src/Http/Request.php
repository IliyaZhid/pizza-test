<?php

namespace Iliyazhid\PizzaTest\Http;

class Request
{
    private string $method;
    private string $uri;
    private array $headers;
    private array $body;
    private array $queryParams;

    public function __construct(string $method = null, string $uri = null, array $headers = [], array $body = [], array $queryParams = []) {
        $this->method = $method ?? $_SERVER['REQUEST_METHOD'];
        $this->uri = $uri ?? trim($_SERVER['REQUEST_URI'], '/');
        $this->headers = !empty($headers) ? $headers : getallheaders();
        $this->body = !empty($body) ? $body : json_decode(file_get_contents('php://input'), true) ?? [];
        $this->queryParams = !empty($queryParams) ? $queryParams : $_GET;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function getQueryParam(string $name, $default = null)
    {
        return $this->queryParams[$name] ?? $default;
    }

}
