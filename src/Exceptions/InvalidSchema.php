<?php

namespace Dedoc\Scramble\Exceptions;

use Exception;
use Illuminate\Routing\Route;

class InvalidSchema extends Exception implements RouteAware
{
    use RouteAwareTrait;

    public static function create(string $message, string $path)
    {
        return new self($path.': '.$message);
    }

    public function getRouteAwareMessage(Route $route, string $msg): string
    {
        $method = $route->methods()[0];
        $action = $route->getAction('uses');

        return "'$method $route->uri' ($action): ".$msg;
    }
}
