<?php

declare(strict_types=1);

use Componenta\Http\Middleware\EmptyPipelineHandler;
use Nyholm\Psr7\ServerRequest;

it('throws a runtime exception on any request - signalling pipeline misconfiguration', function () {
    $handler = new EmptyPipelineHandler();

    expect(fn () => $handler->handle(new ServerRequest('GET', '/')))
        ->toThrow(RuntimeException::class);
});
