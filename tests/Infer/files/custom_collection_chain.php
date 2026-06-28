<?php

use Illuminate\Database\Eloquent\Collection;

class CustomCollectionChainTest_Collection extends Collection
{
    public function published()
    {
        return $this->whereNotNull('published_at');
    }

    public function featured()
    {
        return $this->where('is_featured', 1);
    }

    public function publishedFeatured()
    {
        return $this->featured()->published()->where('is_featured', 1)->where('is_featured', 1)->where('is_featured', 1);
    }
}
