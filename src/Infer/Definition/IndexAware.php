<?php

namespace Dedoc\Scramble\Infer\Definition;

use Dedoc\Scramble\Infer\Scope\Index;

trait IndexAware
{
    public function getIndex(): Index
    {
        return app(Index::class);
    }
}
