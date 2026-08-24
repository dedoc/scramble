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
            $group = $attribute->newInstance();

            $name = $group->name;

            if (! $name) {
                return $acc;
            }

            $description = $group->description;
            $weight = $group->weight !== PHP_INT_MAX ? $group->weight : null;

            /** @var Tag $tag */
            $tag = $acc->get($name, new Tag($name));

            if ($description !== null && $tag->description === null) {
                $tag->description = $description;
            }

            if ($weight !== null && $tag->getAttribute('weight') === null) {
                $tag->setAttribute('weight', $weight);
            }

            if ($group->parent !== null && $tag->parent === null) {
                $tag->parent = $group->parent;
            }

            if ($group->summary !== null && $tag->summary === null) {
                $tag->summary = $group->summary;
            }

            if ($group->kind !== null && $tag->kind === null) {
                $tag->kind = $group->kind;
            }

            if ($group->externalDocsUrl !== null && $tag->externalDocs === null) {
                $tag->externalDocs = new ExternalDocumentation(
                    url: $group->externalDocsUrl,
                    description: $group->externalDocsDescription,
                );
            }

            $acc->offsetSet($name, $tag);

            return $acc;
        }, collect());

        $this->addMissingParentTags($tags);

        return $tags->sortBy(fn (Tag $t) => $t->getAttribute('weight', INF))->values()->all();
    }

    /**
     * A tag naming a parent nothing declares would leave the document invalid: the
     * spec requires the named parent to exist in the API description.
     *
     * @param  Collection<string, Tag>  $tags
     */
    private function addMissingParentTags(Collection $tags): void
    {
        $tags->pluck('parent')
            ->filter()
            ->unique()
            ->reject(fn (string $parent): bool => $tags->has($parent))
            ->each(fn (string $parent) => $tags->offsetSet($parent, new Tag($parent)));
    }
}
