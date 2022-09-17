<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Illuminate\Support\Arr;

class RulesToParameter
{
    private string $name;

    private array $rules = [];

    const RULES_PRIORITY = [
        'bool', 'boolean', 'number', 'int', 'integer', 'string', 'array', 'exists',
    ];

    public function __construct(string $name, $rules)
    {
        $this->name = $name;
        $this->rules = Arr::wrap(is_string($rules) ? explode('|', $rules) : $rules);
    }

    public function generate()
    {
        $rules = collect($this->rules)
            ->map(fn ($v) => method_exists($v, '__toString') ? $v->__toString() : $v)
            ->sortByDesc($this->rulesSorter());

        $type = $rules->reduce(function (OpenApiType $type, $rule) {
            if (is_string($rule)) {
                return $this->getTypeFromRule($type, $rule);
            }

            return method_exists($rule, 'docs')
                ? $rule->docs($type)
                : $type;
        }, new UnknownType);

        $description = $type->description;
        $type->setDescription('');

        return Parameter::make($this->name, 'query')
            ->setSchema(Schema::fromType($type))
            ->required($rules->contains('required'))
            ->description($description);
    }

    private function rulesSorter()
    {
        return function ($v) {
            if (! is_string($v)) {
                return -2;
            }

            $index = array_search($v, static::RULES_PRIORITY);

            return $index === false ? -1 : count(static::RULES_PRIORITY) - $index;
        };
    }

    private function getTypeFromRule(OpenApiType $type, string $rule)
    {
        $rulesHandler = app(RulesMapper::class);

        $explodedRule = explode(':', $rule, 2);

        $ruleName = $explodedRule[0];
        $params = isset($explodedRule[1]) ? explode(',', $explodedRule[1]) : [];

        return method_exists($rulesHandler, $ruleName)
            ? $rulesHandler->$ruleName($type, $params)
            : $type;
    }
}
