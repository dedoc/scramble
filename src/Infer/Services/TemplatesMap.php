<?php

namespace Dedoc\Scramble\Infer\Services;

use Dedoc\Scramble\Infer\Contracts\ArgumentTypeBag;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;

class TemplatesMap
{
    private const ARGUMENTS = 'Arguments';

    /**
     * @param  array<string, Type>  $bag
     */
    public function __construct(
        public array $bag,
        public ArgumentTypeBag $arguments,
    ) {}

    /**
     * @param  array<string, Type>|TemplatesMap  $itemsToPrepend
     * @return $this
     */
    public function prepend(array|self $itemsToPrepend): self
    {
        $bag = is_array($itemsToPrepend) ? $itemsToPrepend : $itemsToPrepend->bag;

        $this->bag = array_merge($bag, $this->bag);

        return $this;
    }

    public function get(string $name, Type $defaultType = new UnknownType): Type
    {
        if ($name === self::ARGUMENTS) {
            return new KeyedArrayType(collect($this->arguments->all())->map(fn ($t, $k) => new ArrayItemType_($k, $t))->all());
        }

        return $this->bag[$name] ?? $defaultType;
    }

    public function has(string $name): bool
    {
        return $name === self::ARGUMENTS || array_key_exists($name, $this->bag);
    }
}
