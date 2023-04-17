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

    public function addOperation(string $method, Operation $operationBuilder)
    {
        $this->operations[$method] = $operationBuilder;

        return $this;
    }

    public function toArray(OpenApi $openApi)
    {
        $result = [];

        foreach ($this->operations as $method => $operation) {
            $result[$method] = $operation->toArray($openApi);
        }

        if (count($this->servers)) {
            $servers = [];
            foreach ($this->servers as $server) {
                $servers[] = $server->toArray($openApi);
            }
            $result['servers'] = $servers;
        }

        return $result;
    }
}
