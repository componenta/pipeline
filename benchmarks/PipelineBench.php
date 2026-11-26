<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware\Benchmarks;

use Componenta\Http\Middleware\Pipeline;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PhpBench\Attributes as Bench;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Bench\BeforeMethods('setUp')]
#[Bench\ParamProviders('provideSizes')]
#[Bench\Revs(1000)]
#[Bench\Iterations(5)]
final class PipelineBench
{
    /** @var list<MiddlewareInterface> */
    private array $middlewares;
    private RequestHandlerInterface $terminal;
    private ServerRequestInterface $request;
    private Pipeline $warm;

    /** @return iterable<string, array{size: int}> */
    public function provideSizes(): iterable
    {
        foreach ([0, 1, 10, 50] as $size) {
            yield (string) $size => ['size' => $size];
        }
    }

    /** @param array{size: int} $params */
    public function setUp(array $params): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };
        $this->middlewares = array_fill(0, $params['size'], $middleware);
        $this->terminal = new class implements RequestHandlerInterface {
            private ResponseInterface $response;
            public function __construct() { $this->response = new Response(204); }
            public function handle(ServerRequestInterface $request): ResponseInterface { return $this->response; }
        };
        $this->request = new ServerRequest('GET', '/');
        $this->warm = new Pipeline($this->middlewares, $this->terminal);
        $this->warm->handle($this->request);
    }

    public function benchConstruct(): void
    {
        new Pipeline($this->middlewares, $this->terminal);
    }

    public function benchColdHandle(): void
    {
        (new Pipeline($this->middlewares, $this->terminal))->handle($this->request);
    }

    public function benchWarmHandle(): void
    {
        $this->warm->handle($this->request);
    }

    public function benchProcess(): void
    {
        $this->warm->process($this->request, $this->terminal);
    }

    public function benchPipeBatch(): void
    {
        $this->warm->pipe($this->middlewares);
    }
}
