<?php

namespace Dedoc\Scramble\Infer\DefinitionBuilders;

use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

trait BuildsPropertyType
{
    /**
     * Native declarations establish the runtime envelope and cannot be contradicted by PHPDoc.
     * Within that envelope, compatible PHPDoc represents explicit user intent: it may refine the
     * native declaration and takes precedence over advisory framework knowledge or inference.
     * Without compatible PHPDoc, the native declaration remains authoritative and later inference
     * is free to fill any information that is still missing.
     */
    private function buildPropertyType(Type $declarationType, ?Type $phpDocType): Type
    {
        if (! $phpDocType) {
            return $declarationType;
        }

        if ($declarationType instanceof UnknownType) {
            return $phpDocType;
        }

        if (! $declarationType->accepts($phpDocType)) {
            return $declarationType;
        }

        return $phpDocType;
    }
}
