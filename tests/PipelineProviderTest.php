<?php

declare(strict_types=1);

use Componenta\Config\ConfigKey;
use Componenta\Http\Middleware\PipelineConfigProvider;
use Componenta\Http\Middleware\PipelineFactory;
use Componenta\Http\Middleware\PipelineFactoryInterface;

it('publishes a valid pipeline factory registration for the installed Config version', function (): void {
    $config = (new PipelineConfigProvider())();
    $dependencies = $config[ConfigKey::DEPENDENCIES];
    $invokables = $dependencies[ConfigKey::INVOKABLES];

    // Config 1 and 2 normalize keyed invokables into a class and alias.
    // Config 3 and 4 preserve the keyed invokable for DI normalization.
    if (isset($invokables[PipelineFactoryInterface::class])) {
        expect($invokables[PipelineFactoryInterface::class])->toBe(PipelineFactory::class);
    } else {
        expect($invokables)->toContain(PipelineFactory::class)
            ->and($dependencies[ConfigKey::ALIASES][PipelineFactoryInterface::class])->toBe(PipelineFactory::class);
    }
});
