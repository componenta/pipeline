<?php

declare(strict_types=1);

use Componenta\Http\Middleware\Pipeline;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

it('isolates suspended cold and warm handle calls and preserves onion ordering', function (): void {
    $events = [];
    $outer = new class($events) implements MiddlewareInterface {
        private array $events;
        public function __construct(array &$events) { $this->events = &$events; }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
        {
            $path = $request->getUri()->getPath();
            $this->events[] = 'outer:before:' . $path;
            Fiber::suspend($path);
            $result = $handler->handle($request);
            $this->events[] = 'outer:after:' . $path;
            return $result->withAddedHeader('X-Layer', 'outer');
        }
    };
    $inner = new class($events) implements MiddlewareInterface {
        private array $events;
        public function __construct(array &$events) { $this->events = &$events; }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
        {
            $path = $request->getUri()->getPath();
            $this->events[] = 'inner:before:' . $path;
            $result = $handler->handle($request);
            $this->events[] = 'inner:after:' . $path;
            return $result->withAddedHeader('X-Layer', 'inner');
        }
    };
    $terminal = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response(200, [], $request->getUri()->getPath());
        }
    };
    $pipeline = new Pipeline([$outer, $inner], $terminal);
    $cold = new Fiber(fn () => $pipeline->handle(new ServerRequest('GET', '/cold')));
    $warm = new Fiber(fn () => $pipeline->handle(new ServerRequest('GET', '/warm')));

    expect($cold->start())->toBe('/cold')->and($warm->start())->toBe('/warm');
    $warm->resume();
    $cold->resume();
    expect($events)->toBe([
        'outer:before:/cold', 'outer:before:/warm',
        'inner:before:/warm', 'inner:after:/warm', 'outer:after:/warm',
        'inner:before:/cold', 'inner:after:/cold', 'outer:after:/cold',
    ]);
    foreach (['/cold' => $cold, '/warm' => $warm] as $path => $fiber) {
        expect((string) $fiber->getReturn()->getBody())->toBe($path)
            ->and($fiber->getReturn()->getHeader('X-Layer'))->toBe(['inner', 'outer']);
    }
});

it('cannot mutate an existing immutable pipeline through a rejected constructor call', function (bool $warm): void {
    $terminal = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface { return new Response(200); }
    };
    $added = new class implements MiddlewareInterface {
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
        {
            return new Response(403);
        }
    };
    $pipeline = new Pipeline([], $terminal);
    $request = new ServerRequest('GET', '/');
    if ($warm) {
        expect($pipeline->handle($request)->getStatusCode())->toBe(200);
    }

    expect(fn () => $pipeline->__construct([$added], $terminal))->toThrow(Error::class)
        ->and($pipeline->handle($request)->getStatusCode())->toBe(200)
        ->and($pipeline->process($request, $terminal)->getStatusCode())->toBe(200);
})->with(['cold' => [false], 'warm' => [true]]);
