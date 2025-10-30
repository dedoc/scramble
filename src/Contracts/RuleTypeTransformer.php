<?php

namespace Dedoc\Scramble\Contracts;

use Dedoc\Scramble\Contexts\RuleTransformerContext;
use Dedoc\Scramble\Support\Generator\Types\Type as Schema;
use Dedoc\Scramble\Support\Type\Type;

interface RuleTypeTransformer
{
    public function shouldHandle(Type $rule): bool;

    public function toSchema(Schema $previous, Type $rule, RuleTransformerContext $context): Schema;
}
