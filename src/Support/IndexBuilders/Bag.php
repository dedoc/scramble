<?php

namespace Dedoc\Scramble\Support\IndexBuilders;

class Bag
{
    public function __construct(
        public array $data = []
    ) {}

    public function set(string $key, $value)
    {
        $this->data[$key] = $value;

        return $this;
    }
}
