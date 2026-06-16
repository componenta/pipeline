<?php

declare(strict_types=1);

use Componenta\Http\Middleware\PipelineFactory;
use Componenta\Http\Middleware\Tests\Fixture\RecordingHandler;
use Componenta\Http\Middleware\Tests\Fixture\RecordingMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;

describe('PipelineFactory::createMiddlewarePipeline()', function () {
    it('returns a pipeline that executes the provided middleware against the provided fallback', function () {
        $log = [];
        $terminal = new RecordingHandler(new Response());

        $pipeline = (new PipelineFactory())->createMiddlewarePipeline(
            [new RecordingMiddleware('a', $log)],
            $terminal,
        );

        $pipeline->handle(new ServerRequest('GET', '/'));

        expect($log)->toBe(['a'])
            ->and($terminal->calls)->toBe(1);
    });

    it('installs the empty-pipeline fallback when no fallback handler is provided', function () {
        $pipeline = (new PipelineFactory())->createMiddlewarePipeline();

        expect(fn () => $pipeline->handle(new ServerRequest('GET', '/')))
            ->toThrow(RuntimeException::class);
    });
});
