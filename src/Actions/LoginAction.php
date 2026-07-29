<?php

namespace Libinkk\OneAuth\Actions;

use Libinkk\OneAuth\Pipelines\LoginPipeline;

class LoginAction
{
    public function __construct(private LoginPipeline $pipeline)
    {
    }

    public function execute(array $payload): array
    {
        return $this->pipeline->handle($payload);
    }
}
