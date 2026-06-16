<?php

declare(strict_types=1);

use Componenta\Http\Middleware\ChainComposer;
use Componenta\Http\Middleware\Tests\Fixture\RecordingHandler;
use Componenta\Http\Middleware\Tests\Fixture\RecordingMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;

describe('ChainComposer::compose()', function () {
    it('returns the terminal handler unchanged when the middleware list is empty', function () {
        $terminal = new RecordingHandler(new Response());

        $chain = ChainComposer::compose([], $terminal);

        expect($chain)->toBe($terminal);
    });

    it('wraps middleware so the first one is outermost and executes in FIFO order', function () {
        $log = [];
        $terminal = new RecordingHandler(new Response());

        $chain = ChainComposer::compose(
            [
                new RecordingMiddleware('a', $log),
                new RecordingMiddleware('b', $log),
                new RecordingMiddleware('c', $log),
            ],
            $terminal,
        );

        $chain->handle(new ServerRequest('GET', '/'));

        expect($log)->toBe(['a', 'b', 'c'])
            ->and($terminal->calls)->toBe(1);
    });
});
