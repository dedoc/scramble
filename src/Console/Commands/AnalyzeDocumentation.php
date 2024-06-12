<?php

namespace Dedoc\Scramble\Console\Commands;

use Dedoc\Scramble\Console\Commands\Components\TermsOfContentItem;
use Dedoc\Scramble\Exceptions\ConsoleRenderable;
use Dedoc\Scramble\Exceptions\RouteAware;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AnalyzeDocumentation extends Command
{
    protected $signature = 'scramble:analyze
        {--api=default : The API to analyze}
    ';

    protected $description = 'Analyzes the documentation generation process to surface any issues.';

    public function handle(Generator $generator): int
    {
        $generator->setThrowExceptions(false);

        $config = Scramble::getGeneratorConfig($this->option('api'));

        $generator($config);

        $i = 1;

        collect($generator->exceptions)
            ->groupBy(fn ($e) => $e instanceof RouteAware ? $this->getRouteKey($e->getRoute()) : '')
            ->each(function (Collection $exceptions, string $routeKey) use (&$i) {
                // when route key is set, then the exceptions in the group are route aware.
                if ($routeKey) {
                    /** @var RouteAware $firstException */
                    $firstException = $exceptions->first();
                    /** @var Route $route */
                    $route = $firstException->getRoute();

                    $method = $route->methods()[0];
                    $errorsMessage = ($count = $exceptions->count()).' '.Str::plural('error', $count);

                    $color = match($method) {
                        'POST', 'PUT' => 'blue',
                        'DELETE' => 'red',
                        default => 'yellow',
                    };

                    $tocComponent = new TermsOfContentItem(right: "<options=bold;fg=$color>".$method."</> $route->uri <fg=red>$errorsMessage</>");

                    if (
                        $route->getAction('uses')
                        && count($parts = explode('@', $route->getAction('uses'))) === 2
                        && class_exists($parts[0])
                        && method_exists(...$parts)
                    ) {
                        [$class, $method] = $parts;

                        $eloquentClassName = Str::replace(['App\Http\Controllers\\', 'App\Http\\'], '', $class);

                        $tocComponent->left = "<fg=gray>{$eloquentClassName}@{$method}</>";
                    }

                    $tocComponent->render($this->output);

                    $this->line('');
                }

                $exceptions->each(function ($exception) use (&$i) {
                    $message = Str::replace('Dedoc\Scramble\Support\Generator\Types\\', '', property_exists($exception, 'originalMessage') ? $exception->originalMessage : $exception->getMessage());

                    $this->output->writeln("<options=bold>$i. {$message}</>");

                    if ($exception instanceof ConsoleRenderable) {
                        $exception->renderInConsole($this->output);
                    }

                    $i++;
                    $this->line('');
                });
            });

        if (count($generator->exceptions)) {
            $this->error("[ERROR] Found ".count($generator->exceptions)." errors.");

            return static::FAILURE;
        }

        $this->info('Everything is fine! Documentation is generated without any errors 🍻');

        return static::SUCCESS;
    }

    private function getRouteKey(?Route $route)
    {
        if (! $route) {
            return '';
        }

        $method = $route->methods()[0];
        $action = $route->getAction('uses');

        return "$method.$action";
    }
}
