<?php

namespace Dedoc\Scramble\Support\RulesExtractor;

use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Illuminate\Support\Stringable;

class RulesMapper
{
    public function bool(Type $_)
    {
        return new BooleanType;
    }

    public function boolean(Type $_)
    {
        return $this->bool($_);
    }

    public function numeric(Type $_)
    {
        return new NumberType;
    }

    public function int(Type $_)
    {
        return new IntegerType;
    }

    public function integer(Type $_)
    {
        return $this->int($_);
    }

    public function nullable(Type $type)
    {
        return $type->nullable(true);
    }

    public function exists(Type $type, $params)
    {
        if (in_array('id', $params)) {
            return $this->int($type);
        }

        return $type;
    }

    public function min(Type $type, $params)
    {
        if ($type instanceof NumberType) {
            $type->setMin((float) $params[0]);
        }

        return $type;
    }

    public function max(Type $type, $params)
    {
        if ($type instanceof NumberType) {
            $type->setMax((float) $params[0]);
        }

        return $type;
    }

    public function in(Type $type, $params)
    {
        return $type->enum(
            collect($params)
                ->mapInto(Stringable::class)
                ->map(fn (Stringable $v) => (string) $v->trim('"')->replace('""', '"'))
                ->values()
                ->all()
        );
    }
}
