<?php

namespace Iliyazhid\PizzaTest\Controllers;

use Iliyazhid\PizzaTest\Http\Response;

abstract class Controller
{
    protected function jsonResponse(Response $response, $data, int $statusCode = 200): void
    {
        $response->json($data, $statusCode)->send();
    }

    protected function notFound(Response $response): void
    {
        $this->jsonResponse($response, ['error' => 'Not found'], 404);
    }

    protected function badRequest(Response $response, string $message = 'Invalid request'): void
    {
        $this->jsonResponse($response, ['error' => $message], 400);
    }

    protected function internalServerError(Response $response, string $message = 'Internal server error'): void
    {
        $this->jsonResponse($response, ['error' => $message], 500);
    }

}
