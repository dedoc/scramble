<?php

namespace Dedoc\Scramble\Support\ResponseExtractor;

use Dedoc\Scramble\Infer\Definition\ClassDefinition;
use Dedoc\Scramble\Infer\Definition\ClassPropertyDefinition;
use Dedoc\Scramble\Support\Type\ArrayType;
use Dedoc\Scramble\Support\Type\BooleanType;
use Dedoc\Scramble\Support\Type\FloatType;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\IntegerType;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Union;
use Dedoc\Scramble\Support\Type\UnknownType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Types\DecimalType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use SplFileObject;

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

    private string $class;

    public function __construct(string $class)
    {
        $this->class = $class;
    }

    public function handle()
    {
        $class = $this->qualifyModel($this->class);

        /** @var Model $model */
        $model = app()->make($class);

        return $this->displayJson(
            $model,
            $class,
            $this->getAttributes($model),
            $this->getRelations($model),
        );
    }

    public function type()
    {
        if (isset(static::$cache[$this->class])) {
            return static::$cache[$this->class];
        }

        if (! class_exists(Column::class)) {
            throw new \LogicException('`doctrine/dbal` package is not installed. It is needed to get model attribute types.');
        }

        $modelInfo = $this->handle();

        /** @var Model $model */
        $model = app()->make($modelInfo->get('class'));

        /** @var Collection $properties */
        $properties = $modelInfo->get('attributes')
            ->map(function ($value, $key) use ($model) {
                $isNullable = $value['nullable'];
                $createType = fn ($t) => $isNullable
                    ? Union::wrap([new NullType(), $t])
                    : $t;

                $type = explode(' ', $value['type']);
                $typeName = explode('(', $type[0])[0];

                if (in_array($key, $model->getDates())) {
                    return $createType(new ObjectType('\\Carbon\\Carbon'));
                }

                $types = [
                    'int' => new IntegerType(),
                    'integer' => new IntegerType(),
                    'bigint' => new IntegerType(),
                    'float' => new FloatType(),
                    'double' => new FloatType(),
                    'decimal' => new FloatType(),
                    'string' => new StringType(),
                    'text' => new StringType(),
                    'datetime' => new StringType(),
                    'bool' => new BooleanType(),
                    'boolean' => new BooleanType(),
                    'json' => new ArrayType(),
                    'array' => new ArrayType(),
                ];

                $attributeType = null;

                if (array_key_exists($typeName, $types)) {
                    $attributeType = $createType($types[$typeName]);
                }

                if ($attributeType && $value['cast'] && function_exists('enum_exists') && enum_exists($value['cast'])) {
                    if (! isset($value['cast']::cases()[0]->value)) {
                        return $attributeType;
                    }

                    $attributeType = new ObjectType($value['cast']);
                }

                return $attributeType ?: new UnknownType("unimplemented DB column type [$type[0]]");
            });

        $relations = $modelInfo->get('relations')
            ->map(function ($relation) {
                if ($isManyRelation = Str::contains($relation['type'], 'Many')) {
                    return new Generic(
                        \Illuminate\Database\Eloquent\Collection::class,
                        [
                            new ObjectType($relation['related']),
                        ]
                    );
                }

                return new ObjectType($relation['related']);
            });

        return static::$cache[$this->class] = new ClassDefinition(
            name: $modelInfo->get('class'),
            properties: $properties->merge($relations)->map(fn ($t) => new ClassPropertyDefinition($t))->all(),
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
                'hidden' => $this->attributeIsHidden($column->getName(), $model),
                'appended' => null,
                'cast' => $this->getCastType($column->getName(), $model),
            ])
            ->merge($this->getVirtualAttributes($model, $columns))
            ->keyBy('name');
    }

    /**
     * Get the virtual (non-column) attributes for the given model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Doctrine\DBAL\Schema\Column[]  $columns
     * @return \Illuminate\Support\Collection
     */
    protected function getVirtualAttributes($model, $columns)
    {
        $class = new ReflectionClass($model);

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
            ->reject(fn ($cast, $name) => collect($columns)->has($name))
            ->map(fn ($cast, $name) => [
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
                    || $method->getDeclaringClass()->getName() !== get_class($model)
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
