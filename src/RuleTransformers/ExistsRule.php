<?php

namespace Dedoc\Scramble\RuleTransformers;

use Dedoc\Scramble\Contracts\RuleTransformer;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\Generator\Types\UnknownType;
use Dedoc\Scramble\Support\InferExtensions\ModelExtension;
use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesMapper;
use Dedoc\Scramble\Support\RuleTransforming\NormalizedRule;
use Dedoc\Scramble\Support\RuleTransforming\RuleTransformerContext;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Concerns\ValidatesAttributes;
use Throwable;

class ExistsRule implements RuleTransformer
{
    public function __construct(
        private RulesMapper $rulesMapper,
    ) {}

    public function shouldHandle(NormalizedRule $rule): bool
    {
        return $rule->is('exists');
    }

    public function toSchema(Type $previous, NormalizedRule $rule, RuleTransformerContext $context): Type
    {
        if (! $previous instanceof UnknownType) {
            return $previous;
        }

        if ($schema = $this->getSchemaUsingDbConnection($previous, $rule, $context)) {
            return $schema;
        }

        // fallback to guessing using "id" in column name
        if (Str::is(['id', '*_id'], $this->getColumnName($rule, $context))) {
            return $this->rulesMapper->int($previous);
        }

        return $previous;
    }

    private function getSchemaUsingDbConnection(Type $previous, NormalizedRule $rule, RuleTransformerContext $context): ?Type
    {
        /** @var list<string> $params */
        $params = $rule->getParameters();

        try {
            [$connection, $table] = $this->getConnection(tableOrModel: $params[0]);

            $columns = $connection->getSchemaBuilder()->getColumns($table);
        } catch (Throwable) {
            // @todo collect the error ErrorCollector
            return null;
        }

        $columnData = collect($columns)->firstWhere('name', $this->getColumnName($rule, $context));
        if (! $columnData) {
            return null;
        }

        /** @see ModelExtension */
        $typeName = str($columnData['type'])
            ->before(' ') // strip modifiers from a type name such as `bigint unsigned`
            ->before('(') // strip the length from a type name such as `tinyint(4)`
            ->toString();

        if (in_array($typeName, ['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint'])) {
            return $this->rulesMapper->int($previous);
        }

        return $this->rulesMapper->string($previous);
    }

    private function getColumnName(NormalizedRule $rule, RuleTransformerContext $context): string
    {
        /** @var list<string> $params */
        $params = $rule->getParameters();

        $column = $params[1] ?? 'NULL';

        return $column === 'NULL' ? $context->field : $column;
    }

    /**
     * @return array{0: Connection, 1: string}
     */
    private function getConnection(string $tableOrModel): array
    {
        if (str_contains($tableOrModel, '\\') && class_exists($tableOrModel) && is_a($tableOrModel, Model::class, true)) {
            /**
             * @see ValidatesAttributes::parseTable
             *
             * @var Model $model
             */
            $model = new $tableOrModel;

            return [$model->getConnection(), $model->getTable()];
        }

        return [DB::connection(), $tableOrModel];
    }
}
