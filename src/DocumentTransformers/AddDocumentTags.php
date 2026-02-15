<?php

namespace Dedoc\Scramble\DocumentTransformers;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Tag;
use Illuminate\Support\Collection;
use ReflectionAttribute;

class AddDocumentTags implements DocumentTransformer
{
    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $document->tags = $this->makeTagsFromGroupAttributes($context->groups);
    }

    /**
     * @param  Collection<int, ReflectionAttribute<Group>>  $groupsAttributes
     * @return Tag[]
     */
    private function makeTagsFromGroupAttributes(Collection $groupsAttributes)
    {
        /** @var Collection<string, Tag> $tags */
        $tags = $groupsAttributes->reduce(function (Collection $acc, ReflectionAttribute $attribute) {
            $arguments = $attribute->getArguments();

            $name = $arguments['name'] ?? $arguments[0] ?? null;

            if (! $name) {
                return $acc;
            }

            $description = $arguments['description'] ?? $arguments[1] ?? null;
            $weight = $arguments['weight'] ?? $arguments[2] ?? null;

            /** @var Tag $tag */
            $tag = $acc->get($name, new Tag($name));

            if ($description !== null && $tag->description === null) {
                $tag->description = $description;
            }

            if ($weight !== null && $tag->getAttribute('weight') === null) {
                $tag->setAttribute('weight', $weight);
            }

            $acc->offsetSet($name, $tag);

            return $acc;
        }, collect());

        return $tags->sortBy(fn (Tag $t) => $t->getAttribute('weight', INF))->values()->all();
    }
}
