<?php

namespace Dedoc\Scramble\Support\GroupTree;

/**
 * A single node in the documentation explorer tree. A node is either a "group"
 * (a folder that can contain child groups and routes) or a "route" leaf.
 */
class TreeNode
{
    public ?string $parent = null;

    public int $depth = 0;

    public int $order = PHP_INT_MAX;

    public string $type = 'group';

    public ?string $icon = 'folder';

    /** @var list<TreeNode> */
    public array $children = [];

    /** @var list<array<string, mixed>> */
    public array $routes = [];

    /** @var array<string, mixed> */
    public array $metadata = [];

    public function __construct(
        public string $id,
        public string $name,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        // Groups first, then by explicit order, then by natural (case-insensitive) name.
        // usort is stable as of PHP 8.0, so equal nodes keep their insertion order.
        $children = $this->children;
        usort($children, function (TreeNode $a, TreeNode $b) {
            return [$a->type === 'group' ? 0 : 1, $a->order]
                <=> [$b->type === 'group' ? 0 : 1, $b->order]
                ?: strnatcasecmp($a->name, $b->name);
        });

        $children = array_map(fn (TreeNode $child) => $child->toArray(), $children);

        return array_filter([
            'id' => $this->id,
            'name' => $this->name,
            'parent' => $this->parent,
            'depth' => $this->depth,
            'order' => $this->order,
            'type' => $this->type,
            'icon' => $this->icon,
            'children' => $children,
            'routes' => $this->routes,
            'metadata' => $this->metadata,
        ], fn ($value) => $value !== null && $value !== []);
    }
}
