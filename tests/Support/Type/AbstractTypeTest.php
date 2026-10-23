<?php

use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Reference\PotentialMethodMutatingCallType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Union;

it('clones shared type nodes only once per graph', function () {
    $type = new ObjectType('Example');
    for ($i = 0; $i < 8; $i++) {
        $type = new PotentialMethodMutatingCallType($type, $type, 'update', []);
    }

    $cloned = $type->clone();

    while ($type instanceof PotentialMethodMutatingCallType) {
        expect($cloned)->not->toBe($type)
            ->and($cloned->subject)->toBe($cloned->callee);

        $type = $type->subject;
        $cloned = $cloned->subject;
    }

    expect($cloned)->not->toBe($type)
        ->and($cloned->name)->toBe('Example');
});

it('keeps template identities when cloning child nodes', function () {
    $template = new TemplateType('T');
    $type = new Union([$template, new ObjectType('Example')]);

    $cloned = $type->clone();

    expect($cloned)->not->toBe($type)
        ->and($cloned->types[0])->toBe($template)
        ->and($cloned->types[1])->not->toBe($type->types[1]);
});

it('clones recursive graphs without retaining original nodes', function () {
    $type = new Union([new ObjectType('Example')]);
    $type->types[] = $type;

    $cloned = $type->clone();

    expect($cloned)->not->toBe($type)
        ->and($cloned->types[0])->not->toBe($type->types[0])
        ->and($cloned->types[1])->toBe($cloned);
});

it('uses separate clones for independent cloning operations', function () {
    $type = new Union([new ObjectType('Example')]);

    $first = $type->clone();
    $second = $type->clone();

    expect($first)->not->toBe($second)
        ->and($first->types[0])->not->toBe($second->types[0]);
});
