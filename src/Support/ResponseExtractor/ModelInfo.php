<?php

namespace Dedoc\Scramble\Support\ResponseExtractor;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use SplFileObject;
use UnitEnum;

/**
 * All the code here was written by the great Laravel team and community. Cudos to them.
 */
class ModelInfo
{
    public static array $cache = [];

    protected $relationMethods = [
        'hasMany',
        'hasManyThrough',
        'hasOneThrough',
        'belongsToMany',
        'hasOne',
        'belongsTo',
        'morphOne',
        'morphTo',
        'morphMany',
        'morphToMany',
        'morphedByMany',
    ];

    public function __construct(
        private string $class
    ) {}

    public function handle()
    {
        $class = $this->qualifyModel($this->class);

        $reflectionClass = new ReflectionClass($class);
        if (! $reflectionClass->isInstantiable()) {
            return collect([
                'instance' => null,
                'class' => $class,
                'attributes' => collect(),
                'relations' => collect(),
            ]);
        }

        /** @var Model $model */
        $model = app()->make($class);

        return $this->displayJson(
            $model,
            $class,
            $this->getAttributes($model),
            $this->getRelations($model),
        );
    }

    /**
     * Get the column attributes for the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Support\Collection
     */
    protected function getAttributes($model)
    {
        $connection = $model->getConnection();
        $schema = $connection->getSchemaBuilder();
        $table = $model->getTable();
        $columns = $schema->getColumns($table);
        $indexes = $schema->getIndexes($table);

        return collect($columns)
            ->values()
            ->map(fn ($column) => [
                'driver' => $connection->getDriverName(),
                'name' => $column['name'],
                'type' => $column['type'],
                'increments' => $column['auto_increment'],
                'nullable' => $column['nullable'],
                'default' => $this->getColumnDefault($column, $model),
                'unique' => $this->columnIsUnique($column['name'], $indexes),
                'fillable' => $model->isFillable($column['name']),
                'appended' => null,
                'hidden' => $this->attributeIsHidden($column['name'], $model),
                'cast' => $this->getCastType($column['name'], $model),
            ])
            ->merge($this->getVirtualAttributes($model, $columns))
            ->keyBy('name');
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

    /**
     * Get the virtual (non-column) attributes for the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  array[]  $columns
     * @return \Illuminate\Support\Collection
     */
    protected function getVirtualAttributes($model, $columns)
    {
        $class = new ReflectionClass($model);

        $keyedColumns = collect($columns)->keyBy('name');

        return collect($class->getMethods())
            ->reject(
                fn (ReflectionMethod $method) => $method->isStatic()
                    || $method->isAbstract()
                    || $method->getDeclaringClass()->getName() !== get_class($model)
            )
            ->mapWithKeys(function (ReflectionMethod $method) use ($model) {
                if (preg_match('/^get(.*)Attribute$/', $method->getName(), $matches) === 1) {
                    return [Str::snake($matches[1]) => 'accessor'];
                } elseif ($model->hasAttributeMutator($method->getName())) {
                    return [Str::snake($method->getName()) => 'attribute'];
                } else {
                    return [];
                }
            })
            ->reject(fn ($cast, $name) => $keyedColumns->has($name))
            ->map(fn ($cast, $name) => [
                'driver' => null,
                'name' => $name,
                'type' => null,
                'increments' => false,
                'nullable' => null,
                'default' => null,
                'unique' => null,
                'fillable' => $model->isFillable($name),
                'hidden' => $this->attributeIsHidden($name, $model),
                'appended' => $model->hasAppended($name),
                'cast' => $cast,
            ])
            ->values();
    }

    /**
     * Get the relations from the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Support\Collection
     */
    protected function getRelations($model)
    {
        return collect(get_class_methods($model))
            ->map(fn ($method) => new ReflectionMethod($model, $method))
            ->reject(
                fn (ReflectionMethod $method) => $method->isStatic()
                    || $method->isAbstract()
                    || $method->getDeclaringClass()->getName() === Model::class,
            )
            ->filter(function (ReflectionMethod $method) {
                $file = new SplFileObject($method->getFileName());
                $file->seek($method->getStartLine() - 1);
                $code = '';
                while ($file->key() < $method->getEndLine()) {
                    $code .= $file->current();
                    $file->next();
                }

                return collect($this->relationMethods)
                    ->contains(fn ($relationMethod) => str_contains($code, '$this->'.$relationMethod.'('));
            })
            ->map(function (ReflectionMethod $method) use ($model) {
                try {
                    $relation = $method->invoke($model);
                } catch (\Throwable $e) {
                    // @todo: add verbosity levels for debugging
                    return null;
                }

                if (! $relation instanceof Relation) {
                    return null;
                }

                return [
                    'name' => $method->getName(),
                    'type' => Str::afterLast(get_class($relation), '\\'),
                    'related' => get_class($relation->getRelated()),
                ];
            })
            ->filter()
            ->values()
            ->keyBy('name');
    }

    /**
     * Render the model information as JSON.
     */
    protected function displayJson($model, $class, $attributes, $relations)
    {
        return collect([
            'instance' => $model,
            'class' => $class,
            'attributes' => $attributes,
            'relations' => $relations,
        ]);
    }

    /**
     * Get the cast type for the given column.
     *
     * @param  string  $column
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return string|null
     */
    protected function getCastType($column, $model)
    {
        if ($model->hasGetMutator($column) || $model->hasSetMutator($column)) {
            return 'accessor';
        }

        if ($model->hasAttributeMutator($column)) {
            return 'attribute';
        }

        return $this->getCastsWithDates($model)->get($column) ?? null;
    }

    /**
     * Get the model casts, including any date casts.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Support\Collection
     */
    protected function getCastsWithDates($model)
    {
        return collect($model->getDates())
            ->filter()
            ->flip()
            ->map(fn () => 'datetime')
            ->merge($model->getCasts());
    }

    /**
     * Determine if the given attribute is hidden.
     *
     * @param  string  $attribute
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    protected function attributeIsHidden($attribute, $model)
    {
        if (count($model->getHidden()) > 0) {
            return in_array($attribute, $model->getHidden());
        }

        if (count($model->getVisible()) > 0) {
            return ! in_array($attribute, $model->getVisible());
        }

        return false;
    }

    /**
     * Qualify the given model class base name.
     *
     * @return string
     *
     * @see \Illuminate\Console\GeneratorCommand
     */
    protected function qualifyModel(string $model)
    {
        if (class_exists($model)) {
            return $model;
        }

        if (str_contains($model, '\\') && class_exists($model)) {
            return $model;
        }

        $model = ltrim($model, '\\/');

        $model = str_replace('/', '\\', $model);

        $rootNamespace = app()->getNamespace();

        if (Str::startsWith($model, $rootNamespace)) {
            return $model;
        }

        return is_dir(app_path('Models'))
            ? $rootNamespace.'Models\\'.$model
            : $rootNamespace.$model;
    }
}
