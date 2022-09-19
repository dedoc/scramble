<?php

use Dedoc\Scramble\Infer\Infer;
use Dedoc\Scramble\Support\Type\ObjectType;

it('response()->noContent() type', function () {
    /** @var ObjectType $type */
    $type = app(Infer::class)->analyzeClass(Foo_First::class);

    $returnType = $type->getMethodCallType('bar');

    expect($returnType->toString())->toBe("Illuminate\Http\Response<string, int(204), array{}>");
});

class Foo_First {
    public function bar()
    {
        return response()->noContent();
    }
}

it('response()->json() type with default params', function () {
    /** @var ObjectType $type */
    $type = app(Infer::class)->analyzeClass(Foo_Second::class);

    $returnType = $type->getMethodCallType('bar');

    expect($returnType->toString())->toBe("Illuminate\Http\JsonResponse<array{}, int(200), array{}>");
});
class Foo_Second {
    public function bar()
    {
        return response()->json();
    }
}

it('response()->json() type with named params', function () {
    /** @var ObjectType $type */
    $type = app(Infer::class)->analyzeClass(Foo_Third::class);

    $returnType = $type->getMethodCallType('bar');

    expect($returnType->toString())->toBe("Illuminate\Http\JsonResponse<array{}, int(329), array{}>");
});
class Foo_Third {
    public function bar()
    {
        return response()->json(status: 329);
    }
}

it('response()->make() with params', function () {
    /** @var ObjectType $type */
    $type = app(Infer::class)->analyzeClass(Foo_Fourth::class);

    $returnType = $type->getMethodCallType('bar');

    expect($returnType->toString())->toBe("Illuminate\Http\Response<string(Hello), int(200), array{}>");
});
class Foo_Fourth {
    public function bar()
    {
        return response()->make('Hello');
    }
}
