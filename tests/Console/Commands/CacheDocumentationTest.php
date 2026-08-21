<?php

use Dedoc\Scramble\CacheableGenerator;
use Dedoc\Scramble\Console\Commands\CacheDocumentation;
use Dedoc\Scramble\Console\Commands\ClearDocumentationCache;
use Dedoc\Scramble\Diagnostics\DiagnosticSeverity;
use Dedoc\Scramble\Diagnostics\GenericDiagnostic;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\GeneratorResult;
use Dedoc\Scramble\OldGeneratorResult;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\ProNudge\ProNudgeSignal;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\artisan;

beforeEach(function () {
    config()->set('scramble.cache', [
        'key' => 'scramble.openapi.test',
        'store' => 'array',
    ]);

    Scramble::configure()->useConfig(config('scramble'));
});

it('returns cached documentation when cache is configured', function () {
    $generator = app(Generator::class);
    $cacheableGenerator = app(CacheableGenerator::class);
    $config = Scramble::getGeneratorConfig(Scramble::DEFAULT_API);

    $expected = $generator->generate($config);
    $expected->diagnostics()->push(
        new GenericDiagnostic(DiagnosticSeverity::Error, 'Cached diagnostic')
    );
    $expected->proNudge()->record(
        ProNudgeSignal::QueryBuilder,
        new RouteInfo(Route::get('/users', fn () => []), 'GET'),
    );

    CacheableGenerator::store($config, $expected);

    $actual = $cacheableGenerator->generate($config);

    expect($actual)->toBe($expected)
        ->and($cacheableGenerator($config))->toBe($expected->spec())
        ->and($actual->openApi())->toBe($expected->openApi())
        ->and($actual->diagnostics())->toHaveCount(1)
        ->and($actual->proNudge()->message())->not->toBeNull();
});

it('returns documentation cached by an older Scramble version', function () {
    $cacheableGenerator = app(CacheableGenerator::class);
    $config = Scramble::getGeneratorConfig(Scramble::DEFAULT_API);
    $oldSpec = ['openapi' => '3.1.0'];

    Cache::store('array')->forever('scramble.openapi.test:'.Scramble::DEFAULT_API, $oldSpec);

    $actual = $cacheableGenerator->generate($config);

    expect($actual)->toBeInstanceOf(OldGeneratorResult::class)
        ->and($actual->spec())->toBe($oldSpec)
        ->and($actual->openApi())->toBeInstanceOf(OpenApi::class)
        ->and($actual->openApi()->version)->toBe('3.1.0')
        ->and($cacheableGenerator($config))->toBe($oldSpec)
        ->and($actual->diagnostics())->toHaveCount(1)
        ->and($actual->diagnostics()->sole()->severity())->toBe(DiagnosticSeverity::Warning)
        ->and($actual->diagnostics()->sole()->message())->toContain('php artisan scramble:cache')
        ->and($actual->proNudge()->message())->toBeNull();
});

it('generates documentation on cache miss without storing', function () {
    $generator = app(Generator::class);
    $cacheableGenerator = app(CacheableGenerator::class);
    $config = Scramble::getGeneratorConfig(Scramble::DEFAULT_API);

    expect($cacheableGenerator($config))->toBe($generator($config));
    expect(Cache::store('array')->has('scramble.openapi.test:'.Scramble::DEFAULT_API))->toBeFalse();
});

it('uses api-specific cache keys for registered apis', function () {
    $api = 'v2';

    Scramble::registerApi($api, [
        'api_path' => 'api/v2',
    ]);

    artisan(CacheDocumentation::class, ['--api' => [$api]])->assertOk();

    expect(Cache::store('array')->has("scramble.openapi.test:{$api}"))->toBeTrue();
});

it('caches documentation for all apis by default', function () {
    $api = 'v2';

    Scramble::registerApi($api, [
        'api_path' => 'api/v2',
    ]);

    artisan(CacheDocumentation::class)->assertOk();

    expect(Cache::store('array')->has('scramble.openapi.test:'.Scramble::DEFAULT_API))->toBeTrue()
        ->and(Cache::store('array')->has("scramble.openapi.test:{$api}"))->toBeTrue();
});

it('caches documentation using scramble:cache command', function () {
    $generator = app(Generator::class);
    $config = Scramble::getGeneratorConfig(Scramble::DEFAULT_API);
    $expected = $generator->generate($config);

    artisan(CacheDocumentation::class, ['--api' => [Scramble::DEFAULT_API]])->assertOk();

    $cached = Cache::store('array')->get('scramble.openapi.test:'.Scramble::DEFAULT_API);

    expect($cached)->toBeInstanceOf(GeneratorResult::class)
        ->and(Cache::store('array')->get('scramble.openapi.test:'.Scramble::DEFAULT_API.':_version'))
        ->toBe(CacheableGenerator::CACHE_VERSION)
        ->and($cached->spec())->toBe($expected->spec());
});

it('clears documentation cache using scramble:clear command', function () {
    Cache::store('array')->forever('scramble.openapi.test:'.Scramble::DEFAULT_API, ['openapi' => '3.1.0']);
    Cache::store('array')->forever('scramble.openapi.test:'.Scramble::DEFAULT_API.':_version', CacheableGenerator::CACHE_VERSION);

    expect(Cache::store('array')->has('scramble.openapi.test:'.Scramble::DEFAULT_API))->toBeTrue()
        ->and(Cache::store('array')->has('scramble.openapi.test:'.Scramble::DEFAULT_API.':_version'))->toBeTrue();

    artisan(ClearDocumentationCache::class, ['--api' => [Scramble::DEFAULT_API]])->assertOk();

    expect(Cache::store('array')->has('scramble.openapi.test:'.Scramble::DEFAULT_API))->toBeFalse()
        ->and(Cache::store('array')->has('scramble.openapi.test:'.Scramble::DEFAULT_API.':_version'))->toBeFalse();
});

it('clears documentation cache for all apis by default', function () {
    $api = 'v2';

    Scramble::registerApi($api, [
        'api_path' => 'api/v2',
    ]);

    Cache::store('array')->forever('scramble.openapi.test:'.Scramble::DEFAULT_API, ['openapi' => '3.1.0']);
    Cache::store('array')->forever('scramble.openapi.test:'.Scramble::DEFAULT_API.':_version', CacheableGenerator::CACHE_VERSION);
    Cache::store('array')->forever("scramble.openapi.test:{$api}", ['openapi' => '3.1.0']);
    Cache::store('array')->forever("scramble.openapi.test:{$api}:_version", CacheableGenerator::CACHE_VERSION);

    artisan(ClearDocumentationCache::class)->assertOk();

    expect(Cache::store('array')->has('scramble.openapi.test:'.Scramble::DEFAULT_API))->toBeFalse()
        ->and(Cache::store('array')->has('scramble.openapi.test:'.Scramble::DEFAULT_API.':_version'))->toBeFalse()
        ->and(Cache::store('array')->has("scramble.openapi.test:{$api}"))->toBeFalse()
        ->and(Cache::store('array')->has("scramble.openapi.test:{$api}:_version"))->toBeFalse();
});

it('returns early when cache store is not configured', function () {
    config()->set('scramble.cache.store', null);

    artisan(CacheDocumentation::class)
        ->assertOk()
        ->expectsOutput('Documentation cache store is not configured. Set `scramble.cache.store` in your config.');
});

it('returns early when cache key is not configured', function () {
    config()->set('scramble.cache.key', null);

    artisan(ClearDocumentationCache::class)
        ->assertOk()
        ->expectsOutput('Documentation cache key is not configured. Set `scramble.cache.key` in your config.');
});
