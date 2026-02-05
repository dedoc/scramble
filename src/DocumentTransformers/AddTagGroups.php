<?php

namespace Dedoc\Scramble\DocumentTransformers;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Tag;

class AddTagGroups implements DocumentTransformer
{
    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $tagGroups = $this->buildTagGroups($document->tags);

        if (! empty($tagGroups)) {
            $document->setExtensionProperty('tagGroups', $tagGroups);
        }
    }

    /**
     * @param  Tag[]  $tags
     * @return array<int, array{name: string, tags: array<int, string>}>
     */
    private function buildTagGroups(array $tags): array
    {
        // Group tags by parent
        $grouped = collect($tags)
            ->filter(fn (Tag $tag) => $tag->getAttribute('parent') !== null)
            ->groupBy(function (Tag $tag): string {
                $parent = $tag->getAttribute('parent');

                return is_string($parent) ? $parent : '';
            });

        // Build x-tagGroups structure
        return $grouped->map(function ($childTags, string $parentName): array {
            /** @var \Illuminate\Support\Collection<int, Tag> $childTags */
            return [
                'name' => $parentName,
                'tags' => $childTags->map(fn (Tag $tag): string => $tag->name)->values()->all(),
            ];
        })
            ->sortBy(function (array $group) use ($tags): int {
                /** @var array{name: string, tags: array<int, string>} $group */
                // Sort by minimum weight of children
                $minWeight = collect($group['tags'])
                    ->map(fn (string $tagName) => collect($tags)->firstWhere('name', $tagName))
                    ->filter()
                    ->map(function (Tag $tag): int {
                        $weight = $tag->getAttribute('weight', INF);

                        return is_int($weight) ? $weight : PHP_INT_MAX;
                    })
                    ->min();

                return is_int($minWeight) ? $minWeight : PHP_INT_MAX;
            })
            ->values()
            ->all();
    }
}
