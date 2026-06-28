<?php

namespace Dedoc\Scramble\Tests\Files;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;

/**
 * @template T of Model
 *
 * @mixin Builder<T>
 */
class CustomScoutBuilder
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $subject
     */
    public function __construct(
        protected Builder $subject,
    ) {}

    public function __call(string $name, array $arguments): mixed
    {
        return $this->subject->$name(...$arguments);
    }
}
