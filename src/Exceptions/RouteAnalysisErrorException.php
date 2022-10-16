<?php

namespace Dedoc\Scramble\Exceptions;

use Exception;
use Illuminate\Routing\Route;
use Throwable;

class RouteAnalysisErrorException extends Exception
{
    public static function make(Route $route, Throwable $e)
    {
        $method = $route->methods()[0];
        $action = $route->getAction('uses');

        return new static(
            "Error when analyzing route '$method $route->uri' ($action): {$e->getMessage()} – ".($e->getFile().' on line '.$e->getLine()),
            0,
            $e,
        );
    }
}
