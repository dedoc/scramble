<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

class RulesMapper
{
    public function string(Type $_)
    {
        return new StringType;
    }

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

    public function array(Type $_)
    {
        return new ArrayType;
    }

    public function exists(Type $type, $params)
    {
        if (!$type instanceof UnknownType) {
            return $type;
        }

        if (Str::is(['id', '*_id'], $column = $params[1] ?? 'id')) {
            return $this->int($type);
        }

        return $type;
    }

    public function email(Type $type)
    {
        if ($type instanceof UnknownType) {
            $type = $this->string($type);
        }

        return $type->format('email');
    }

    public function nullable(Type $type)
    {
        return $type->nullable(true);
    }

    public function required(Type $type)
    {
        $type->setAttribute('required', true);

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
