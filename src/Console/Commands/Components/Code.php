<?php

namespace Dedoc\Scramble\Console\Commands\Components;

use Illuminate\Console\OutputStyle;
use RuntimeException;
use Symfony\Component\Console\Formatter\OutputFormatter;

class Code implements Component
{
    public function __construct(
        public string $filePath,
        public int $line,
        public int $linesBefore = 2,
        public int $linesAfter = 2,
    ) {}

    public function render(OutputStyle $style): void
    {
        $style->writeln($this->snippet());
    }

    public function snippet(): string
    {
        $source = file_get_contents($this->filePath);

        if ($source === false) {
            throw new RuntimeException("Cannot read source file [$this->filePath].");
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $source));
        $offset = max($this->line - $this->linesBefore - 1, 0);
        $lines = array_slice($lines, $offset, $this->linesBefore + $this->linesAfter + 1, preserve_keys: true);
        $lineNumberWidth = max(3, strlen((string) (array_key_last($lines) + 1)));

        return collect($lines)
            ->map(function (string $code, int $index) use ($lineNumberWidth) {
                $lineNumber = $index + 1;
                $marker = $lineNumber === $this->line
                    ? '<fg=red;options=bold>  ➜ </>'
                    : '    ';

                return $marker
                    .'<fg=gray>'.str_pad((string) $lineNumber, $lineNumberWidth, ' ', STR_PAD_LEFT).'▕ </>'
                    .'<fg=white>'.OutputFormatter::escape($code).'</>';
            })
            ->implode(PHP_EOL);
    }
}
