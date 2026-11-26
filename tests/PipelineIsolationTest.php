<?php

declare(strict_types=1);

use Componenta\Http\Middleware\Pipeline;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

it('keeps independently suspended requests and continuations isolated', function () {
    $middleware = new class implements MiddlewareInterface {
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
        {
            Fiber::suspend($request->getUri()->getPath());
            return $handler->handle($request);
        }
    };
    $terminal = static fn (int $status): RequestHandlerInterface => new class($status) implements RequestHandlerInterface {
        public function __construct(private int $status) {}
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response($this->status, [], $request->getUri()->getPath());
        }
    };
    $pipeline = new Pipeline([$middleware]);
    $first = new Fiber(fn () => $pipeline->process(new ServerRequest('GET', '/first'), $terminal(201)));
    $second = new Fiber(fn () => $pipeline->process(new ServerRequest('GET', '/second'), $terminal(202)));

    expect($first->start())->toBe('/first')->and($second->start())->toBe('/second');
    $second->resume();
    $first->resume();
    expect((string) $first->getReturn()->getBody())->toBe('/first')
        ->and($first->getReturn()->getStatusCode())->toBe(201)
        ->and((string) $second->getReturn()->getBody())->toBe('/second')
        ->and($second->getReturn()->getStatusCode())->toBe(202);
});

it('executes the same continuation twice on both cold and warm paths', function () {
    $terminal = new class implements RequestHandlerInterface {
        public int $calls = 0;
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            ++$this->calls;
            return new Response(200, [], $request->getUri()->getPath());
        }
    };
    $middleware = new class implements MiddlewareInterface {
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
        {
            $handler->handle($request);
            return $handler->handle($request);
        }
    };
    $pipeline = new Pipeline([$middleware], $terminal);
    foreach (['/cold', '/warm'] as $path) {
        expect((string) $pipeline->handle(new ServerRequest('GET', $path))->getBody())->toBe($path);
    }
    expect($terminal->calls)->toBe(4);
});

it('recovers after a terminal exception without changing its identity', function () {
    $failure = new DomainException('failure');
    $terminal = new class($failure) implements RequestHandlerInterface {
        public function __construct(private DomainException $failure) {}
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            if ($request->getUri()->getPath() === '/fail') {
                throw $this->failure;
            }
            return new Response(204);
        }
    };
    $pipeline = new Pipeline([], $terminal);
    $caught = null;
    try {
        $pipeline->handle(new ServerRequest('GET', '/fail'));
    } catch (DomainException $e) {
        $caught = $e;
    }
    expect($caught)->toBe($failure)
        ->and($pipeline->handle(new ServerRequest('GET', '/ok'))->getStatusCode())->toBe(204);
});
