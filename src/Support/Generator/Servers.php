<?php

namespace Dedoc\ApiDocs\Support\Generator;

class Servers
{
    /** @var array */
    private array $items;

    public function add(Server $server)
    {
        $this->items[] = $server;
    }

    public function toArray()
    {
        return array_map(
            fn (Server $s) => $s->toArray(),
            $this->items,
        );
    }
}
