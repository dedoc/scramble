<?php

namespace Dedoc\Scramble\Console\Commands\Components;

use Illuminate\Console\OutputStyle;
use NunoMaduro\Collision\ConsoleColor;
use NunoMaduro\Collision\Highlighter;
use Symfony\Component\Console\Helper\Helper;

class Code implements Component
{
    private ConsoleColor $color;

    private ?string $annotationAnchor = null;

    private ?string $annotation = null;

    public function __construct(
        public string $filePath,
        public int $line,
        public int $linesBefore = 2,
        public int $linesAfter = 2,
    ) {
        $this->color = new ConsoleColor;
    }

    public function render(OutputStyle $style): void
    {
        $style->writeln($this->highlight());
    }

    public function highlight(): string
    {
        $content = rtrim((new Highlighter)->getCodeSnippet(
            file_get_contents($this->filePath),
            $this->line,
            $this->linesBefore,
            $this->linesAfter,
        ));

        if ($this->annotationAnchor) {
            $content = $this->doAnnotate($content);
        }

        return $content;
    }

    public function annotate(string $annotationAnchor, string $annotation): self
    {
        $this->annotationAnchor = $annotationAnchor;
        $this->annotation = $annotation;

        return $this;
    }

    private function doAnnotate(string $content): string
    {
        $lines = $this->insertAnnotation(
            explode(PHP_EOL, $content),
            $this->annotationAnchor,
            $this->annotation,
        );

        return implode(PHP_EOL, $lines);
    }

    private function insertAnnotation(array $lines, string $annotationAnchor, string $annotation): array
    {
        $lineNumberDelimiter = '▕';
        $firstLine = $this->stripAnsi($lines[0] ?? '');
        $lineNumberDelimiterPosition = strpos($firstLine, $lineNumberDelimiter);

        if ($lineNumberDelimiterPosition === false) {
            return $lines;
        }

        $lineNumberWidth = Helper::width(substr($firstLine, 0, $lineNumberDelimiterPosition));

        foreach ($lines as $index => $line) {
            $visibleLine = $this->stripAnsi($line);

            if (! str_contains($visibleLine, $annotationAnchor)) {
                continue;
            }

            $delimiterPosition = strpos($visibleLine, $lineNumberDelimiter);

            if ($delimiterPosition === false) {
                continue;
            }

            $lineContent = substr($visibleLine, $delimiterPosition + strlen($lineNumberDelimiter));
            $lookupPosition = strpos($lineContent, $annotationAnchor);

            if ($lookupPosition === false) {
                continue;
            }

            $annotationLine = str_repeat(' ', $lineNumberWidth)
                .$this->color->apply('dark_gray', $lineNumberDelimiter)
                .str_repeat(' ', Helper::width(substr($lineContent, 0, $lookupPosition)))
                .'<fg=yellow>'.str_repeat('^', Helper::width($annotationAnchor)).'</> '
                .$annotation;

            array_splice($lines, $index + 1, 0, [$annotationLine]);

            return $lines;
        }

        return $lines;
    }

    private function stripAnsi(string $line): string
    {
        return preg_replace('/\e\[[0-9;]*m/', '', $line) ?? $line;
    }
}
