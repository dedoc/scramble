<?php

namespace Dedoc\Scramble\Tests\Utils;

use Dedoc\Scramble\Infer\Contracts\Index;
use Dedoc\Scramble\Infer\FlowNodes\BasicFlowNode;
use Dedoc\Scramble\Infer\FlowNodes\FlowNodeTypeGetter;
use Dedoc\Scramble\Infer\FlowNodes\LazyIndex;
use PhpParser\Parser;

class TestUtils
{
    public function __construct(
        public readonly Index $index,
        public readonly Parser $parser,
    )
    {
    }

    public function getExpressionType(string $expressionCode, array $functionsDefinitions = [], array $classesDefinitions = []): TestAnalysisResult
    {
        $index = new LazyIndex(
            $this->parser,
            functions: $functionsDefinitions,
            classes: $classesDefinitions,
        );

        $statement = $this->parser->parse('<?php '.$expressionCode.';')[0];
        $expression = $statement->expr;

        return new TestAnalysisResult(
            type: (new FlowNodeTypeGetter($expression, new BasicFlowNode($statement, [])))->getType(),
            index: $index,
        );
    }
}
