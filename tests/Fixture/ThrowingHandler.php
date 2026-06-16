<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Tests\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Terminal handler that fails the test if invoked. Used to assert a handler
 * is unreachable under a given scenario (e.g. short-circuiting middleware).
 */
final class ThrowingHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new \LogicException('ThrowingHandler was invoked but should not have been');
    }
}
