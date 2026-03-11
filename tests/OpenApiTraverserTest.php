<?php

use Dedoc\Scramble\AbstractOpenApiVisitor;
use Dedoc\Scramble\Support\Generator\InfoObject;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Path;

it('traverses open api document', function () {
    $document = new OpenApi(version: '3.1.0');
    $document->setInfo(new InfoObject(title: 'app'));
    $document->addPath($path = new Path('/test'));
    $path->addOperation(new Operation('GET'));

    $traverser = new \Dedoc\Scramble\OpenApiTraverser([
        $visitor = new class extends AbstractOpenApiVisitor
        {
            public array $paths = [];

            public function enter($object, $path = [])
            {
                $this->paths[] = implode('.', $path);
            }
        },
    ]);

    $traverser->traverse($document);

    expect($visitor->paths)->toBe([
        '',
        'info',
        'components',
        'paths.0',
        'paths.0.operations.GET',
    ]);
});
