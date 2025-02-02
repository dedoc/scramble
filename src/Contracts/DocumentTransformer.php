<?php

namespace Dedoc\Scramble\Contracts;

use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;

interface DocumentTransformer
{
    public function handle(OpenApi $document, OpenApiContext $context);
}
