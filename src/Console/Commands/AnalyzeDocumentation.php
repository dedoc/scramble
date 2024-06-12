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
use Throwable;

class AnalyzeDocumentation extends Command
{
    protected $signature = 'scramble:analyze
        {--api=default : The API to analyze}
    ';

    protected $description = 'Analyzes the documentation generation process to surface any issues.';

    public function handle(Generator $generator): int
    {
        $generator->setThrowExceptions(false);

        $generator(Scramble::getGeneratorConfig($this->option('api')));

        $i = 1;
        $this->groupExceptions($generator->exceptions)->each(function (Collection $exceptions, string $group) use (&$i) {
            $this->renderExceptionsGroup($exceptions, $group, $i);
        });

        if (count($generator->exceptions)) {
            $this->error('[ERROR] Found '.count($generator->exceptions).' errors.');

            return static::FAILURE;
        }

        $this->info('Everything is fine! Documentation is generated without any errors 🍻');

        return static::SUCCESS;
    }

    /**
     * @return Collection<string, Collection<int, Throwable>>
     */
    private function groupExceptions(array $exceptions): Collection
    {
        return collect($exceptions)
            ->groupBy(fn ($e) => $e instanceof RouteAware ? $this->getRouteKey($e->getRoute()) : '');
    }

    /**
     * @param  Collection<int, Throwable>  $exceptions
     */
    private function renderExceptionsGroup(Collection $exceptions, string $group, int &$i): void
    {
        // when route key is set, then the exceptions in the group are route aware.
        if ($group) {
            $this->renderRouteExceptionsGroupLine($exceptions);
        }

        $exceptions->each(function ($exception) use (&$i) {
            $this->renderException($exception, $i);
            $i++;
            $this->line('');
        });
    }

    private function getRouteKey(?Route $route): string
    {
        if (! $route) {
            return '';
        }

        $method = $route->methods()[0];
        $action = $route->getAction('uses');

        return "$method.$action";
    }

    /**
     * @param  Collection<int, RouteAware>  $exceptions
     */
    private function renderRouteExceptionsGroupLine(Collection $exceptions): void
    {
        $firstException = $exceptions->first();
        $route = $firstException->getRoute();

        $method = $route->methods()[0];
        $errorsMessage = ($count = $exceptions->count()).' '.Str::plural('error', $count);

        $tocComponent = new TermsOfContentItem(
            right: '<options=bold;fg='.$this->getHttpMethodColor($method).'>'.$method."</> $route->uri <fg=red>$errorsMessage</>",
            left: $this->getRouteAction($route),
        );

        $tocComponent->render($this->output);

        $this->line('');
    }

    private function getHttpMethodColor(string $method): string
    {
        return match ($method) {
            'POST', 'PUT' => 'blue',
            'DELETE' => 'red',
            default => 'yellow',
        };
    }

    public function getRouteAction(?Route $route): ?string
    {
        if (! $uses = $route->getAction('uses')) {
            return null;
        }

        if (count($parts = explode('@', $uses)) !== 2 || ! method_exists(...$parts)) {
            return null;
        }

        [$class, $method] = $parts;

        $eloquentClassName = Str::replace(['App\Http\Controllers\\', 'App\Http\\'], '', $class);

        return "<fg=gray>{$eloquentClassName}@{$method}</>";
    }

    private function renderException($exception, int $i): void
    {
        $message = Str::replace('Dedoc\Scramble\Support\Generator\Types\\', '', property_exists($exception, 'originalMessage') ? $exception->originalMessage : $exception->getMessage());

        $this->output->writeln("<options=bold>$i. {$message}</>");

        if ($exception instanceof ConsoleRenderable) {
            $exception->renderInConsole($this->output);
        }
    }
}
