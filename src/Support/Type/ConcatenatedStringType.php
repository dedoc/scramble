<?php

namespace Dedoc\Scramble\Support\Type;

use Dedoc\Scramble\Support\Type\Contracts\LiteralType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;

class ConcatenatedStringType extends StringType
{
    /**
     * @param  Type[]  $parts
     */
    private function __construct(public array $parts) {}

    /**
     * @param  Type[]  $parts
     */
    public static function fromParts(array $parts): Type
    {
        $flattened = [];

        foreach ($parts as $part) {
            if ($part instanceof self) {
                array_push($flattened, ...$part->parts);

                continue;
            }

            $flattened[] = $part;
        }

        $collapsed = [];

        foreach ($flattened as $part) {
            $literal = self::stringifyLiteral($part);
            $part = $literal !== null ? new LiteralStringType($literal) : $part;

            $lastIndex = array_key_last($collapsed);
            $last = $lastIndex !== null ? $collapsed[$lastIndex] : null;

            if ($part instanceof LiteralStringType && $last instanceof LiteralStringType) {
                $collapsed[$lastIndex] = new LiteralStringType($last->value.$part->value);

                continue;
            }

            $collapsed[] = $part;
        }

        if (count($collapsed) === 1) {
            return $collapsed[0];
        }

        return new self($collapsed);
    }

    public function nodes(): array
    {
        return ['parts'];
    }

    public function isSame(Type $type)
    {
        if (! $type instanceof static || count($this->parts) !== count($type->parts)) {
            return false;
        }

        foreach ($this->parts as $i => $part) {
            if (! $part->isSame($type->parts[$i])) {
                return false;
            }
        }

        return true;
    }

    public function toString(): string
    {
        $body = '';

        foreach ($this->parts as $part) {
            if ($part instanceof LiteralStringType) {
                $body .= self::escapeTemplateLiteral($part->value);

                continue;
            }

            $body .= '${'.$part->toString().'}';
        }

        return 'string(`'.$body.'`)';
    }

    private static function stringifyLiteral(Type $type): ?string
    {
        if (! $type instanceof LiteralType) {
            return null;
        }

        $value = $type->getValue();

        return is_scalar($value) ? (string) $value : null;
    }

    private static function escapeTemplateLiteral(string $value): string
    {
        return str_replace(
            ['\\', '`', '${'],
            ['\\\\', '\\`', '\\${'],
            $value,
        );
    }
}
