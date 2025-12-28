<?php

namespace Dedoc\Scramble\RuleTransformers;

use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesMapper;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Dedoc\Scramble\Support\Type\IntegerType;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Illuminate\Validation\Rule;

class ExistsRule implements RuleTransformer
{
    public function __construct(
        private RulesMapper $rulesMapper,
    )
    {
    }

    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->is('exists');
    }

    public function toSchema(Type $previous, NormalizedRule $rule, RuleTransformerContext $context): Type
    {
        if (! $previous instanceof UnknownType) {
            return $previous;
        }

        /** @var list<string> $params */
        $params = $rule->getParameters();

        [$connection, $table] = $this->getConnection(tableOrModel: $params[0]);

        $columns = $connection->getSchemaBuilder()->getColumns($table);

        $column = $params[1] ?? 'NULL';
        $column = $column === 'NULL' ? $context->field : $column;

        $columnData = collect($columns)->firstWhere('name', $column);
        if (! $columnData) {
            return $previous;
        }

        $typeName = str($columnData['type'])
            ->before(' ') // strip modifiers from a type name such as `bigint unsigned`
            ->before('(') // strip the length from a type name such as `tinyint(4)`
            ->toString();

        if (in_array($typeName, ['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint'])) {
            return $this->rulesMapper->int($previous);
        }

        return $this->rulesMapper->string($previous);
    }

    /**
     * @return array{0: Connection, 1: string}
     */
    private function getConnection(string $tableOrModel): array
    {
        if (is_a($tableOrModel, Model::class, true)) {
            /** @var Model $model */
            $model = app()->make($tableOrModel);

            return [$model->getConnection(), $model->getTable()];
        }

        return [DB::connection(), $tableOrModel];
    }
}
