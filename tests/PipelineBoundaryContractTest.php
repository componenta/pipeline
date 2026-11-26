<?php

declare(strict_types=1);

use Componenta\Http\Middleware\NotFoundHandler;
use Componenta\Http\Middleware\Pipeline;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

it('keeps the warmed fallback and nested continuation independent', function (): void {
    $layer = new class implements MiddlewareInterface {
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
        {
            return $handler->handle($request)->withAddedHeader('X-Layer', 'inner');
        }
    };
    $terminal = static fn (int $status): RequestHandlerInterface => new class($status) implements RequestHandlerInterface {
        public function __construct(private int $status) {}
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response($this->status, [], $request->getUri()->getPath());
        }
    };
    $inner = new Pipeline([$layer], $terminal(201));
    $outer = new Pipeline([$inner], $terminal(202));

    foreach ([
        [$inner, '/first', 201],
        [$outer, '/nested', 202],
        [$inner, '/last', 201],
        [$outer, '/nested-again', 202],
    ] as [$pipeline, $path, $status]) {
        $response = $pipeline->handle(new ServerRequest('GET', $path));
        expect($response->getStatusCode())->toBe($status)
            ->and((string) $response->getBody())->toBe($path)
            ->and($response->getHeader('X-Layer'))->toBe(['inner']);
    }
});

it('does not traverse a middleware instance that is also iterable when piping it', function (): void {
    $middleware = new class implements MiddlewareInterface, IteratorAggregate {
        public function getIterator(): Traversable
        {
            throw new LogicException('The middleware definition is not an iterable collection.');
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
        {
            return new Response(201);
        }
    };

    expect((new Pipeline())->pipe($middleware)->handle(new ServerRequest('GET', '/'))->getStatusCode())->toBe(201);
});

it('preserves the warmed pipeline after iteration fails while deriving another pipeline', function (): void {
    $terminal = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response(201);
        }
    };
    $middleware = new class implements MiddlewareInterface {
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
        {
            return new Response(403);
        }
    };
    $pipeline = new Pipeline([], $terminal);
    $request = new ServerRequest('GET', '/');
    expect($pipeline->handle($request)->getStatusCode())->toBe(201);

    $failure = new DomainException('iteration failed');
    $items = (static function () use ($middleware, $failure): Generator {
        yield $middleware;
        throw $failure;
    })();
    $caught = null;
    try {
        $pipeline->pipe($items);
    } catch (DomainException $e) {
        $caught = $e;
    }

    expect($caught)->toBe($failure)
        ->and($pipeline->handle($request)->getStatusCode())->toBe(201)
        ->and($pipeline->process($request, $terminal)->getStatusCode())->toBe(201);
});

it('creates independent not-found response bodies for repeated requests', function (): void {
    $handler = new NotFoundHandler(new Psr17Factory());
    $first = $handler->handle(new ServerRequest('GET', '/first'));
    $first->getBody()->write('modified by caller');
    $second = $handler->handle(new ServerRequest('GET', '/second'));

    expect($second->getStatusCode())->toBe(404)
        ->and($second->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and(json_decode((string) $second->getBody(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe(['status' => 404, 'message' => 'Not Found'])
        ->and($second->getBody())->not->toBe($first->getBody());
});
