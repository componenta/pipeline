# Componenta Pipeline

[![PHP](https://img.shields.io/badge/PHP-%5E8.4-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![PSR-15](https://img.shields.io/badge/PSR--15-compliant-green)](https://www.php-fig.org/psr/psr-15/)
[![License](https://img.shields.io/badge/license-MIT-blue)](https://opensource.org/licenses/MIT)

PSR-15 middleware pipeline for the Componenta framework.

## Installation

```bash
composer require componenta/pipeline
```

The package declares `Componenta\Http\Middleware\PipelineConfigProvider` in `extra.componenta.config-providers`.
When `componenta/composer-plugin` is installed, the provider is added to the generated provider list automatically.

## Related Packages

| Package | Why it matters here |
|---|---|
| `psr/http-server-middleware` | Defines `MiddlewareInterface` and `RequestHandlerInterface`. |
| `componenta/app-http` | Runs HTTP applications on top of the pipeline; concrete middleware and response emission live in separate HTTP packages. |
| `componenta/router` | Usually sits near the end of the HTTP pipeline. |
| `componenta/middleware-factory` | Creates middleware from strings, classes, groups, and callables. |

## Usage

### As a top-level handler

```php
use Componenta\Http\Middleware\Pipeline;

$pipeline = new Pipeline(
    [$errorMiddleware, $authMiddleware, $routerMiddleware],
    fallbackHandler: $notFoundHandler,
);

$response = $pipeline->handle($request);
```

If no `fallbackHandler` is supplied, `EmptyPipelineHandler` is installed and throws `RuntimeException` on invocation — this surfaces misconfiguration of an empty pipeline.

### Nested pipelines

A pipeline is itself a middleware, so pipelines compose:

```php
$api = new Pipeline([$apiAuth, $apiRouter]);
$web = new Pipeline([$sessionMiddleware, $webRouter]);

$app = new Pipeline([$errorMiddleware], $notFoundHandler)
    ->pipe($api)
    ->pipe($web);
```

### Via the factory

```php
$pipeline = $factory->createMiddlewarePipeline(
    [$auth, $router],
    $notFoundHandler,
);
```

## Behavior

- Middleware execute in registration order (FIFO).
- `pipe()` returns a new pipeline; the original is not modified.
- Returning a response from a middleware without calling the next handler halts the chain.
- Exceptions from middleware and terminal handlers propagate unchanged.

## Errors

| Condition | Exception |
|---|---|
| Non-middleware element in constructor / `pipe()` | `InvalidArgumentException` |
| Pipeline appended to itself via `pipe()` | `RuntimeException` |
| Pipeline passed as its own handler to `process()` | `RuntimeException` |
| Empty pipeline invoked without a custom fallback | `RuntimeException` |
