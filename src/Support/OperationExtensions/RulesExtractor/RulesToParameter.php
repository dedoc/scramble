<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiType;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Helpers\ExamplesExtractor;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class RulesToParameter
{
    const RULES_PRIORITY = [
        'bool', 'boolean', 'numeric', 'int', 'integer', 'file', 'image', 'string', 'array', 'exists',
    ];

    private array $rules;

    public function __construct(
        private string $name,
        $rules,
        private ?PhpDocNode $docNode,
        private TypeTransformer $openApiTransformer,
        private string $in = 'query',
    ) {
        $this->rules = Arr::wrap(is_string($rules) ? explode('|', $rules) : $rules);
    }

    public function generate(): ?Parameter
    {
        if (count($this->docNode?->getTagsByName('@ignoreParam') ?? [])) {
            return null;
        }

        $rules = collect($this->rules)
            ->map(function ($v) {
                if (! method_exists($v, '__toString')) {
                    return $v;
                }
                try {
                    return $v->__toString();
                } catch (\Throwable) {
                    return $v;
                }
            })
            ->sortByDesc($this->rulesSorter());

        /** @var OpenApiType $type */
        $type = $rules->reduce(function (OpenApiType $type, $rule) {
            if (is_string($rule)) {
                return $this->getTypeFromStringRule($type, $rule);
            }

            return method_exists($rule, 'docs')
                ? $rule->docs($type, $this->openApiTransformer)
                : $this->getTypeFromObjectRule($type, $rule);
        }, new UnknownType);

        if (
            $rules->containsStrict('sometimes')
            && $type->getAttribute('required')
        ) {
            $type->setAttribute('required', false);
        }

        $description = $type->description;
        $type->setDescription('');

        $containsRequiringRule = $rules->containsStrict('required')
            || $rules->containsStrict('present');

        $parameter = Parameter::make($this->name, $this->in)
            ->setSchema(Schema::fromType($type))
            ->required(
                $containsRequiringRule
                && ! $rules->containsStrict('sometimes')
            )
            ->description($description);

        return $this->applyDocsInfo($parameter);
    }

    private function applyDocsInfo(Parameter $parameter)
    {
        if (! $this->docNode) {
            return $parameter;
        }

        $description = (string) Str::of($this->docNode->getAttribute('summary') ?: '')
            ->append(' '.($this->docNode->getAttribute('description') ?: ''))
            ->trim();

        if ($description) {
            $parameter->description($description);
        }

        if (count($varTags = $this->docNode->getVarTagValues())) {
            $varTag = $varTags[0];

            $parameter->setSchema(Schema::fromType(
                $this->openApiTransformer->transform(PhpDocTypeHelper::toType($varTag->type)),
            ));
        }

        if ($examples = ExamplesExtractor::make($this->docNode)->extract(preferString: $parameter->schema->type instanceof StringType)) {
            $parameter->example($examples[0]);
        }

        if ($default = ExamplesExtractor::make($this->docNode, '@default')->extract(preferString: $parameter->schema->type instanceof StringType)) {
            $parameter->schema->type->default($default[0]);
        }

        if ($format = array_values($this->docNode->getTagsByName('@format'))[0]->value->value ?? null) {
            $parameter->schema->type->format($format);
        }

        if ($this->docNode->getTagsByName('@query')) {
            $parameter->setAttribute('isInQuery', true);

            $parameter->in = 'query';
        }

        return $parameter;
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
