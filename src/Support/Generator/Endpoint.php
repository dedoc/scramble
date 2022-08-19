<?php

namespace Dedoc\Scramble\Support\Generator;

class Endpoint
{
    public ?string $method = null;

    public ?string $path = null;

    public ?string $operationId = null;

    public array $tags = [];

    public array $parameters = [];

    public ?array $request = [];

    public ?array $response = null;
}
