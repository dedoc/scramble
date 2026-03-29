<?php

namespace Dedoc\Scramble\Console\Commands\Components;

use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Terminal;

class Block implements Component
{
    public function __construct(
        private string $content,
        private int $paddingLeft = 0,
    ) {}

    public function render(OutputStyle $style): void
    {
        if ($this->content === '') {
            return;
        }

        $padding = str_repeat(' ', $this->paddingLeft);
        $width = max(10, (new Terminal)->getWidth() - $this->paddingLeft);
        $lines = (new StyledConsoleTextWrapper)->wrap($this->content, $width);

        foreach ($lines as $line) {
            $style->writeln($padding.$line);
        }
    }
}
