<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\RuleTransforming\RuleSetToSchemaTransformer;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Illuminate\Validation\ConditionalRules;

class RulesMapper
{
    public function __construct(
        private TypeTransformer $openApiTransformer, // @phpstan-ignore property.onlyWritten
        private RuleSetToSchemaTransformer $rulesToSchemaTransformer,
    ) {}

    public function string(Type $prevType)
    {
        return (new StringType)->addProperties($prevType);
    }

    public function bool(Type $prevType)
    {
        return (new BooleanType)->addProperties($prevType);
    }

    public function boolean(Type $_)
    {
        return $this->bool($_);
    }

    public function numeric(Type $prevType)
    {
        return (new NumberType)->addProperties($prevType);
    }

    public function decimal(Type $prevType)
    {
        return (new NumberType)->addProperties($prevType);
    }

    public function int(Type $prevType)
    {
        return (new IntegerType)->addProperties($prevType);
    }

    public function integer(Type $_)
    {
        return $this->int($_);
    }

    public function array(Type $_, $params)
    {
        if (count($params)) {
            $object = (new \Dedoc\Scramble\Support\Generator\Types\ObjectType)
                ->setRequired($params);

            foreach ($params as $param) {
                $object->addProperty($param, new UnknownType);
            }

            return $object;
        }

        return new ArrayType;
    }

    public function email(Type $type)
    {
        if ($type instanceof UnknownType) {
            $type = $this->string($type);
        }

        return $type->format('email');
    }

    public function uuid(Type $type)
    {
        if ($type instanceof UnknownType) {
            $type = $this->string($type);
        }

        return $type->format('uuid');
    }

    public function nullable(Type $type)
    {
        return $type->nullable(true);
    }

    public function present(Type $type): Type
    {
        $type->setAttribute('required', true);

        return $type;
    }

    public function required(Type $type)
    {
        $type->setAttribute('required', true);

        return $type;
    }

    public function min(Type $type, $params)
    {
        if (
            $type instanceof NumberType
            || $type instanceof ArrayType
            || $type instanceof StringType
        ) {
            $type->setMin((float) $params[0]);
        }

        return $type;
    }

    public function max(Type $type, $params)
    {
        if (
            $type instanceof NumberType
            || $type instanceof ArrayType
            || $type instanceof StringType
        ) {
            $type->setMax((float) $params[0]);
        }

        return $type;
    }

    public function size(Type $type, $params)
    {
        $type = $this->min($type, $params);

        return $this->max($type, $params);
    }

    /**
     * @param  array<mixed>  $params
     */
    public function between(Type $type, array $params): Type
    {
        if (count($params) !== 2) {
            return $type;
        }

        $type = $this->min($type, [$params[0]]);

        return $this->max($type, [$params[1]]);
    }

    public function image(Type $type)
    {
        return $this->file($type);
    }

    public function file(Type $type)
    {
        if ($type instanceof UnknownType) {
            $type = $this->string($type);
        }

        return $type->contentMediaType('application/octet-stream')->format('binary');
    }

    public function url(Type $type)
    {
        if ($type instanceof UnknownType) {
            $type = $this->string($type);
        }

        return $type->format('uri');
    }

    public function date(Type $type, $params)
    {
        if ($type instanceof UnknownType) {
            $type = $this->string($type);
        }

        return match ($params[0] ?? 'Y-m-d H:i:s') {
            'Y-m-d' => $type->format('date'),
            'Y-m-d H:i:s' => $type->format('date-time'),
            default => $type,
        };
    }

    public function date_format(Type $type, $params)
    {
        return $this->date($type, $params);
    }

    public function conditionalRules(Type $type, ConditionalRules $rule, RuleTransformerContext $context): Type
    {
        $ifRules = $rule->rules();
        $elseRules = $rule->defaultRules();

        $rules = [$ifRules, $elseRules];

        $types = $type instanceof AnyOf
            ? array_map(fn (Type $t) => (clone $t)->addProperties($type), $type->items)
            : [$type];

        $newTypes = [];
        foreach ($rules as $conditionRules) {
            foreach ($types as $type) {
                $newTypes[] = $newT = $this->rulesToSchemaTransformer->transform($conditionRules, clone $type, $context);
                if (! $conditionRules) {
                    $newT->setAttribute('isEmptyRules', true);
                }
            }
        }

        $isRequired = collect($newTypes)->every(fn (Type $t) => (bool) $t->getAttribute('required', false));

        $newTypes = array_values(array_filter($newTypes, fn (Type $t) => ! $t->getAttribute('isEmptyRules')));

        if (count($newTypes) === 1) {
            if ($isRequired !== $newTypes[0]->getAttribute('required')) {
                $newTypes[0]->setAttribute('required', $isRequired);
            }

            return $newTypes[0];
        }

        $anyOf = (new AnyOf)->setItems($newTypes);

        if ($isRequired) {
            $anyOf->setAttribute('required', true);
        }

        return $anyOf;
    }
}
