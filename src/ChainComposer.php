<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Folds a middleware list into a {@see RequestHandlerInterface} chain rooted
 * at the given terminal handler.
 *
 * Builds the chain right-to-left so that the first middleware becomes the
 * outermost wrapper (FIFO execution). An empty list returns the terminal
 * unchanged.
 *
 * @internal
 */
final class ChainComposer
{
    /**
     * @param list<MiddlewareInterface> $middlewares
     */
    public static function compose(
        array $middlewares,
        RequestHandlerInterface $terminal,
    ): RequestHandlerInterface {
        $handler = $terminal;

        for ($i = count($middlewares) - 1; $i >= 0; $i--) {
            $handler = new MiddlewareHandler($middlewares[$i], $handler);
        }

        return $handler;
    }
}
