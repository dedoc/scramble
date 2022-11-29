<?php

namespace Dedoc\Scramble\Support\Generator;

class Path
{
    public string $path;

    /** @var array<string, Operation> */
    public array $operations = [];

    /** @var Server[] */
    public array $servers = [];

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public static function make(string $path)
    {
        return new self($path);
    }

    /**
     * @param  Server[]  $servers
     */
    public function servers(array $servers)
    {
        $this->servers = $servers;

        return $this;
    }

    public function addOperation(Operation $operationBuilder)
    {
        $this->operations[$operationBuilder->method] = $operationBuilder;

        return $this;
    }

    public function toArray()
    {
        $result = [];

        foreach ($this->operations as $method => $operation) {
            $result[$method] = $operation->toArray();
        }

        if (count($this->servers)) {
            $servers = [];
            foreach ($this->servers as $server) {
                $servers[] = $server->toArray();
            }
            $result['servers'] = $servers;
        }

        return $result;
    }
}
