<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Contexts\RuleTransformerContext;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\ContainerUtils;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type as OpenApiSchema;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\Helpers\ExamplesExtractor;
use Illuminate\Support\Str;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class RulesToParameter
{
    /**
     * @param  RuleSet  $rules
     */
    public function __construct(
        private string $name,
        private mixed $rules,
        private ?PhpDocNode $docNode,
        private TypeTransformer $openApiTransformer,
        private string $in = 'query',
    ) {}

    public function generate(): ?Parameter
    {
        if ($this->shouldIgnoreParameter()) {
            return null;
        }

        $type = (new RuleSetToSchemaTransformer(
            $this->openApiTransformer,
            $this->makeRuleTransformerContext(),
        ))->transform($this->rules);

        $parameter = $this->makeParameterFromSchema($type);

        return $this->applyDocsInfo($parameter);
    }

    public function shouldIgnoreParameter(): bool
    {
        return (bool) ($this->docNode?->getTagsByName('@ignoreParam') ?? []);
    }

    protected function makeParameterFromSchema(OpenApiSchema $schema): Parameter
    {
        $description = $schema->description;
        $schema->setDescription('');

        return Parameter::make($this->name, $this->in)
            ->setSchema(Schema::fromType($schema))
            ->required((bool) $schema->getAttribute('required', false))
            ->description($description);
    }

    private function applyDocsInfo(Parameter $parameter): Parameter
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

    private function makeRuleTransformerContext(): RuleTransformerContext
    {
        return ContainerUtils::makeContextable(RuleTransformerContext::class, [
            'field' => $this->name,
            'rules' => $this->rules,
            OpenApi::class => $this->openApiTransformer->context->openApi,
            GeneratorConfig::class => $this->openApiTransformer->context->config,
        ]);
    }
}
