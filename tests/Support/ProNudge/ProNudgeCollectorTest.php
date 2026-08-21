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

    expect($collector->message())->toBe([
        'title' => '1 endpoint uses Spatie Query Builder, and 2 endpoints return Laravel Data objects',
        'description' => 'Scramble PRO will document these endpoints accurately.',
    ]);
});

it('reports the message in a block', function () {
    $collector = new ProNudgeCollector;
    $routeInfo = new RouteInfo(new Route('GET', 'users', ['uses' => 'UsersController@index']), 'GET');

    $collector->record(ProNudgeSignal::QueryBuilder, $routeInfo);

    $command = makeProNudgeTestCommand($output = new BufferedOutput);

    (new ProNudgeReporter($collector))->report($command);

    expect($output->fetch())
        ->toContain('⚡️ 1 endpoint uses Spatie Query Builder.')
        ->toContain('Scramble PRO will document these endpoints accurately.')
        ->toContain('Learn more: '.ProNudgeReporter::PRO_URL)
        ->toContain(' | ');
});

it('does not report when there are no signals', function () {
    $command = makeProNudgeTestCommand($output = new BufferedOutput);

    (new ProNudgeReporter(new ProNudgeCollector))->report($command);

    expect($output->fetch())->toBe('');
    expect((new ProNudgeCollector)->message())->toBeNull();
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
