<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\IntegerRangeType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Pagination\CursorPaginator;

class AfterCursorPaginatorDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === CursorPaginator::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $definition = $event->classDefinition;

        /** Laravel 10.x compatibility layer */
        if (! collect($definition->templateTypes)->firstWhere('name', 'TKey')) {
            $definition->templateTypes[] = new TemplateType('TKey');
        }
        if (! $tValue = collect($definition->templateTypes)->firstWhere('name', 'TValue')) {
            $tValue = $definition->templateTypes[] = new TemplateType('TValue');
        }

        $definition->methods['toArray'] = new ShallowFunctionDefinition(
            type: new FunctionType(
                name: 'toArray',
                arguments: [],
                returnType: new KeyedArrayType([
                    new ArrayItemType_('data', new ArrayType($tValue)),
                    tap(new ArrayItemType_('path', new Union([new StringType, new NullType])), function (ArrayItemType_ $t) {
                        $t->setAttribute('docNode', PhpDoc::parse('/** Base path for paginator generated URLs. */'));
                    }),
                    tap(new ArrayItemType_('per_page', new IntegerRangeType(min: 0)), function (ArrayItemType_ $t) {
                        $t->setAttribute('docNode', PhpDoc::parse('/** Number of items shown per page. */'));
                    }),
                    tap(new ArrayItemType_('next_cursor', new Union([new StringType, new NullType])), function (ArrayItemType_ $t) {
                        $t->setAttribute('docNode', PhpDoc::parse('/** The "cursor" that points to the next set of items. */'));
                    }),
                    tap(new ArrayItemType_('next_page_url', new Union([new StringType, new NullType])), function (ArrayItemType_ $t) {
                        $t->setAttribute('docNode', PhpDoc::parse('/** @format uri */'));
                    }),
                    tap(new ArrayItemType_('prev_cursor', new Union([new StringType, new NullType])), function (ArrayItemType_ $t) {
                        $t->setAttribute('docNode', PhpDoc::parse('/** The "cursor" that points to the previous set of items. */'));
                    }),
                    tap(new ArrayItemType_('prev_page_url', new Union([new StringType, new NullType])), function (ArrayItemType_ $t) {
                        $t->setAttribute('docNode', PhpDoc::parse('/** @format uri */'));
                    }),
                ]),
            ),
            definingClassName: CursorPaginator::class,
        );
    }
}
