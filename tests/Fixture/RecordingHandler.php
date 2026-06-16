<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Terminal handler that records every invocation and returns a preconfigured
 * response. Used to verify chain traversal reaches the terminal and to
 * inspect the request seen by it.
 */
final class RecordingHandler implements RequestHandlerInterface
{
    public int $calls = 0;
    public ?ServerRequestInterface $lastRequest = null;

    public function __construct(private readonly ResponseInterface $response) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->calls++;
        $this->lastRequest = $request;

        return $this->response;
    }
}
