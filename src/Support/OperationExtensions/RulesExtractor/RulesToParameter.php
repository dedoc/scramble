<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class RulesToParameter
{
    private string $name;

    private array $rules;

    private TypeTransformer $openApiTransformer;

    const RULES_PRIORITY = [
        'bool', 'boolean', 'number', 'int', 'integer', 'string', 'array', 'exists',
    ];

    public function __construct(string $name, $rules, TypeTransformer $openApiTransformer)
    {
        $this->name = $name;
        $this->rules = Arr::wrap(is_string($rules) ? explode('|', $rules) : $rules);
        $this->openApiTransformer = $openApiTransformer;
    }

    public function generate()
    {
        $rules = collect($this->rules)
            ->map(fn ($v) => method_exists($v, '__toString') ? $v->__toString() : $v)
            ->sortByDesc($this->rulesSorter());

        $type = $rules->reduce(function (OpenApiType $type, $rule) {
            if (is_string($rule)) {
                return $this->getTypeFromStringRule($type, $rule);
            }

            return method_exists($rule, 'docs')
                ? $rule->docs($type, $this->openApiTransformer)
                : $this->getTypeFromObjectRule($type, $rule);
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

    private function getTypeFromStringRule(OpenApiType $type, string $rule)
    {
        $rulesHandler = new RulesMapper($this->openApiTransformer);

        $explodedRule = explode(':', $rule, 2);

        $ruleName = $explodedRule[0];
        $params = isset($explodedRule[1]) ? explode(',', $explodedRule[1]) : [];

        return method_exists($rulesHandler, $ruleName)
            ? $rulesHandler->$ruleName($type, $params)
            : $type;
    }

    private function getTypeFromObjectRule(OpenApiType $type, $rule)
    {
        $rulesHandler = new RulesMapper($this->openApiTransformer);

        $methodName = Str::camel(class_basename(get_class($rule)));

        return method_exists($rulesHandler, $methodName)
            ? $rulesHandler->$methodName($type, $rule)
            : $type;
    }
}
