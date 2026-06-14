<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Support\PhpDoc;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;

class PendingDocComment
{
    public function __construct(
        public string $docComment,
        public ?string $fileName = null,
        public ?string $declaringClass = null,
    ) {}

    public function resolve(): PhpDocNode
    {
        $fileName = $this->resolveFileName();

        return PhpDoc::parse(
            $this->docComment,
            $fileName ? FileNameResolver::createForFile($fileName) : null,
        );
    }

    private function resolveFileName(): ?string
    {
        if ($this->fileName) {
            return $this->fileName;
        }

        if ($this->declaringClass) {
            return $this->getDeclaringClassFile($this->declaringClass);
        }

        return null;
    }

    private function getDeclaringClassFile(string $declaringClass): ?string
    {
        try {
            $reflectionClass = new \ReflectionClass($declaringClass);

            return $reflectionClass->getFileName() ?: null;
        } catch (\Throwable) {}

        return null;
    }
}
