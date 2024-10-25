<?php

use Dedoc\Scramble\Scramble;

Scramble::registerUiRoute(config('scramble.path', 'docs/api'))->name('scramble.docs.ui');

Scramble::registerJsonSpecificationRoute(path: 'docs/api.json')->name('scramble.docs.document');
