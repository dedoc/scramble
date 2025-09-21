<?php

namespace Dedoc\Scramble\Support\TypeManagers;

use Dedoc\Scramble\Support\PhpDoc;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;
use Illuminate\Pagination\CursorPaginator;

/**
 * @see CursorPaginator
 */
class CursorPaginatorTypeManager
{
    public function getToArrayType(Type $dataType): KeyedArrayType
    {
        return new KeyedArrayType([
            new ArrayItemType_('data', $dataType),
            tap(new ArrayItemType_('path', new Union([new StringType, new NullType])), function (ArrayItemType_ $t) {
                $t->setAttribute('docNode', PhpDoc::parse('/** Base path for paginator generated URLs. */'));
            }),
            tap(new ArrayItemType_('per_page', new IntegerType), function (ArrayItemType_ $t) {
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
        ]);
    }
}
