<?php

namespace Dedoc\Scramble;

use Dedoc\Scramble\Infer\Extensions\ExpressionExceptionExtension;
use Dedoc\Scramble\Infer\Extensions\ExpressionTypeInferExtension;
use Dedoc\Scramble\Infer\Handler\ArrayHandler;
use Dedoc\Scramble\Infer\Handler\ArrayItemHandler;
use Dedoc\Scramble\Infer\Handler\AssignHandler;
use Dedoc\Scramble\Infer\Handler\ClassHandler;
use Dedoc\Scramble\Infer\Handler\ExceptionInferringExtensions;
use Dedoc\Scramble\Infer\Handler\ExpressionTypeInferringExtensions;
use Dedoc\Scramble\Infer\Handler\FunctionLikeHandler;
use Dedoc\Scramble\Infer\Handler\PropertyHandler;
use Dedoc\Scramble\Infer\Handler\ReturnHandler;
use Dedoc\Scramble\Infer\Handler\ThrowHandler;
use Dedoc\Scramble\Support\InferExtensions\AbortHelpersExceptionInfer;
use Dedoc\Scramble\Support\InferExtensions\JsonResourceCallsTypeInfer;
use Dedoc\Scramble\Support\InferExtensions\JsonResourceCreationInfer;
use Dedoc\Scramble\Support\InferExtensions\JsonResourceTypeInfer;
use Dedoc\Scramble\Support\InferExtensions\PossibleExceptionInfer;
use Dedoc\Scramble\Support\InferExtensions\ResourceCollectionTypeInfer;
use Dedoc\Scramble\Support\InferExtensions\ResponseFactoryTypeInfer;
use Dedoc\Scramble\Support\InferExtensions\ValidatorTypeInfer;
use Dedoc\Scramble\Support\InferHandlers\PhpDocHandler;

class Extensions
{
    public static function makeInferHandlers()
    {
        $extensions = static::defaultInferExtensions();
        $handlers = [new PhpDocHandler()];

        return [
            new FunctionLikeHandler(),
            new AssignHandler(),
            new ClassHandler(),
            new PropertyHandler(),
            new ArrayHandler(),
            new ArrayItemHandler(),
            new ReturnHandler(),
            new ThrowHandler(),
            new ExpressionTypeInferringExtensions(array_values(array_filter(
                $extensions,
                fn ($ext) => $ext instanceof ExpressionTypeInferExtension,
            ))),
            new ExceptionInferringExtensions(array_values(array_filter(
                $extensions,
                fn ($ext) => $ext instanceof ExpressionExceptionExtension,
            ))),
            ...$handlers,
        ];
    }

    private static function defaultInferExtensions()
    {
        return [
            new PossibleExceptionInfer(),
            new AbortHelpersExceptionInfer(),

            new JsonResourceCallsTypeInfer(),
            new JsonResourceCreationInfer(),
            new JsonResourceTypeInfer(),
            new ValidatorTypeInfer(),
            new ResourceCollectionTypeInfer(),
            new ResponseFactoryTypeInfer(),
        ];
    }
}
