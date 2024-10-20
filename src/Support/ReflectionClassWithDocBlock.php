<?php

namespace Dedoc\Scramble\Support;

use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PropertyTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

class ReflectionClassWithDocBlock extends \ReflectionClass
{
    public array $imports = [];

    public function __construct($objectOrClass)
    {
        parent::__construct($objectOrClass);

        $this->imports = $this->getClassImports();
    }

    /**
     * Get parsed doc-block with resolved class-names.
     */
    public function getDocParsed(): ?PhpDocNode
    {
        if ($doc = $this->getDocComment()) {
            return $this->resolveDocClasses(PhpDoc::parse($doc));
        }

        return null;
    }

    /**
     * PhpStan doesn't resolve class-names of properties.
     *
     * use App\MyClass;
     *
     * // property MyClass $var
     *
     * We need to resolve property type to full-qualified class-name.
     */
    protected function resolveDocClasses(PhpDocNode $doc): PhpDocNode
    {
        foreach ($doc->children as $node) {
            if ($node instanceof PhpDocTagNode) {
                if ($node->value instanceof PropertyTagValueNode) {
                    $node->value->type = $this->resolve($node->value->type);
                }
            }
        }

        return $doc;
    }

    protected function resolve(TypeNode $type): TypeNode
    {
        if ($type instanceof IdentifierTypeNode) {
            $name = $type->name;
            if (isset($this->imports[$name])) {
                $type->name = $this->imports[$name];
            }
        }

        if ($type instanceof ArrayTypeNode) {
            $type->type = $this->resolve($type->type);
        }

        if ($type instanceof UnionTypeNode || $type instanceof IntersectionTypeNode) {
            $type->types = array_map(fn($type) => $this->resolve($type), $type->types);
        }

        if ($type instanceof GenericTypeNode) {
            $type->type = $this->resolve($type->type);
            $type->genericTypes = array_map(fn($type) => $this->resolve($type), $type->genericTypes);
        }

        if ($type instanceof ArrayShapeNode) {
            $type->items = array_map(fn($type) => $this->resolve($type), $type->items);
        }

        if ($type instanceof ArrayShapeItemNode) {
            $type->valueType = $this->resolve($type->valueType);
        }

        return $type;
    }

    protected function getClassImports(): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        $parsed = $parser->parse(file_get_contents($this->getFileName()));

        $uses = [];

        /** @var Stmt $namespace */
        $namespace = current($parsed);
        /** @var Stmt $stmt */
        foreach ($namespace->stmts as $stmt) {
            if ($stmt instanceof Stmt\GroupUse) {
                foreach ($stmt->uses as $use) {
                    $alias = $use->alias?->name ?? str($use->name->name)->afterLast('\\')->toString();
                    $uses[$alias] = $stmt->prefix->name.'\\'.$use->name->name;
                }
            }
            if ($stmt instanceof Stmt\Use_) {
                foreach ($stmt->uses as $use) {
                    $alias = $use->alias?->name ?? str($use->name->name)->afterLast('\\')->toString();
                    $uses[$alias] = $use->name->name;
                }
            }
        }

        // Classed from current namespace imported without declaration
        $files = glob(dirname($this->getFileName()).'/*');

        foreach ($files as $filename) {
            $filename = basename($filename, '.php');
            $uses[$filename] = $namespace->name.'\\'.$filename;
        }

        return $uses;
    }
}
