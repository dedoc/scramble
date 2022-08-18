<?php

namespace Dedoc\ApiDocs;

class ApiDocs
{
    public static $operationResolver;

    public static function resolveOperationUsing(callable $operationResolver)
    {
        static::$operationResolver = $operationResolver;
    }
}
