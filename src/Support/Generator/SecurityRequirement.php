<?php

namespace Dedoc\Scramble\Support\Generator;

class SecurityRequirement
{
    /**
     * @var array<string, string[]>
     */
    private array $items = [];

    public function __construct(array|string $items)
    {
        if (is_string($items)) { // keeping backward compatibility with synthetic Security object
            $this->items[$items] = [];
        } else {
            $this->items = $items;
        }
    }

    public function toArray()
    {
        return count($this->items) ? $this->items : (object) [];
    }
}

// To keep backward compatibility
class_alias(SecurityRequirement::class, 'Dedoc\Scramble\Support\Generator\Security');
