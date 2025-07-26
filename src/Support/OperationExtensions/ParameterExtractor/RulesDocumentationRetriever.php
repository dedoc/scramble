<?php

namespace Dedoc\Scramble\Support\OperationExtensions\ParameterExtractor;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

/**
 * @internal
 */
interface RulesDocumentationRetriever
{
    /**
     * @return array<string, PhpDocNode>
     */
    public function getDocNodes(): array;
}
