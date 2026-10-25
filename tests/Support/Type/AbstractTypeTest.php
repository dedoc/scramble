<?php

use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\TemplateType;
use Dedoc\Scramble\Support\Type\Union;

it('keeps template identities when cloning child nodes', function () {
    $template = new TemplateType('T');
    $type = new Union([$template, new ObjectType('Example')]);

    $cloned = $type->clone();

    expect($cloned)->not->toBe($type)
        ->and($cloned->types[0])->toBe($template)
        ->and($cloned->types[1])->not->toBe($type->types[1]);
});

it('uses separate clones for independent cloning operations', function () {
    $type = new Union([new ObjectType('Example')]);

    $first = $type->clone();
    $second = $type->clone();

    expect($first)->not->toBe($second)
        ->and($first->types[0])->not->toBe($second->types[0]);
});
