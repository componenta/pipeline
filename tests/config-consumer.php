<?php

declare(strict_types=1);

require $argv[1];

use Componenta\Config\ConfigFactory;
use Componenta\Config\Environment;
use Componenta\DI\ContainerFactory;
use Componenta\Http\Middleware\PipelineConfigProvider;
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

echo 'Config 4 + DI 6 pipeline provider passed.', PHP_EOL;
