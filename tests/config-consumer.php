<?php

declare(strict_types=1);

require $argv[1] ?? dirname(__DIR__) . '/vendor/autoload.php';

use Componenta\Config\ConfigFactory;
use Componenta\Config\ConfigKey;
use Componenta\Config\Environment;
use Componenta\DI\ContainerFactory;
use Componenta\Http\Middleware\PipelineConfigProvider;
use Componenta\Http\Middleware\Pipeline;
use Componenta\Http\Middleware\PipelineInterface;
use Componenta\Http\Middleware\PipelineFactoryInterface;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

$composition = (new ConfigFactory())->create(new Environment([]), new PipelineConfigProvider());
$container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
$factory = $container->get(PipelineFactoryInterface::class);
if (!$factory instanceof PipelineFactoryInterface) {
    throw new RuntimeException('Config must register the public pipeline factory.');
}
$middleware = new class implements MiddlewareInterface {
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request)->withHeader('X-Configured-Pipeline', 'yes');
    }
};
$fallback = new class implements RequestHandlerInterface {
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(201, [], $request->getUri()->getPath());
    }
};
$response = $factory->createMiddlewarePipeline([$middleware], $fallback)->handle(new ServerRequest('GET', '/configured'));
if ($response->getStatusCode() !== 201
    || $response->getHeaderLine('X-Configured-Pipeline') !== 'yes'
    || (string) $response->getBody() !== '/configured') {
    throw new RuntimeException('The configured factory must preserve middleware and fallback execution.');
}

echo 'Pipeline provider and container integration passed.', PHP_EOL;


// An application may provide a different factory through its public contract.
$custom = new class implements PipelineFactoryInterface {
    public int $calls = 0;

    public function createMiddlewarePipeline(
        iterable $middlewares = [],
        ?RequestHandlerInterface $fallbackHandler = null,
    ): PipelineInterface {
        ++$this->calls;
        return new Pipeline($middlewares, $fallbackHandler ?? new \Componenta\Http\Middleware\EmptyPipelineHandler());
    }
};
$composition = (new ConfigFactory())->create(
    new Environment([]),
    new PipelineConfigProvider(),
    static fn (): array => [
        ConfigKey::DEPENDENCIES => [
            ConfigKey::ALIASES => [PipelineFactoryInterface::class => 'application.pipeline.factory'],
            ConfigKey::SERVICES => ['application.pipeline.factory' => $custom],
        ],
    ],
);
$container = (new ContainerFactory())->create($composition->config, $composition->dependencies)->container;
$factory = $container->get(PipelineFactoryInterface::class);
if ($factory !== $custom) {
    throw new RuntimeException('An explicit application factory alias must override the default.');
}
$response = $factory->createMiddlewarePipeline([$middleware], $fallback)->handle(new ServerRequest('GET', '/custom'));
if ($custom->calls !== 1 || $response->getHeaderLine('X-Configured-Pipeline') !== 'yes'
    || (string) $response->getBody() !== '/custom') {
    throw new RuntimeException('The custom factory must preserve pipeline execution.');
}

echo 'Application pipeline factory override passed.', PHP_EOL;
