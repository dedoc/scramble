<?php

namespace Dedoc\Scramble\Exceptions;

use Exception;

class InvalidSchema extends Exception implements RouteAware
{
    use RouteAwareTrait;

    public ?string $jsonPointer = null;

    public string $originalMessage = '';

    public ?string $originFile = null;

    public ?int $originLine = null;

    public static function createForSchema(string $message, string $path, ?string $file, ?int $line): static
    {
        $originalMessage = $message;
        if ($file) {
            $message = rtrim($message, '.').'. Got when analyzing an expression in file ['.$file.'] on line '.$line;
        }

        $exception = new static($path.': '.$message);

        $exception->originalMessage = $originalMessage;
        $exception->originFile = $file;
        $exception->originLine = $line;
        $exception->jsonPointer = $path;

        return $exception;
    }
}
