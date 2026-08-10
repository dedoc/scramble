<?php

use Dedoc\Scramble\Support\ProNudge\ProNudgeCollector;
use Dedoc\Scramble\Support\ProNudge\ProNudgeReporter;
use Dedoc\Scramble\Support\ProNudge\ProNudgeSignal;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

it('records unique endpoints per signal', function () {
    $collector = new ProNudgeCollector;

    $routeInfo = new RouteInfo(new Route('GET', 'users', ['uses' => 'UsersController@index']), 'GET');
    $otherRouteInfo = new RouteInfo(new Route('POST', 'users', ['uses' => 'UsersController@store']), 'POST');

    $collector->record(ProNudgeSignal::LaravelDataReturn, $routeInfo);
    $collector->record(ProNudgeSignal::LaravelDataReturn, $routeInfo);
    $collector->record(ProNudgeSignal::LaravelDataReturn, $otherRouteInfo);
    $collector->record(ProNudgeSignal::QueryBuilder, $routeInfo);

    expect($collector->count(ProNudgeSignal::LaravelDataReturn))->toBe(2)
        ->and($collector->count(ProNudgeSignal::QueryBuilder))->toBe(1)
        ->and($collector->count(ProNudgeSignal::LaravelDataRequest))->toBe(0)
        ->and($collector->hasAny())->toBeTrue();
});

it('reports both packages with a combined pitch', function () {
    $collector = new ProNudgeCollector;
    $routeInfo = new RouteInfo(new Route('GET', 'users', ['uses' => 'UsersController@index']), 'GET');

    $collector->record(ProNudgeSignal::QueryBuilder, $routeInfo);
    $collector->record(ProNudgeSignal::LaravelDataReturn, $routeInfo);
    $collector->record(ProNudgeSignal::LaravelDataRequest, $routeInfo);

    $command = makeProNudgeTestCommand($output = new BufferedOutput);

    (new ProNudgeReporter($collector))->report($command);

    $rendered = $output->fetch();

    expect($rendered)
        ->toContain('Scramble detected:')
        ->toContain('  • 1 endpoint uses Spatie Query Builder')
        ->toContain('  • 1 endpoint returns Laravel Data objects')
        ->toContain('  • 1 endpoint accepts Laravel Data objects')
        ->toContain('Scramble PRO understands these packages and automatically documents:')
        ->toContain('  • Query Builder filters, sorts, includes, and sparse fieldsets')
        ->toContain('  • Laravel Data request and response schemas')
        ->toContain('Learn more: '.ProNudgeReporter::PRO_URL);
});

it('reports a query builder only pitch', function () {
    $collector = new ProNudgeCollector;
    $routeInfo = new RouteInfo(new Route('GET', 'users', ['uses' => 'UsersController@index']), 'GET');

    $collector->record(ProNudgeSignal::QueryBuilder, $routeInfo);

    $command = makeProNudgeTestCommand($output = new BufferedOutput);

    (new ProNudgeReporter($collector))->report($command);

    expect($output->fetch())
        ->toContain('Spatie Query Builder')
        ->not->toContain('Laravel Data');
});

it('reports a laravel data only pitch', function () {
    $collector = new ProNudgeCollector;
    $routeInfo = new RouteInfo(new Route('GET', 'users', ['uses' => 'UsersController@index']), 'GET');

    $collector->record(ProNudgeSignal::LaravelDataReturn, $routeInfo);

    $command = makeProNudgeTestCommand($output = new BufferedOutput);

    (new ProNudgeReporter($collector))->report($command);

    expect($output->fetch())
        ->toContain('Laravel Data')
        ->toContain('Data objects.')
        ->not->toContain('Query Builder');
});

it('does not report when there are no signals', function () {
    $command = makeProNudgeTestCommand($output = new BufferedOutput);

    (new ProNudgeReporter(new ProNudgeCollector))->report($command);

    expect($output->fetch())->toBe('');
});

function makeProNudgeTestCommand(OutputInterface $output): Command
{
    $command = new class extends Command
    {
        protected $name = 'test';
    };

    $command->setLaravel(app());
    $command->setOutput(
        new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            $output,
        )
    );

    return $command;
}
