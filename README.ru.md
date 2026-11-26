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

Если `fallbackHandler` не передан, устанавливается `EmptyPipelineHandler`, который бросает `RuntimeException` при вызове. Так обнаруживается исчерпание цепочки, если ни один обработчик не сформировал ответ.

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
| Цепочка исчерпана без собственного запасного обработчика | `RuntimeException` |

## Интеграция с контейнером

Провайдер конфигурации поддерживает Config 4 и прежние ветки Config 1, 2 и 3. Провайдер проверяется с Config 3 / DI 5 и Config 4 / DI 6. В приложении с одним из этих наборов зависимостей проверьте регистрацию сервисов командой:

```sh
php tests/config-consumer.php /path/to/consumer/vendor/autoload.php
```

## Разработка

Команды: `composer test`, `composer phpstan`, `composer benchmark`. Замеры отдельно охватывают создание, первый и повторные вызовы, вложенную обработку. Производные конвейеры используют те же объекты обработчиков; неизменяемой является только упорядоченная коллекция. Вложенный конвейер использует переданное продолжение, а не собственный запасной обработчик.
