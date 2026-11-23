<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\Event\FunctionCallEvent;
use Dedoc\Scramble\Infer\Extensions\FunctionReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Http\Request;

class RequestFunctionReturnTypeExtension implements FunctionReturnTypeExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === 'request';
    }

    public function getFunctionReturnType(FunctionCallEvent $event): ?Type
    {
        if (count($event->arguments) !== 0) {
            return null;
        }

        return (new ObjectType(Request::class))->mergeAttributes(['mayContainRequestInput' => true]);
    }
}
