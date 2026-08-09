<?php

namespace Dedoc\Scramble\Console\Commands\Components;

use Illuminate\Console\OutputStyle;
use NunoMaduro\Collision\Highlighter;

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
        $style->writeln($this->highlight());
    }

    public function highlight(): string
    {
        return rtrim((new Highlighter)->getCodeSnippet(
            file_get_contents($this->filePath),
            $this->line,
            $this->linesBefore,
            $this->linesAfter,
        ));
    }
}
