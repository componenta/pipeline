# Componenta Pipeline

PSR-15 конвейер промежуточных обработчиков для Componenta Framework. Пакет выполняет список обработчиков по порядку и передает управление финальному обработчику, если ни один промежуточный обработчик не вернул ответ раньше.

## Установка

```bash
composer require componenta/pipeline
```

Пакет объявляет `Componenta\Http\Middleware\PipelineConfigProvider` в `extra.componenta.config-providers`.
Если установлен `componenta/composer-plugin`, провайдер автоматически добавляется в сгенерированный список провайдеров.

## Связанные пакеты

| Пакет | Зачем нужен здесь |
|---|---|
| `psr/http-server-middleware` | Определяет `MiddlewareInterface` и `RequestHandlerInterface`. |
| `componenta/app-http` | Запускает HTTP-приложения поверх конвейера; конкретные промежуточные обработчики и отправка ответа находятся в отдельных HTTP-пакетах. |
| `componenta/router` | Обычно стоит в конце конвейера и выбирает обработчик маршрута. |
| `componenta/middleware-factory` | Создает промежуточные обработчики из строк, классов, групп и callable-обработчиков. |

## Использование

### Как главный обработчик

```php
use Componenta\Http\Middleware\Pipeline;

$pipeline = new Pipeline(
    [$errorMiddleware, $authMiddleware, $routerMiddleware],
    fallbackHandler: $notFoundHandler,
);

$response = $pipeline->handle($request);
```

Если `fallbackHandler` не передан, устанавливается `EmptyPipelineHandler`, который бросает `RuntimeException` при вызове. Так пустой конвейер без финального обработчика сразу проявляет ошибку конфигурации.

### Вложенные конвейеры

`Pipeline` сам является промежуточным обработчиком, поэтому конвейеры можно вкладывать друг в друга:

```php
$api = new Pipeline([$apiAuth, $apiRouter]);
$web = new Pipeline([$sessionMiddleware, $webRouter]);

$app = new Pipeline([$errorMiddleware], $notFoundHandler)
    ->pipe($api)
    ->pipe($web);
```

### Через фабрику

```php
$pipeline = $factory->createMiddlewarePipeline(
    [$auth, $router],
    $notFoundHandler,
);
```

## Поведение

- Промежуточные обработчики выполняются в порядке регистрации.
- `pipe()` возвращает новый `Pipeline`; исходный объект не меняется.
- Если промежуточный обработчик возвращает ответ без вызова следующего обработчика, цепочка останавливается.
- Исключения из промежуточных обработчиков и финального обработчика пробрасываются без изменений.

## Ошибки

| Условие | Исключение |
|---|---|
| В конструктор или `pipe()` передан объект, не реализующий `MiddlewareInterface` | `InvalidArgumentException` |
| Pipeline добавлен сам в себя через `pipe()` | `RuntimeException` |
| Pipeline передан как собственный обработчик в `process()` | `RuntimeException` |
| Пустой pipeline вызван без собственного запасного обработчика | `RuntimeException` |
