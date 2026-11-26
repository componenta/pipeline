<?php

declare(strict_types=1);

namespace Componenta\Http\Middleware;

/**
 * Registers the default {@see PipelineFactory} against
 * {@see PipelineFactoryInterface}.
 */
final class PipelineConfigProvider extends \Componenta\Config\ConfigProvider
{
    /** @return array<class-string, class-string> */
    protected function getInvokables(): array
    {
        return [PipelineFactoryInterface::class => PipelineFactory::class];
    }
}
