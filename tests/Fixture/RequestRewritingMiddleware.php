<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware that replaces the request passed to the next handler.
 */
final readonly class RequestRewritingMiddleware implements MiddlewareInterface
{
    public function __construct(private ServerRequestInterface $replacement) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($this->replacement);
    }
}
