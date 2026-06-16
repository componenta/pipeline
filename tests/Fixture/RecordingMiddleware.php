<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware that appends its name to a shared log and delegates to the next
 * handler. Optionally short-circuits by returning a preconfigured response
 * without calling the handler.
 */
final class RecordingMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $log Shared execution log reference.
     */
    public function __construct(
        private readonly string $name,
        private array &$log,
        private readonly ?ResponseInterface $shortCircuit = null,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->log[] = $this->name;

        if ($this->shortCircuit !== null) {
            return $this->shortCircuit;
        }

        return $handler->handle($request);
    }
}
