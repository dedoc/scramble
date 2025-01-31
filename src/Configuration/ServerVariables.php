<?php

namespace Dedoc\Scramble\Configuration;

use Dedoc\Scramble\Support\Generator\ServerVariable;

class ServerVariables
{
    /**
     * @param  array<string, ServerVariable>  $items
     */
    public function __construct(public array $items = []) {}

    public function use(array $items)
    {
        $this->items = $items;

        return $this;
    }

    public function all()
    {
        return $this->items;
    }

    public function set(string $name, ServerVariable $serverVariable)
    {
        $this->items[$name] = $serverVariable;

        return $this;
    }
}
