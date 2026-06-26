<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Dedoc\Scramble\Infer\Extensions\AfterClassDefinitionCreatedExtension;
use Dedoc\Scramble\Infer\Extensions\Event\ClassDefinitionCreatedEvent;
use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\FunctionType;
use Dedoc\Scramble\Support\Type\IntegerRangeType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Pagination\LengthAwarePaginator;

class AfterLengthAwarePaginatorDefinitionCreatedExtension implements AfterClassDefinitionCreatedExtension
{
    public function shouldHandle(string $name): bool
    {
        return $name === LengthAwarePaginator::class;
    }

    public function afterClassDefinitionCreated(ClassDefinitionCreatedEvent $event): void
    {
        $definition = $event->classDefinition;

        $tValue = collect($definition->templateTypes)->firstOrFail('name', 'TValue');

        $definition->methods['toArray'] = new ShallowFunctionDefinition(
            type: new FunctionType(
                name: 'toArray',
                arguments: [],
                returnType: new KeyedArrayType([
                    new ArrayItemType_('current_page', new IntegerRangeType(min: 1)),
                    new ArrayItemType_('data', new ArrayType($tValue)),
                    new ArrayItemType_('first_page_url', new Union([new StringType, new NullType])),
                    new ArrayItemType_('from', new Union([new IntegerRangeType(min: 1), new NullType])),
                    new ArrayItemType_('last_page_url', new Union([new StringType, new NullType])),
                    new ArrayItemType_('last_page', new IntegerRangeType(min: 1)),
                    tap(new ArrayItemType_('links', new ArrayType), function (ArrayItemType_ $t) {
                        $t->setAttribute('docNode', PhpDoc::parse('/** Generated paginator links. */'));
                        $linkObject = new KeyedArrayType([
                            new ArrayItemType_('url', new Union([new StringType, new NullType])),
                            new ArrayItemType_('label', new StringType),
                            new ArrayItemType_('active', new BooleanType),
                        ]);
                        $t->value = new ArrayType($linkObject);
                    }),
                    new ArrayItemType_('next_page_url', new Union([new StringType, new NullType])),
                    tap(new ArrayItemType_('path', new Union([new StringType, new NullType])), function (ArrayItemType_ $t) {
                        $t->setAttribute('docNode', PhpDoc::parse('/** Base path for paginator generated URLs. */'));
                    }),
                    tap(new ArrayItemType_('per_page', new IntegerRangeType(min: 0)), function (ArrayItemType_ $t) {
                        $t->setAttribute('docNode', PhpDoc::parse('/** Number of items shown per page. */'));
                    }),
                    new ArrayItemType_('prev_page_url', new Union([new StringType, new NullType])),
                    tap(new ArrayItemType_('to', new Union([new IntegerRangeType(min: 1), new NullType])), function (ArrayItemType_ $t) {
                        $t->setAttribute('docNode', PhpDoc::parse('/** Number of the last item in the slice. */'));
                    }),
                    tap(new ArrayItemType_('total', new IntegerRangeType(min: 0)), function (ArrayItemType_ $t) {
                        $t->setAttribute('docNode', PhpDoc::parse('/** Total number of items being paginated. */'));
                    }),
                ]),
            ),
            definingClassName: LengthAwarePaginator::class,
        );
    }
}
