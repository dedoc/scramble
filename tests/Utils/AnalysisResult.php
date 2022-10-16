<?php

namespace Dedoc\Scramble\Tests\Utils;

use Dedoc\Scramble\Infer\Scope\Scope;
use Dedoc\Scramble\Support\Type\ObjectType;
use PhpParser;

class AnalysisResult
{
    private Scope $scope;

    /**
     * @var PhpParser\Node\Stmt[]
     */
    private array $ast;

    public function __construct(Scope $scope, array $ast)
    {
        $this->scope = $scope;
        $this->ast = $ast;
    }

    public function getClassType(string $className): ?ObjectType
    {

    }
}
