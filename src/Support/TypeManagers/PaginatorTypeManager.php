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
use Illuminate\Pagination\Paginator;

/**
 * @see Paginator
 */
class PaginatorTypeManager
{
    public function getToArrayType(Type $dataType): KeyedArrayType
    {
        return new KeyedArrayType([
            new ArrayItemType_('current_page', new IntegerType),
            new ArrayItemType_('data', $dataType),
            new ArrayItemType_('first_page_url', new Union([new StringType, new NullType])),
            new ArrayItemType_('from', new Union([new IntegerType, new NullType])),
            new ArrayItemType_('next_page_url', new Union([new StringType, new NullType])),
            tap(new ArrayItemType_('path', new Union([new StringType, new NullType])), function (ArrayItemType_ $t) {
                $t->setAttribute('docNode', PhpDoc::parse('/** Base path for paginator generated URLs. */'));
            }),
            tap(new ArrayItemType_('per_page', new IntegerType), function (ArrayItemType_ $t) {
                $t->setAttribute('docNode', PhpDoc::parse('/** Number of items shown per page. */'));
            }),
            new ArrayItemType_('prev_page_url', new Union([new StringType, new NullType])),
            tap(new ArrayItemType_('to', new Union([new IntegerType, new NullType])), function (ArrayItemType_ $t) {
                $t->setAttribute('docNode', PhpDoc::parse('/** Number of the last item in the slice. */'));
            }),
        ]);
    }
}
