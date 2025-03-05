<?php

namespace Iliyazhid\PizzaTest\Http;

class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private $body;

    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setBody($body): self
    {
        $this->body = $body;
        return $this;
    }

    public function getBody(): string {
        return $this->body;
    }

    public function json($data, int $statusCode = 200): self
    {
        $this->setHeader('Content-Type', 'application/json');
        $this->setStatusCode($statusCode);
        if($data !== null) {
            $this->setBody(json_encode($data));
        }
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        echo $this->body;
    }
}
