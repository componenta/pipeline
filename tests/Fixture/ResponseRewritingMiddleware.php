<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware that calls the next handler but discards its response,
 * returning a preconfigured replacement instead.
 */
final readonly class ResponseRewritingMiddleware implements MiddlewareInterface
{
    public function __construct(private ResponseInterface $replacement) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $handler->handle($request);

        return $this->replacement;
    }
}
