<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware;

use JsonException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Terminal handler that returns a 404 Not Found JSON response.
 *
 * Intended as a fallback for pipelines that serve JSON APIs; responds with
 * `{"status": 404, "message": "Not Found"}` regardless of the request.
 */
final readonly class NotFoundHandler implements RequestHandlerInterface
{
    public function __construct(private ResponseFactoryInterface $factory) {}

    /**
     * @throws JsonException If the body payload cannot be JSON-encoded.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->factory->createResponse(404);

        $response->getBody()->write(json_encode(
            ['status' => 404, 'message' => 'Not Found'],
            JSON_THROW_ON_ERROR,
        ));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
