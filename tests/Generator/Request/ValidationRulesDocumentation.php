<?php

use Dedoc\Scramble\Support\OperationExtensions\RulesExtractor\RulesToParameters;
use Illuminate\Support\Arr;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

it('supports confirmed rule', function () {
    $rules = [
        'password' => ['required', 'min:8', 'confirmed'],
    ];

    $params = app()->make(RulesToParameters::class, ['rules' => $rules])->handle();

    expect($params = collect($params)->map->toArray()->all())
        ->toHaveCount(2)
        ->and($params[1])
        ->toMatchArray(['name' => 'password_confirmation']);
});
//
//it('works', function () {
//    dd(
//        resolve(\Dedoc\Scramble\Infer\Services\FileParser::class)
//            ->parse(
//                (new ReflectionClass(\Dedoc\Scramble\Support\ClassAstHelper::class))->getFileName()
//            )
//    );
//});
