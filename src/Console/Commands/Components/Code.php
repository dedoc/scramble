<?php

namespace Dedoc\Scramble\Console\Commands\Components;

use Illuminate\Console\OutputStyle;
use NunoMaduro\Collision\Highlighter;

class Code implements Component
{
    public function __construct(
        public string $filePath,
        public int $line,
    ) {}

    public function render(OutputStyle $style): void
    {
        $code = (new Highlighter)->highlight(file_get_contents($this->filePath), $this->line);

        $style->writeln($code);
    }
}
