<?php

use Dedoc\Scramble\Infer\Services\TemplatesMap;
use Dedoc\Scramble\Infer\UnresolvableArgumentTypeBag;
use Dedoc\Scramble\Support\Type\ArrayItemType_;
use Dedoc\Scramble\Support\Type\KeyedArrayType;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\Literal\LiteralStringType;
use Dedoc\Scramble\Support\Type\TemplateType;

it('infers TParam as union when template appears in array shape with mixed element types', function () {
    $tParam = new TemplateType('TParam');

    $templatesMap = new TemplatesMap(
        templates: [$tParam],
        parameters: [
            'p' => new KeyedArrayType([
                new ArrayItemType_(null, $tParam),
                new ArrayItemType_(null, $tParam),
            ]),
        ],
        arguments: new UnresolvableArgumentTypeBag([
            'p' => new KeyedArrayType([
                new ArrayItemType_(null, new LiteralIntegerType(42)),
                new ArrayItemType_(null, new LiteralStringType('str')),
            ]),
        ]),
        defaults: [],
    );

    expect($templatesMap->get('TParam')->toString())->toBe('int(42)|string(str)');
});

it('infers TParam as union when template appears in multiple parameters with different argument types', function () {
    $tParam = new TemplateType('TParam');

    $templatesMap = new TemplatesMap(
        templates: [$tParam],
        parameters: [
            'p' => $tParam,
            'q' => $tParam,
        ],
        arguments: new UnresolvableArgumentTypeBag([
            'p' => new LiteralIntegerType(42),
            'q' => new LiteralStringType('str'),
        ]),
        defaults: [],
    );

    expect($templatesMap->get('TParam')->toString())->toBe('int(42)|string(str)');
});
