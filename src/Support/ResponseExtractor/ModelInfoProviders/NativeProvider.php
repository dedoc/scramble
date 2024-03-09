<?php

namespace Dedoc\Scramble\Support\ResponseExtractor\ModelInfoProviders;

use Illuminate\Database\Eloquent\Model;
use UnitEnum;
use BackedEnum;

/**
 * All the code here was written by the great Laravel team and community. Cudos to them.
 */
class NativeProvider implements ModelInfoProvider
{
    public function getAttributes(Model $model): array
    {
        $connection = $model->getConnection();
        $schema = $connection->getSchemaBuilder();
        $table = $model->getTable();
        $columns = $schema->getColumns($table);
        $indexes = $schema->getIndexes($table);

        return collect($columns)
            ->values()
            ->map(fn ($column) => [
                'name' => $column['name'],
                'type' => $column['type'],
                'increments' => $column['auto_increment'],
                'nullable' => $column['nullable'],
                'default' => $this->getColumnDefault($column, $model),
                'unique' => $this->columnIsUnique($column['name'], $indexes),
                'fillable' => $model->isFillable($column['name']),
                'appended' => null,
            ])
            ->toArray();
    }

    private function getColumnDefault($column, Model $model)
    {
        $attributeDefault = $model->getAttributes()[$column['name']] ?? null;

        return match (true) {
            $attributeDefault instanceof BackedEnum => $attributeDefault->value,
            $attributeDefault instanceof UnitEnum => $attributeDefault->name,
            default => $attributeDefault ?? $column['default'],
        };
    }

    private function columnIsUnique($column, array $indexes)
    {
        return collect($indexes)->contains(
            fn ($index) => count($index['columns']) === 1 && $index['columns'][0] === $column && $index['unique']
        );
    }
}
