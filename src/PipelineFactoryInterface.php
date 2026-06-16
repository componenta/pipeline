<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware;

use InvalidArgumentException;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Factory contract for {@see PipelineInterface} instances.
 */
interface PipelineFactoryInterface
{
    /**
     * Creates a new pipeline. When $fallbackHandler is null, implementations
     * install a default handler that signals misconfiguration of an empty
     * pipeline (see {@see EmptyPipelineHandler}).
     *
     * @param iterable<MiddlewareInterface> $middlewares
     *
     * @throws InvalidArgumentException If any element does not implement {@see MiddlewareInterface}.
     */
    public function createMiddlewarePipeline(
        iterable $middlewares = [],
        ?RequestHandlerInterface $fallbackHandler = null,
    ): PipelineInterface;
}
