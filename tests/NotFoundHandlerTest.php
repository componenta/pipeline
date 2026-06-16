<?php

declare(strict_types=1);

use Componenta\Http\Middleware\NotFoundHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;

describe('NotFoundHandler::handle()', function () {
    it('returns a 404 response with a JSON body describing the error', function () {
        $handler = new NotFoundHandler(new Psr17Factory());

        $response = $handler->handle(new ServerRequest('GET', '/missing'));

        expect($response->getStatusCode())->toBe(404)
            ->and($response->getHeaderLine('Content-Type'))->toBe('application/json')
            ->and(json_decode((string) $response->getBody(), associative: true))
            ->toBe(['status' => 404, 'message' => 'Not Found']);
    });
});
