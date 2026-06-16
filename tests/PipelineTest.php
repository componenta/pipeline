<?php

declare(strict_types=1);

use Componenta\Http\Middleware\Pipeline;
use Componenta\Http\Middleware\Tests\Fixture\RecordingHandler;
use Componenta\Http\Middleware\Tests\Fixture\RecordingMiddleware;
use Componenta\Http\Middleware\Tests\Fixture\RequestRewritingMiddleware;
use Componenta\Http\Middleware\Tests\Fixture\ResponseRewritingMiddleware;
use Componenta\Http\Middleware\Tests\Fixture\ThrowingHandler;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

describe('Pipeline::__construct()', function () {
    it('rejects a non-middleware element and reports its position', function () {
        expect(fn () => new Pipeline(['not-a-middleware']))
            ->toThrow(InvalidArgumentException::class, 'at position 0');
    });

    it('reports the correct position when the invalid element is not first', function () {
        $log = [];
        $valid = new RecordingMiddleware('a', $log);

        expect(fn () => new Pipeline([$valid, 42]))
            ->toThrow(InvalidArgumentException::class, 'at position 1');
    });

    it('includes the offending type in the error message', function () {
        expect(fn () => new Pipeline([new stdClass()]))
            ->toThrow(InvalidArgumentException::class, 'stdClass');
    });
});

describe('Pipeline::handle()', function () {
    it('delegates directly to the fallback handler when no middleware are configured', function () {
        $terminal = new RecordingHandler(new Response());

        $pipeline = new Pipeline([], $terminal);
        $pipeline->handle(new ServerRequest('GET', '/'));

        expect($terminal->calls)->toBe(1);
    });

    it('executes middleware in registration order and terminates on the fallback handler', function () {
        $log = [];
        $terminal = new RecordingHandler(new Response());

        $pipeline = new Pipeline([
            new RecordingMiddleware('a', $log),
            new RecordingMiddleware('b', $log),
            new RecordingMiddleware('c', $log),
        ], $terminal);

        $pipeline->handle(new ServerRequest('GET', '/'));

        expect($log)->toBe(['a', 'b', 'c'])
            ->and($terminal->calls)->toBe(1);
    });

    it('returns the response produced by the chain', function () {
        $expected = new Response();
        $pipeline = new Pipeline([], new RecordingHandler($expected));

        $actual = $pipeline->handle(new ServerRequest('GET', '/'));

        expect($actual)->toBe($expected);
    });

    it('stops executing subsequent middleware and the fallback when one short-circuits', function () {
        $log = [];
        $shortCircuitResponse = new Response();

        $pipeline = new Pipeline([
            new RecordingMiddleware('a', $log),
            new RecordingMiddleware('b', $log, shortCircuit: $shortCircuitResponse),
            new RecordingMiddleware('c', $log),
        ], new ThrowingHandler());

        $result = $pipeline->handle(new ServerRequest('GET', '/'));

        expect($log)->toBe(['a', 'b'])
            ->and($result)->toBe($shortCircuitResponse);
    });

    it('propagates a request replaced by middleware to downstream handlers', function () {
        $replacement = new ServerRequest('POST', '/replaced');
        $terminal = new RecordingHandler(new Response());

        $pipeline = new Pipeline(
            [new RequestRewritingMiddleware($replacement)],
            $terminal,
        );

        $pipeline->handle(new ServerRequest('GET', '/'));

        expect($terminal->lastRequest)->toBe($replacement);
    });

    it('returns a response replaced by middleware after the terminal handler ran', function () {
        $replacement = new Response();
        $terminal = new RecordingHandler(new Response());

        $pipeline = new Pipeline(
            [new ResponseRewritingMiddleware($replacement)],
            $terminal,
        );

        $result = $pipeline->handle(new ServerRequest('GET', '/'));

        expect($result)->toBe($replacement)
            ->and($terminal->calls)->toBe(1);
    });

    it('throws via the default EmptyPipelineHandler when no middleware and no fallback are configured', function () {
        $pipeline = new Pipeline();

        expect(fn () => $pipeline->handle(new ServerRequest('GET', '/')))
            ->toThrow(RuntimeException::class);
    });

    it('propagates exceptions thrown by middleware to the caller', function () {
        $boom = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                throw new DomainException('middleware blew up');
            }
        };

        $pipeline = new Pipeline([$boom], new RecordingHandler(new Response()));

        expect(fn () => $pipeline->handle(new ServerRequest('GET', '/')))
            ->toThrow(DomainException::class, 'middleware blew up');
    });

    it('produces consistent results across multiple invocations', function () {
        $log = [];
        $terminal = new RecordingHandler(new Response());

        $pipeline = new Pipeline([
            new RecordingMiddleware('a', $log),
            new RecordingMiddleware('b', $log),
        ], $terminal);

        $pipeline->handle(new ServerRequest('GET', '/'));
        $pipeline->handle(new ServerRequest('GET', '/'));

        expect($log)->toBe(['a', 'b', 'a', 'b'])
            ->and($terminal->calls)->toBe(2);
    });
});

