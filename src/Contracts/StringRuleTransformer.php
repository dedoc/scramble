<?php

namespace Dedoc\Scramble\Contracts;

use Dedoc\Scramble\Contexts\RuleTransformerContext;
use Dedoc\Scramble\Support\Generator\Types\Type as Schema;

interface StringRuleTransformer
{
    public function shouldHandle(string $rule): bool;

    public function toSchema(Schema $previous, string $rule, array $params, RuleTransformerContext $context): Schema;
}
