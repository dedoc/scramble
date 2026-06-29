<?php

namespace Dedoc\Scramble\DocumentTransformers;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Tag;
use Dedoc\Scramble\Support\GroupTree\GroupResolverPipeline;
use Dedoc\Scramble\Support\GroupTree\TreeBuilder;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

/**
 * Builds the hierarchical documentation explorer.
 *
 * For every operation a group path is resolved through the
 * {@see GroupResolverPipeline}. The resolved paths are used to:
 *
 *  - assign each operation its leaf group as a tag;
 *  - synthesise parent/child tag relationships (`parent`);
 *  - emit the `x-tree` and `x-tagGroups` document extensions consumed by the
 *    documentation UI and Redoc-compatible renderers.
 *
 * The transformer is a no-op unless `scramble.groups.enabled` is `true`, which
 * preserves Scramble's default (flat) tagging behaviour for existing users.
 */
class AddTagGroups implements DocumentTransformer
{
    /**
     * Per-instance cache of resolved group paths, keyed by route signature.
     *
     * @var array<string, list<string>>
     */
    protected array $resolvedPaths = [];

    public function __construct(
        protected ?GroupResolverPipeline $pipeline = null,
    ) {}

    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        if (! config('scramble.groups.enabled', false)) {
            return;
        }

        $pipeline = $this->pipeline ?? new GroupResolverPipeline;
        $tree = new TreeBuilder;

        $tags = $this->indexTags($document);
        $orders = $this->groupOrders($document);

        /** @var array<string, array<string, true>> $tagGroups */
        $tagGroups = [];
        $hasNesting = false;

        foreach ($document->paths as $pathItem) {
            foreach ($pathItem->operations as $operation) {
                $routeAttribute = $operation->getAttribute('route');
                $route = $routeAttribute instanceof Route ? $routeAttribute : null;

                $groupPath = $this->resolveGroupPath($pipeline, $operation, $route);
                if ($groupPath === []) {
                    continue;
                }

                $tree->addRoute($groupPath, $this->makeRouteNode($operation, $route), $orders);

                $this->syncTags($document, $tags, $groupPath);

                $leaf = $groupPath[array_key_last($groupPath)];
                $operation->setTags([$leaf]);

                $top = $groupPath[0];
                $tagGroups[$top][$leaf] = true;

                if (count($groupPath) > 1) {
                    $hasNesting = true;
                }
            }
        }

        $document->setExtensionProperty('tree', $tree->toArray());

        if ($hasNesting) {
            $document->setExtensionProperty('tagGroups', $this->buildTagGroups($tagGroups));
        }
    }

    /**
     * @return list<string>
     */
    protected function resolveGroupPath(GroupResolverPipeline $pipeline, Operation $operation, ?Route $route): array
    {
        if ($route instanceof Route) {
            $key = implode('|', $route->methods()).':'.$route->uri();

            return $this->resolvedPaths[$key] ??= $this->ensureNonEmpty(
                $pipeline->resolve($operation, $route),
                $operation,
            );
        }

        return $this->ensureNonEmpty([], $operation);
    }

    /**
     * Guarantee a non-empty group path, falling back to the operation's existing
     * tags (the default Scramble behaviour) and finally to "General".
     *
     * @param  list<string>  $path
     * @return list<string>
     */
    protected function ensureNonEmpty(array $path, Operation $operation): array
    {
        if ($path !== []) {
            return $path;
        }

        if ($operation->tags !== []) {
            return [$operation->tags[0]];
        }

        return ['General'];
    }

    /**
     * Index the document tags by name for O(1) lookups and mutation.
     *
     * @return array<string, Tag>
     */
    protected function indexTags(OpenApi $document): array
    {
        $tags = [];
        foreach ($document->tags as $tag) {
            $tags[$tag->name] = $tag;
        }

        return $tags;
    }

    /**
     * Map of group name to sort order, derived from `#[Group(weight:)]`.
     *
     * @return array<string, int>
     */
    protected function groupOrders(OpenApi $document): array
    {
        $orders = [];
        foreach ($document->tags as $tag) {
            $weight = $tag->getAttribute('weight');
            if (is_int($weight)) {
                $orders[$tag->name] = $weight;
            }
        }

        return $orders;
    }

    /**
     * Ensure each segment of the path exists as a Tag and carries the correct
     * parent relationship.
     *
     * @param  array<string, Tag>  $tags
     * @param  list<string>  $groupPath
     */
    protected function syncTags(OpenApi $document, array &$tags, array $groupPath): void
    {
        $parent = null;

        foreach ($groupPath as $segment) {
            if (! isset($tags[$segment])) {
                $tags[$segment] = new Tag($segment);
                $document->tags[] = $tags[$segment];
            }

            if ($parent !== null) {
                $tags[$segment]->parent = $parent;
            }

            $parent = $segment;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeRouteNode(Operation $operation, ?Route $route): array
    {
        $controller = $route?->getAction('controller');
        $controllerName = is_string($controller) ? class_basename(explode('@', $controller)[0]) : null;

        $id = $operation->operationId
            ?: $operation->method.'-'.Str::of($operation->path)->replace(['/', '{', '}'], ['-', '', ''])->trim('-');

        return array_filter([
            'id' => $id,
            'name' => $operation->summary ?: $operation->method.' '.$operation->path,
            'method' => $operation->method,
            'path' => $operation->path,
            'summary' => $operation->summary,
            'operationId' => $operation->operationId,
            'controller' => $controllerName,
            'type' => 'route',
            'icon' => 'endpoint',
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, array<string, true>>  $tagGroups
     * @return list<array{name: string, tags: list<string>}>
     */
    protected function buildTagGroups(array $tagGroups): array
    {
        $result = [];

        foreach ($tagGroups as $name => $leaves) {
            $result[] = [
                'name' => $name,
                'tags' => array_keys($leaves),
            ];
        }

        return $result;
    }
}
