<?php

namespace Dedoc\Scramble\Contracts;

use Dedoc\Scramble\Contexts\RuleTransformerContext;
use Dedoc\Scramble\Support\Generator\Types\Type as Schema;

interface RuleTransformer
{
    public function shouldHandle(object $rule): bool;

    public function toSchema(Schema $previous, object $rule, RuleTransformerContext $context): Schema;
}