describe('Pipeline::process()', function () {
    it('uses the externally provided handler as the chain terminal', function () {
        $log = [];
        $externalTerminal = new RecordingHandler(new Response());

        $pipeline = new Pipeline(
            [new RecordingMiddleware('a', $log)],
            new ThrowingHandler(),
        );

        $pipeline->process(new ServerRequest('GET', '/'), $externalTerminal);

        expect($log)->toBe(['a'])
            ->and($externalTerminal->calls)->toBe(1);
    });

    it('forwards the request to the external handler when no middleware are configured', function () {
        $externalTerminal = new RecordingHandler(new Response());

        $pipeline = new Pipeline([], new ThrowingHandler());
        $pipeline->process(new ServerRequest('GET', '/'), $externalTerminal);

        expect($externalTerminal->calls)->toBe(1);
    });

    it('rejects using the pipeline as its own handler', function () {
        $pipeline = new Pipeline();

        expect(fn () => $pipeline->process(new ServerRequest('GET', '/'), $pipeline))
            ->toThrow(RuntimeException::class, 'own handler');
    });
});

describe('Pipeline::pipe()', function () {
    it('returns a new pipeline instance rather than mutating and returning self', function () {
        $log = [];
        $original = new Pipeline([], new RecordingHandler(new Response()));
        $extended = $original->pipe(new RecordingMiddleware('a', $log));

        expect($extended)->not->toBe($original);
    });

    it('leaves the original pipeline unchanged when a middleware is appended', function () {
        $log = [];
        $terminal = new RecordingHandler(new Response());

        $original = new Pipeline([new RecordingMiddleware('a', $log)], $terminal);
        $original->pipe(new RecordingMiddleware('b', $log));

        $original->handle(new ServerRequest('GET', '/'));

        expect($log)->toBe(['a']);
    });

    it('appends middleware at the tail preserving FIFO order', function () {
        $log = [];
        $terminal = new RecordingHandler(new Response());

        $pipeline = (new Pipeline([new RecordingMiddleware('a', $log)], $terminal))
            ->pipe(new RecordingMiddleware('b', $log))
            ->pipe([
                new RecordingMiddleware('c', $log),
                new RecordingMiddleware('d', $log),
            ]);

        $pipeline->handle(new ServerRequest('GET', '/'));

        expect($log)->toBe(['a', 'b', 'c', 'd']);
    });

    it('accepts a single middleware', function () {
        $log = [];
        $terminal = new RecordingHandler(new Response());

        $pipeline = (new Pipeline([], $terminal))
            ->pipe(new RecordingMiddleware('only', $log));

        $pipeline->handle(new ServerRequest('GET', '/'));

        expect($log)->toBe(['only']);
    });

    it('accepts an iterable of middleware', function () {
        $log = [];
        $terminal = new RecordingHandler(new Response());

        $pipeline = (new Pipeline([], $terminal))
            ->pipe([
                new RecordingMiddleware('a', $log),
                new RecordingMiddleware('b', $log),
            ]);

        $pipeline->handle(new ServerRequest('GET', '/'));

        expect($log)->toBe(['a', 'b']);
    });

    it('rejects a non-middleware element in the appended iterable', function () {
        $pipeline = new Pipeline();

        expect(fn () => $pipeline->pipe(['bad']))
            ->toThrow(InvalidArgumentException::class, 'at position 0');
    });

    it('rejects appending the pipeline to itself as a single middleware', function () {
        $pipeline = new Pipeline();

        expect(fn () => $pipeline->pipe($pipeline))
            ->toThrow(RuntimeException::class, 'itself');
    });

    it('rejects appending the pipeline to itself within an iterable', function () {
        $pipeline = new Pipeline();

        expect(fn () => $pipeline->pipe([$pipeline]))
            ->toThrow(RuntimeException::class, 'itself');
    });

    it('keeps the original chain intact while the derived pipeline uses the extended one', function () {
        // Covers both the immutability guarantee and cache invalidation:
        // exercising handle() on the original before deriving must not leak
        // its composed chain into the derived pipeline.
        $log = [];
        $terminal = new RecordingHandler(new Response());

        $original = new Pipeline([new RecordingMiddleware('a', $log)], $terminal);

        $original->handle(new ServerRequest('GET', '/'));

        $extended = $original->pipe(new RecordingMiddleware('b', $log));
        $extended->handle(new ServerRequest('GET', '/'));
        $original->handle(new ServerRequest('GET', '/'));

        expect($log)->toBe(['a', 'a', 'b', 'a']);
    });
});

describe('Pipeline as nested middleware (PSR-15 composition)', function () {
    it('runs an inner pipeline as middleware inside an outer pipeline', function () {
        $log = [];
        $terminal = new RecordingHandler(new Response());

        $inner = new Pipeline([
            new RecordingMiddleware('inner-a', $log),
            new RecordingMiddleware('inner-b', $log),
        ]);

        $outer = new Pipeline([
            new RecordingMiddleware('outer', $log),
            $inner,
        ], $terminal);

        $outer->handle(new ServerRequest('GET', '/'));

        expect($log)->toBe(['outer', 'inner-a', 'inner-b'])
            ->and($terminal->calls)->toBe(1);
    });

    it('allows an inner pipeline to short-circuit before reaching the outer terminal', function () {
        $log = [];
        $shortCircuitResponse = new Response();

        $inner = new Pipeline([
            new RecordingMiddleware('inner', $log, shortCircuit: $shortCircuitResponse),
        ]);

        $outer = new Pipeline([$inner], new ThrowingHandler());

        $result = $outer->handle(new ServerRequest('GET', '/'));

        expect($log)->toBe(['inner'])
            ->and($result)->toBe($shortCircuitResponse);
    });
});
