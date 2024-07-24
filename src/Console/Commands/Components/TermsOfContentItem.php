<?php

namespace Dedoc\Scramble\Console\Commands\Components;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Str;
use Symfony\Component\Console\Terminal;

class TermsOfContentItem implements Component
{
    public function __construct(
        public string $right,
        public ?string $left = null,
    ) {}

    public function render(OutputStyle $style): void
    {
        $width = (new Terminal)->getWidth();

        $rightWidth = $this->getLineWidth($this->right ?: '');
        $leftWidth = $this->getLineWidth($this->left ?: '');

        if (! $leftWidth) {
            $style->writeln($this->right);

            return;
        }

        $dotsCount = max($width - $rightWidth - $leftWidth - 2, 0);

        $style->writeln("{$this->right}<fg=gray> ".Str::repeat('.', $dotsCount)." </>{$this->left}");
    }

    private function getLineWidth(string $string)
    {
        $re = '/<.*?>/m';

        return mb_strlen(preg_replace($re, '', $string));
    }
}
