<?php

namespace Dedoc\Scramble\Infer\Scope;

use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use PhpParser\Node;

/**
 * This class stores the information about all the node types. It exists per run.
 */
class NodeTypesResolver
{
    private $nodeTypes = [];

    public function hasType(Node $node): bool
    {
        $nodeId = spl_object_id($node);

        return array_key_exists($nodeId, $this->nodeTypes);
    }

    public function getType(Node $node): Type
    {
        $nodeId = spl_object_id($node);

        return $this->nodeTypes[$nodeId] ?? new UnknownType;
    }

    public function setType(Node $node, Type $type): void
    {
        $nodeId = spl_object_id($node);

        $this->nodeTypes[$nodeId] = $type;
    }
}
