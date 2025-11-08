<?php

namespace Dedoc\Scramble\Support\OperationExtensions\RulesExtractor;

use Dedoc\Scramble\Contexts\RuleTransformerContext;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\PhpDoc\PhpDocTypeHelper;
use Dedoc\Scramble\Support\ContainerUtils;
use Dedoc\Scramble\Support\Generator\MissingValue;
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

        $type = $this->applyDocsInfo($type);

        return $this->makeParameterFromSchema($type);
    }

    public function shouldIgnoreParameter(): bool
    {
        return (bool) ($this->docNode?->getTagsByName('@ignoreParam') ?? []);
    }

    protected function makeParameterFromSchema(OpenApiSchema $schema): Parameter
    {
        $description = $schema->description;
        $example = $schema->example;

        $schema->setDescription('')->example(new MissingValue);

        return Parameter::make($this->name, $schema->getAttribute('isInQuery') ? 'query' : $this->in)
            ->setSchema(Schema::fromType($schema))
            ->example($example)
            ->required((bool) $schema->getAttribute('required', false))
            ->description($description);
    }

    private function applyDocsInfo(OpenApiSchema $type): OpenApiSchema
    {
        if (! $this->docNode) {
            return $type;
        }

        if (count($varTags = $this->docNode->getVarTagValues())) {
            $varTag = array_values($varTags)[0];

            $type = $this->openApiTransformer
                ->transform(PhpDocTypeHelper::toType($varTag->type))
                ->mergeAttributes($type->attributes());
        }

        $description = (string) Str::of($this->docNode->getAttribute('summary') ?: '')
            ->append(' '.($this->docNode->getAttribute('description') ?: ''))
            ->trim();

        if ($description) {
            $type->setDescription($description);
        }

        if ($examples = ExamplesExtractor::make($this->docNode)->extract(preferString: $type instanceof StringType)) {
            $type->example($examples[0]);
        }

        if ($default = ExamplesExtractor::make($this->docNode, '@default')->extract(preferString: $type instanceof StringType)) {
            $type->default($default[0]);
        }

        if ($format = array_values($this->docNode->getTagsByName('@format'))[0]->value->value ?? null) {
            $type->format($format);
        }

        if ($this->docNode->getTagsByName('@query')) {
            $type->setAttribute('isInQuery', true);
        }

        return $type;
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
