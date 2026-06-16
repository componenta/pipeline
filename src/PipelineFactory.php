<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Default {@see PipelineFactoryInterface} implementation.
 *
 * Produces a {@see Pipeline} wired with {@see EmptyPipelineHandler} as the
 * fallback when none is supplied.
 */
final class PipelineFactory implements PipelineFactoryInterface
{
    /**
     * @param iterable<MiddlewareInterface> $middlewares
     */
    public function createMiddlewarePipeline(
        iterable $middlewares = [],
        ?RequestHandlerInterface $fallbackHandler = null,
    ): PipelineInterface {
        return new Pipeline($middlewares, $fallbackHandler ?? new EmptyPipelineHandler);
    }
}
