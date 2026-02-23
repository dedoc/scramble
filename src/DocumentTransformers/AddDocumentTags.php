<?php

namespace Dedoc\Scramble\DocumentTransformers;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\ExternalDocumentation;
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
            $parent = $arguments['parent'] ?? $arguments[3] ?? null;
            $summary = $arguments['summary'] ?? $arguments[4] ?? null;
            $kind = $arguments['kind'] ?? $arguments[5] ?? null;
            $externalDocsUrl = $arguments['externalDocsUrl'] ?? $arguments[6] ?? null;
            $externalDocsDescription = $arguments['externalDocsDescription'] ?? $arguments[7] ?? null;

            // Use combination of name + parent as unique key to allow same tag name
            // under different parents (hierarchical groups)
            $tagKey = $parent ? "{$parent}/{$name}" : $name;

            /** @var Tag $tag */
            $tag = $acc->get($tagKey, new Tag($name));

            if ($description !== null && $tag->description === null) {
                $tag->description = $description;
            }

            if ($weight !== null && $tag->getAttribute('weight') === null) {
                $tag->setAttribute('weight', $weight);
            }

            if ($parent !== null && $tag->parent === null) {
                $tag->parent = $parent;
            }

            if ($summary !== null && $tag->summary === null) {
                $tag->summary = $summary;
            }

            if ($kind !== null && $tag->kind === null) {
                $tag->kind = $kind;
            }

            if ($externalDocsUrl !== null && $tag->externalDocs === null) {
                $tag->externalDocs = new ExternalDocumentation(
                    url: $externalDocsUrl,
                    description: $externalDocsDescription,
                );
            }

            $acc->offsetSet($tagKey, $tag);

            return $acc;
        }, collect());

        // Auto-create any missing parent tags referenced by child tags
        $parentNames = $tags->filter(fn (Tag $tag) => $tag->parent !== null)
            ->map(fn (Tag $tag) => $tag->parent)
            ->unique()
            ->values();

        $existingTagNames = $tags->map(fn (Tag $tag) => $tag->name)->values();

        foreach ($parentNames as $parentName) {
            if (! $existingTagNames->contains($parentName)) {
                $tags->offsetSet($parentName, new Tag($parentName));
            }
        }

        return $tags->sortBy(fn (Tag $t) => $t->getAttribute('weight', INF))->values()->all();
    }
}
