<?php

namespace Dedoc\Scramble\Exceptions;

use Dedoc\Scramble\Console\Commands\Components\Code;
use Exception;
use Illuminate\Console\OutputStyle;
use Illuminate\Routing\Route;
use function Termwind\render;

class InvalidSchema extends Exception implements RouteAware, ConsoleRenderable
{
    use RouteAwareTrait;

    public ?string $jsonPointer = null;

    public string $originalMessage = '';

    public ?string $originFile = null;

    public ?int $originLine = null;

    public static function create(string $message, string $path)
    {
        $exception = new self($path.': '.$message);

        $exception->originalMessage = $message;
        $exception->jsonPointer = $path;

        return $exception;
    }

    public function getRouteAwareMessage(Route $route, string $msg): string
    {
        $method = $route->methods()[0];
        $action = $route->getAction('uses');

        return "'$method $route->uri' ($action): ".$msg;
    }

    public function renderInConsole(OutputStyle $outputStyle): void
    {
        $codeSample = null;
        $tableCells = [];
        if ($this->jsonPointer) {
            $tableCells[] = ['<fg=gray>Found at</>', $this->jsonPointer];
        }

        if ($this->originFile) {
            $line = $this->originLine?":$this->originLine" : '';

            $tableCells[] = ['<fg=gray>Inferred at</>', "{$this->originFile}{$line}"];

            if ($line) {
                $path = class_exists($this->originFile)
                    ? (new \ReflectionClass($this->originFile))->getFileName()
                    : $this->originFile;

                $codeSample = new Code($path, $this->originLine);
            }
        }

        $outputStyle->createTable()->setRows($tableCells)->setStyle('compact')->render();

        $codeSample?->render($outputStyle);
    }
}
