<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware;

use InvalidArgumentException;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * Middleware pipeline contract.
 *
 * Combines PSR-15 {@see MiddlewareInterface} and {@see RequestHandlerInterface},
 * allowing a pipeline to act as a top-level handler (via {@see handle()}) or as
 * nested middleware inside another pipeline (via {@see process()}).
 */
interface PipelineInterface extends MiddlewareInterface, RequestHandlerInterface
{
    /**
     * Returns a new pipeline with the given middleware appended at the tail.
     * The original pipeline is not modified.
     *
     * @param iterable<MiddlewareInterface>|MiddlewareInterface $middlewares
     *
     * @throws InvalidArgumentException If any element does not implement {@see MiddlewareInterface}.
     * @throws RuntimeException If the pipeline is appended to itself.
     */
    public function pipe(iterable|MiddlewareInterface $middlewares): PipelineInterface;
}
