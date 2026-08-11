<?php

namespace Dedoc\Scramble\Contracts;

use Dedoc\Scramble\Configuration\SecurityDocumentationContext;
use Dedoc\Scramble\GeneratorConfig;

interface SecurityDocumentationStrategy
{
    public function configure(SecurityDocumentationContext $context): GeneratorConfig;
}
