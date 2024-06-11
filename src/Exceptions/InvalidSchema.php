<?php

namespace Dedoc\Scramble\Exceptions;

use Exception;

class InvalidSchema extends Exception
{
    public static function create(string $message, string $path)
    {
        return new self($path.': '.$message);
    }
}
