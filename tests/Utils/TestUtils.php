<?php

namespace Dedoc\Scramble\Tests\Utils;

use Dedoc\Scramble\Infer\FlowNodes\BasicFlowNode;
use Dedoc\Scramble\Infer\FlowNodes\FlowNodeTypeGetter;
use Dedoc\Scramble\Infer\FlowNodes\LazyIndex;
use PhpParser\ParserFactory;

class TestUtils
{
    public static function getExpressionType(string $expressionCode, array $functionsDefinitions = [], array $classesDefinitions = []): TestAnalysisResult
    {
        $index = new LazyIndex(
            functions: $functionsDefinitions,
            classes: $classesDefinitions,
        );

        $statement = (new ParserFactory())->createForHostVersion()->parse('<?php '.$expressionCode.';')[0];
        $expression = $statement->expr;

        return new TestAnalysisResult(
            type: (new FlowNodeTypeGetter($expression, new BasicFlowNode($statement, [])))->getType(),
            index: $index,
        );
    }
}
