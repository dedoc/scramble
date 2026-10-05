<?php

namespace Dedoc\Scramble\Support\Generator\SecuritySchemes;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

class ApiKeySecurityScheme extends SecurityScheme
{
    public string $name;

    public string $in;

    public function __construct(string $in, string $name)
    {
        parent::__construct('apiKey');

        $this->in = $in;
        $this->name = $name;
    }

    public function serializeAs31(): mixed
    {
        return $this->serialize(parent::serializeAs31(), fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(parent::serializeAs32(), fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param callable(OpenApiSerializable): mixed $serializeItem
     */
    private function serialize(array $parentArray, callable $serializeItem): mixed
    {
        return array_merge($parentArray, [
            'in' => $this->in,
            'name' => $this->name,
        ]);
    }
}
