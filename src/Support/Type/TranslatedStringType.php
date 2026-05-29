<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Type\Contracts\LateResolvingType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Illuminate\Translation\Translator;

class TranslatedStringType extends StringType implements LateResolvingType
{
    public function __construct(
        public Type $key,
    )
    {
    }

    public function nodes(): array
    {
        return ['key'];
    }

    public function isSame(Type $type): bool
    {
        return $type instanceof static && $type->key->isSame($this->key);
    }

    public function toString(): string
    {
        return '__('.$this->key->toString().')';
    }

    public function resolve(): Type
    {
        if ($this->key instanceof LiteralStringType) {
            $translator = tap(clone app('translator'), fn (Translator $t) => $t->setLocale(config('app.locale')));

            return new LiteralStringType($translator->get($this->key->value));
        }

        return $this->key;
    }

    public function isResolvable(): bool
    {
        return TypeHelper::isResolvable($this->key);
    }
}
