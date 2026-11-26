<?php

declare(strict_types=1);

use Componenta\Http\Middleware\Pipeline;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

it('does not retain completed requests or externally supplied continuations', function (bool $external, bool $fail): void {
    $middleware = new class implements MiddlewareInterface {
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
        {
            return $handler->handle($request);
        }
    };
    $handler = static fn (): RequestHandlerInterface => new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            if ($request->getUri()->getPath() === '/fail') {
                throw new DomainException('request failed');
            }

            return new Response(200, [], $request->getUri()->getPath());
        }
    };
    $pipeline = new Pipeline([$middleware], $handler());
    $pipeline->handle(new ServerRequest('GET', '/warm'));

    $terminal = $handler();
    $request = new ServerRequest('GET', $fail ? '/fail' : '/ok');
    $requestReference = WeakReference::create($request);
    $terminalReference = WeakReference::create($terminal);
    $caught = false;
    try {
        $response = $external
            ? $pipeline->process($request, $terminal)
            : $pipeline->handle($request);
        expect((string) $response->getBody())->toBe('/ok');
        unset($response);
    } catch (DomainException) {
        $caught = true;
    }

    expect($caught)->toBe($fail);
    unset($request, $terminal);
    gc_collect_cycles();

    expect($requestReference->get())->toBeNull()
        ->and($terminalReference->get())->toBeNull()
        ->and((string) $pipeline->handle(new ServerRequest('GET', '/after'))->getBody())->toBe('/after');
})->with([
    'warm handle success' => [false, false],
    'warm handle failure' => [false, true],
    'process success' => [true, false],
    'process failure' => [true, true],
]);

it('preserves outer continuations during reentrant request handling', function (): void {
    $events = [];
    $pipeline = null;
    $outer = new class($pipeline, $events) implements MiddlewareInterface {
        private mixed $pipeline;
        private array $events;
        public function __construct(mixed &$pipeline, array &$events)
        {
            $this->pipeline = &$pipeline;
            $this->events = &$events;
        }
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
        {
            $path = $request->getUri()->getPath();
            $this->events[] = 'before:' . $path;
            if ($path === '/outer') {
                $nested = $this->pipeline->handle(new ServerRequest('GET', '/inner'));
                $this->events[] = 'nested:' . (string) $nested->getBody();
            }
            $response = $handler->handle($request);
            $this->events[] = 'after:' . $path;

            return $response;
        }
    };
    $terminal = new class implements RequestHandlerInterface {
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response(200, [], $request->getUri()->getPath());
        }
    };
    $pipeline = new Pipeline([$outer], $terminal);

    expect((string) $pipeline->handle(new ServerRequest('GET', '/outer'))->getBody())->toBe('/outer')
        ->and($events)->toBe(['before:/outer', 'before:/inner', 'after:/inner', 'nested:/inner', 'after:/outer']);
});
