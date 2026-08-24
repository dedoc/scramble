<?php

namespace Dedoc\Scramble\Diagnostics;

class ClassContext
{
    public function __construct(
        public string $class,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => 'class:'.$this->class,
            'type' => 'class',
            'label' => class_basename($this->class),
            'method' => null,
            'detail' => null,
        ];
    }
}
