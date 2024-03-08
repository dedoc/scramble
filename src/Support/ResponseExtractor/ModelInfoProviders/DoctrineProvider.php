<?php

namespace Dedoc\Scramble\Support\ResponseExtractor\ModelInfoProviders;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Types\DecimalType;
use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated Needed only for backward compatibility, for L10 and lower versions.
 * @see https://laravel.com/docs/master/upgrade#doctrine-dbal-removal
 *
 * All the code here was written by the great Laravel team and community. Cudos to them.
 */
class DoctrineProvider implements ModelInfoProvider
{
    public function getAttributes(Model $model): array
    {
        $schema = $model->getConnection()->getDoctrineSchemaManager();
        $table = $model->getConnection()->getTablePrefix().$model->getTable();

        $platform = $model->getConnection()
            ->getDoctrineConnection()
            ->getDatabasePlatform();

        $platform->registerDoctrineTypeMapping('enum', 'string');
        $platform->registerDoctrineTypeMapping('geometry', 'string');

        $columns = $schema->listTableColumns($table);
        $indexes = $schema->listTableIndexes($table);

        return collect($columns)
            ->values()
            ->map(fn (Column $column) => [
                'name' => $column->getName(),
                'type' => $this->getColumnType($column),
                'increments' => $column->getAutoincrement(),
                'nullable' => ! $column->getNotnull(),
                'default' => $this->getColumnDefault($column, $model),
                'unique' => $this->columnIsUnique($column->getName(), $indexes),
                'fillable' => $model->isFillable($column->getName()),
                'appended' => null,
            ])
            ->toArray();
    }

    /**
     * Get the type of the given column.
     *
     * @param  \Doctrine\DBAL\Schema\Column  $column
     * @return string
     */
    protected function getColumnType($column)
    {
        $name = $column->getType()->getName();

        $unsigned = $column->getUnsigned() ? ' unsigned' : '';

        $details = get_class($column->getType()) === DecimalType::class
            ? $column->getPrecision().','.$column->getScale()
            : $column->getLength();

        if ($details) {
            return sprintf('%s(%s)%s', $name, $details, $unsigned);
        }

        return sprintf('%s%s', $name, $unsigned);
    }

    /**
     * Get the default value for the given column.
     *
     * @param  \Doctrine\DBAL\Schema\Column  $column
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return mixed|null
     */
    protected function getColumnDefault($column, $model)
    {
        $attributeDefault = $model->getAttributes()[$column->getName()] ?? null;

        return $attributeDefault ?? $column->getDefault();
    }

    /**
     * Determine if the given attribute is unique.
     *
     * @param  string  $column
     * @param  \Doctrine\DBAL\Schema\Index[]  $indexes
     * @return bool
     */
    protected function columnIsUnique($column, $indexes)
    {
        return collect($indexes)
            ->filter(fn (Index $index) => count($index->getColumns()) === 1 && $index->getColumns()[0] === $column)
            ->contains(fn (Index $index) => $index->isUnique());
    }
}
