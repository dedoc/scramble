<?php

use Dedoc\Scramble\Support\ResponseExtractor\ModelInfo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

it('handles model without updated_at column', function () {
    $modelInfo = new ModelInfo(UserModelWithoutUpdatedAt::class);

    $modelInfo->handle();
})->expectNotToPerformAssertions();

it('handles relations with array foreign key names', function () {
    $modelInfo = new ModelInfo(RelationNullabilityModel_ModelInfoTest::class);
    $relationIsNullable = new ReflectionMethod($modelInfo, 'relationIsNullable');
    $relationIsNullable->setAccessible(true);

    $relation = new ArrayForeignKeyBelongsTo_ModelInfoTest(new RelationNullabilityModel_ModelInfoTest);

    expect($relationIsNullable->invoke($modelInfo, $relation))->toBeFalse();
});

it('infers nullable relations from nullable foreign key columns', function () {
    $relations = (new ModelInfo(RelationNullabilityModel_ModelInfoTest::class))->handle()->get('relations');

    expect($relations['nullableOwner']['nullable'])->toBeTrue()
        ->and($relations['requiredOwner']['nullable'])->toBeFalse();
});

it('keeps has-one relations nullable unless a default model is configured', function (string $method, bool $withDefault) {
    $modelInfo = new ModelInfo(RelationNullabilityOwner_ModelInfoTest::class);
    $relationIsNullable = new ReflectionMethod($modelInfo, 'relationIsNullable');
    $relationIsNullable->setAccessible(true);

    $relation = (new RelationNullabilityOwner_ModelInfoTest)->$method();

    if ($withDefault) {
        $relation->withDefault();
    }

    expect($relationIsNullable->invoke($modelInfo, $relation))->toBe(! $withDefault);
})->with(['requiredModel', 'morphModel', 'throughModel'])->with([false, true]);

class UserModelWithoutUpdatedAt extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'users';

    const UPDATED_AT = null;
}

class RelationNullabilityOwner_ModelInfoTest extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'relation_nullability_owners';

    public function requiredModel()
    {
        return $this->hasOne(RelationNullabilityModel_ModelInfoTest::class, 'required_owner_id');
    }

    public function morphModel()
    {
        return $this->morphOne(RelationNullabilityModel_ModelInfoTest::class, 'owner');
    }

    public function throughModel()
    {
        return $this->hasOneThrough(
            self::class,
            RelationNullabilityModel_ModelInfoTest::class,
            'required_owner_id',
            'id',
            'id',
            'required_owner_id',
        );
    }
}

class RelationNullabilityModel_ModelInfoTest extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'relation_nullability_models';

    public function requiredOwner()
    {
        return $this->belongsTo(RelationNullabilityOwner_ModelInfoTest::class, 'required_owner_id');
    }

    public function nullableOwner()
    {
        return $this->belongsTo(RelationNullabilityOwner_ModelInfoTest::class, 'nullable_owner_id');
    }
}

class ArrayForeignKeyBelongsTo_ModelInfoTest extends BelongsTo
{
    public function __construct(RelationNullabilityModel_ModelInfoTest $child)
    {
        $this->foreignKey = ['nullable_owner_id', 'required_owner_id'];
        $this->child = $child;
        $this->parent = $child;
        $this->related = new RelationNullabilityOwner_ModelInfoTest;
    }

    public function addConstraints(): void {}
}
