<?php
declare(strict_types=1);

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/_test/docs', fn () => response('Access Granted.'))
        ->middleware(RestrictedDocsAccess::class);
    config(['scramble.allowed_environments' => ['local', 'development']]);
});

it('it allows access when the environment is in the configured list', function () {
    $allowes_environments = config('scramble.allowed_environments');
    app()->detectEnvironment(fn () => $allowes_environments[0]);
    $this->get('/_test/docs')->assertOk();
});
