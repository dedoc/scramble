<?php

namespace Dedoc\Scramble;

interface OpenApiVisitor
{
    /**
     * Called when entering an object for traversal.
     *
     * @param  mixed  $object  OpenAPI object being entered for traversal.
     * @param  (string|int)[]  $path  The path from the root to the object being entered. When an object is a part of an array, its index is used.
     */
    public function enter(mixed $object, array $path = []);

    /**
     * Called when completed traversing an object.
     *
     * @param  mixed  $object  OpenAPI object being left after traversal.
     * @param  (string|int)[]  $path  The path from the root to the object being left. When an object is a part of an array, its index is used.
     */
    public function leave(mixed $object, array $path = []);
}
