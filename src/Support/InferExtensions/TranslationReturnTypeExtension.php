<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\FunctionCallEvent;
use Dedoc\Scramble\Infer\Extensions\FunctionReturnTypeExtension;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\TranslatedStringType;
use Dedoc\Scramble\Support\Type\Type;

class TranslationReturnTypeExtension implements FunctionReturnTypeExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === '__';
    }

    public function getFunctionReturnType(FunctionCallEvent $event): ?Type
    {
        if (count($event->arguments) === 0) {
            return null;
        }

        if (count($event->arguments) >= 2) {
            return new StringType;
        }

        return new TranslatedStringType($event->getArg('key', 0));
    }
}
