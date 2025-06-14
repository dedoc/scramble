<?php

namespace Dedoc\Scramble\Exceptions;

use Dedoc\Scramble\Console\Commands\Components\Code;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Exception;
use Illuminate\Console\OutputStyle;
use Illuminate\Routing\Route;

class InvalidSchema extends Exception implements ConsoleRenderable, RouteAware
{
    use RouteAwareTrait;

    public ?string $jsonPointer = null;

    public string $originalMessage = '';

    public ?string $originFile = null;

    public ?int $originLine = null;

    public static function createForSchema(string $message, string $path, Type $schema)
    {
        /** @var string|null $file */
        $file = $schema->getAttribute('file');
        /** @var int|null $line */
        $line = $schema->getAttribute('line');

        $originalMessage = $message;
        if ($file) {
            $message = rtrim($message, '.').'. Got when analyzing an expression in file ['.$file.'] on line '.$line;
        }

        $exception = new static($path.': '.$message);

        $exception->originalMessage = $originalMessage;
        $exception->originFile = $file;
        $exception->originLine = $line;
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
            $line = $this->originLine ? ":$this->originLine" : '';

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
