<?php

namespace Dedoc\Scramble\Console\Commands\Components;

use Symfony\Component\Console\Helper\Helper;

class StyledConsoleTextWrapper
{
    private const BREAK_CHARS = [' ', '-', '\\', '/', '.', ','];

    private const PARTS_PATTERN = '/(https?:\/\/[^\s<>]+|<[^>]+>)/u';

    /**
     * @return list<string>
     */
    public function wrap(string $content, int $maxWidth): array
    {
        return array_merge(...array_map(
            fn (string $paragraph) => $paragraph === '' ? [''] : $this->wrapParagraph($paragraph, $maxWidth),
            explode("\n", $content),
        ));
    }

    /**
     * @return list<string>
     */
    private function wrapParagraph(string $paragraph, int $maxWidth): array
    {
        $parts = preg_split(self::PARTS_PATTERN, $paragraph, flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $lines = [];
        $line = '';
        $lineWidth = 0;
        $openTags = [];

        foreach ($parts as $part) {
            if ($this->isTag($part)) {
                $line .= $part;
                $this->updateOpenTags($openTags, $part);

                continue;
            }

            if ($this->isUrl($part)) {
                if ($lineWidth > 0 && Helper::width($part) > $maxWidth - $lineWidth) {
                    $this->pushLine($lines, $line, $lineWidth, $openTags);
                }

                $line .= $part;
                $lineWidth += Helper::width($part);

                continue;
            }

            while ($part !== '') {
                if ($lineWidth >= $maxWidth) {
                    $this->pushLine($lines, $line, $lineWidth, $openTags);
                }

                [$chunk, $part] = $this->takeChunk($part, $maxWidth - $lineWidth, $lineWidth > 0);
                if ($chunk === '') {
                    $this->pushLine($lines, $line, $lineWidth, $openTags);

                    continue;
                }

                $line .= $chunk;
                $lineWidth += Helper::width($chunk);
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function pushLine(array &$lines, string &$line, int &$lineWidth, array $openTags): void
    {
        if ($lineWidth === 0) {
            return;
        }

        $lines[] = $line.str_repeat('</>', count($openTags));
        $line = implode('', $openTags);
        $lineWidth = 0;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function takeChunk(string $text, int $maxWidth, bool $wrapOnNextLine): array
    {
        if ($text === '' || $maxWidth <= 0) {
            return ['', $text];
        }

        if (Helper::width($text) <= $maxWidth) {
            return [$text, ''];
        }

        $best = $this->fittingLength($text, $maxWidth);

        if ($best === 0) {
            return [mb_substr($text, 0, 1), mb_substr($text, 1)];
        }

        if ($break = $this->findBreakPosition($text, $best)) {
            return [mb_substr($text, 0, $break), mb_substr($text, $break)];
        }

        if ($wrapOnNextLine) {
            return ['', $text];
        }

        return [mb_substr($text, 0, $best), mb_substr($text, $best)];
    }

    private function fittingLength(string $text, int $maxWidth): int
    {
        $fit = 0;
        $length = mb_strlen($text);

        for ($i = 1; $i <= $length; $i++) {
            if (Helper::width(mb_substr($text, 0, $i)) > $maxWidth) {
                break;
            }

            $fit = $i;
        }

        return $fit;
    }

    private function findBreakPosition(string $text, int $max): ?int
    {
        for ($i = $max; $i >= 1; $i--) {
            if ($this->charAt($text, $i) === '\\') {
                return $i;
            }

            if ($this->isBreakBoundary($text, $i) && $this->charAt($text, $i - 1) !== '\\') {
                return $i;
            }
        }

        return null;
    }

    private function isBreakBoundary(string $text, int $index): bool
    {
        $length = mb_strlen($text);
        if ($index <= 0 || $index > $length) {
            return false;
        }

        return $this->isBreakCharAt($text, $index - 1, $length)
            || $this->isBreakCharAt($text, $index, $length);
    }

    private function isBreakCharAt(string $text, int $index, int $length): bool
    {
        if ($index < 0 || $index >= $length) {
            return false;
        }

        $char = mb_substr($text, $index, 1);

        return in_array($char, self::BREAK_CHARS, true);
    }

    private function isTag(string $part): bool
    {
        return (bool) preg_match('/^<[^>]+>$/u', $part);
    }

    private function isUrl(string $part): bool
    {
        return str_starts_with($part, 'http://') || str_starts_with($part, 'https://');
    }

    private function charAt(string $text, int $index): string
    {
        $length = mb_strlen($text);

        if ($index < 0 || $index >= $length) {
            return '';
        }

        return mb_substr($text, $index, 1);
    }

    private function updateOpenTags(array &$openTags, string $tag): void
    {
        if (str_starts_with($tag, '</')) {
            array_pop($openTags);

            return;
        }

        $openTags[] = $tag;
    }
}
