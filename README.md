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

If no `fallbackHandler` is supplied, `EmptyPipelineHandler` is installed and throws `RuntimeException` on invocation — this surfaces a chain that reaches its terminal without producing a response.

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
| Chain exhausted without a custom fallback | `RuntimeException` |

## Container integration

The configuration provider supports Config 4 alongside Config 1, 2 and 3. The provider is tested with Config 3 / DI 5 and Config 4 / DI 6. In a consumer with one of those stacks and the current package installed, verify provider wiring with:

```sh
php tests/config-consumer.php /path/to/consumer/vendor/autoload.php
```

## Development

Run `composer test`, `composer phpstan`, and `composer benchmark`. Benchmarks measure construction, cold and warm handling, and nested processing separately. Middleware instances are shared when deriving pipelines; only the ordered collection is immutable. Nested pipelines use the supplied continuation rather than their own fallback.
