<?php

namespace Dedoc\Scramble\Exceptions;

use Dedoc\Scramble\Support\Generator\Tag;

class MissingParentTagException extends \Exception
{
    /**
     * @param  list<Tag>  $tags
     */
    public static function forTags(array $tags): self
    {
        $references = array_map(
            fn (Tag $tag) => sprintf('[%s] names parent [%s]', $tag->name, $tag->parent),
            $tags,
        );

        return new self(sprintf(
            'Tags reference parents that are not declared: %s. Declare the parents with `withTags()`, or allow `createMissingParents`.',
            implode(', ', $references),
        ));
    }
}
