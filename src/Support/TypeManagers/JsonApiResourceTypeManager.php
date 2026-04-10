<?php

namespace Dedoc\Scramble\Support\TypeManagers;

use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Infer\Services\ReferenceTypeResolver;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralBooleanType;
use Dedoc\Scramble\Support\Type\MixedType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\NewCallReferenceType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

class JsonApiResourceTypeManager
{
    use ManagesProperties;

    /**
     * All templates of JsonApiResource in order, including those inherited from JsonResource.
     * The order must match the templateTypes array built by AfterJson*DefinitionCreatedExtension.
     */
    const ORDERED_PROPERTY_TO_TEMPLATE_MAP = [
        'resource' => 'TResource',
        'additional' => 'TAdditional',
        'jsonApiLinks' => 'TJsonApiLinks',
        'jsonApiMeta' => 'TJsonApiMeta',
        'usesRequestQueryString' => 'TUsesRequestQueryString',
        'includesPreviouslyLoadedRelationships' => 'TIncludesPreviouslyLoadedRelationships',
        'loadedRelationshipsMap' => 'TLoadedRelationshipsMap',
        'loadedRelationshipIdentifiers' => 'TLoadedRelationshipIdentifiers',
    ];

    public function createType(
        string $name,
        Type $resource = new MixedType,
        Type $additional = new MixedType,
        Type $jsonApiLinks = new KeyedArrayType,
        Type $jsonApiMeta = new KeyedArrayType,
        Type $usesRequestQueryString = new LiteralBooleanType(true),
        Type $includesPreviouslyLoadedRelationships = new LiteralBooleanType(false),
        Type $loadedRelationshipsMap = new MixedType,
        Type $loadedRelationshipIdentifiers = new KeyedArrayType,
    ): Generic
    {
        return new Generic($name, [
            /* TResource */ $resource,
            /* TAdditional */ $additional,
            /* TJsonApiLinks */ $jsonApiLinks,
            /* TJsonApiMeta */ $jsonApiMeta,
            /* TUsesRequestQueryString */ $usesRequestQueryString,
            /* TIncludesPreviouslyLoadedRelationships */ $includesPreviouslyLoadedRelationships,
            /* TLoadedRelationshipsMap */ $loadedRelationshipsMap,
            /* TLoadedRelationshipIdentifiers */ $loadedRelationshipIdentifiers,
        ]);
    }

    public function normalizeType(ObjectType $type): Generic
    {
        if ($type instanceof Generic) {
            return $type;
        }

        $inferredCreationType = ReferenceTypeResolver::getInstance()
            ->resolve(
                new GlobalScope,
                new NewCallReferenceType($type->name, []),
            );

        if ($inferredCreationType instanceof Generic) {
            return $inferredCreationType;
        }

        return $this->createType(name: $type->name);
    }
}
