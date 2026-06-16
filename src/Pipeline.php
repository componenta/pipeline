<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * PSR-15 middleware pipeline.
 *
 * Immutable, ordered collection of middleware that process a request in FIFO
 * order. Usable as a top-level {@see RequestHandlerInterface} via
 * {@see handle()} or as a {@see MiddlewareInterface} inside another pipeline
 * via {@see process()}.
 *
 * The chain is pre-composed into {@see MiddlewareHandler} instances on first
 * {@see handle()} call and reused across subsequent invocations; {@see pipe()}
 * returns a new instance and invalidates the composition cache on the clone.
 */
final class Pipeline implements PipelineInterface
{
    /** @var list<MiddlewareInterface> */
    private array $middlewares = [];

    private readonly RequestHandlerInterface $fallbackHandler;

    /**
     * Memoized fallback-rooted chain for {@see handle()}.
     */
    private ?RequestHandlerInterface $composed = null;

    /**
     * @param iterable<MiddlewareInterface> $middlewares
     *
     * @throws InvalidArgumentException If any element does not implement {@see MiddlewareInterface}.
     */
    public function __construct(
        iterable $middlewares = [],
        RequestHandlerInterface $fallbackHandler = new EmptyPipelineHandler,
    ) {
        foreach ($middlewares as $position => $middleware) {
            $this->validateMiddleware($middleware, $position);
            $this->middlewares[] = $middleware;
        }

        $this->fallbackHandler = $fallbackHandler;
    }

    /**
     * Returns a new pipeline with the given middleware appended at the tail.
     * The original instance is not modified.
     *
     * @param iterable<MiddlewareInterface>|MiddlewareInterface $middlewares
     *
     * @throws InvalidArgumentException If any element does not implement {@see MiddlewareInterface}.
     * @throws RuntimeException If the pipeline is appended to itself.
     */
    public function pipe(iterable|MiddlewareInterface $middlewares): PipelineInterface
    {
        $copy = clone $this;
        $copy->composed = null;

        $items = $middlewares instanceof MiddlewareInterface ? [$middlewares] : $middlewares;

        foreach ($items as $position => $middleware) {
            $this->validateMiddleware($middleware, $position);
            $copy->middlewares[] = $middleware;
        }

        return $copy;
    }

    /**
     * Executes the chain with the provided handler as its terminal.
     *
     * The chain is composed per call because the terminal varies between
     * invocations and cannot be memoized.
     *
     * @throws RuntimeException If the pipeline is passed as its own handler.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        if ($handler === $this) {
            throw new RuntimeException('Cannot use pipeline as its own handler.');
        }

        return ChainComposer::compose($this->middlewares, $handler)->handle($request);
    }

    /**
     * Executes the chain with the configured fallback handler as its terminal.
     * The composed chain is memoized on first call.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->composed ??= ChainComposer::compose(
            $this->middlewares,
            $this->fallbackHandler,
        ))->handle($request);
    }

    /**
     * @throws InvalidArgumentException If $middleware does not implement {@see MiddlewareInterface}.
     * @throws RuntimeException If $middleware is the pipeline itself.
     */
    private function validateMiddleware(
        mixed $middleware,
        string|int|null $position = null,
    ): void {
        if (!$middleware instanceof MiddlewareInterface) {
            $type = get_debug_type($middleware);

            throw new InvalidArgumentException(
                $position !== null
                    ? "Middleware at position $position must implement " . MiddlewareInterface::class . ", $type given"
                    : "Middleware must implement " . MiddlewareInterface::class . ", $type given"
            );
        }

        if ($middleware === $this) {
            throw new RuntimeException('Cannot add pipeline to itself.');
        }
    }
}
