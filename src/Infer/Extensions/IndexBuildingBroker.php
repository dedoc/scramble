<?php

namespace Dedoc\Scramble\Infer\Extensions;

use Dedoc\Scramble\Support\IndexBuilders\Bag;
use Dedoc\Scramble\Support\IndexBuilders\IndexBuilder;

class IndexBuildingBroker
{
    public function __construct(
        public readonly array $indexBuilders = [],
    ) {}

    public function getIndex(string $builderClassName): Bag
    {
        foreach ($this->indexBuilders as $indexBuilder) {
            if (is_a($indexBuilder, $builderClassName)) {
                return $indexBuilder->bag;
            }
        }

        return new Bag;
    }

    public function handleEvent($event)
    {
        foreach ($this->indexBuilders as $indexBuilder) {
            if ($indexBuilder instanceof IndexBuilder) {
                $indexBuilder->handleEvent($event);
            }
        }
    }
}
