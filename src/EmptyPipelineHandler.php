<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * Default fallback handler installed when no custom terminal is provided.
 *
 * Always throws {@see RuntimeException} to surface misconfiguration - a
 * pipeline that reaches this handler has exhausted its middleware chain
 * with no one producing a response.
 */
final class EmptyPipelineHandler implements RequestHandlerInterface
{
    /**
     * @throws RuntimeException Always.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Failed to process the request. The pipeline is empty!');
    }
}
