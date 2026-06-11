<?php

namespace Dedoc\Scramble\Support\Generator\Combined;

use Dedoc\Scramble\Support\Generator\Example;
use Dedoc\Scramble\Support\Generator\MissingValue;
use Dedoc\Scramble\Support\Generator\Types\Type;

trait HasCombinedItems
{
    public function toArray()
    {
        $parentArray = parent::toArray();

        unset($parentArray['type']);

        $items = array_map(fn (Type $item) => $item->clone(), $this->items);

        $distributedCount = [
            'example' => 0,
            'examples' => [],
            'default' => 0,
        ];

        if (! ($this->example instanceof MissingValue)) {
            foreach ($items as $item) {
                if ($this->itemMatchesExample($item, $this->example)) {
                    if ($this->example instanceof Example) {
                        $item->example($this->example->value);
                        if (! $item->description && $this->example->summary) {
                            $item->setDescription($this->example->summary);
                        }
                    } else {
                        $item->example($this->example);
                    }
                    $distributedCount['example']++;
                }
            }
        }

        if (count($this->examples)) {
            $itemExamples = [];
            foreach ($this->examples as $i => $example) {
                $matched = false;
                foreach ($items as $itemIndex => $item) {
                    if ($this->itemMatchesExample($item, $example)) {
                        $itemExamples[$itemIndex][] = $example;
                        $matched = true;
                    }
                }
                if ($matched) {
                    $distributedCount['examples'][] = $i;
                }
            }

            foreach ($itemExamples as $itemIndex => $examples) {
                if (count($examples) === 1) {
                    $example = $examples[0];
                    if ($example instanceof Example) {
                        $items[$itemIndex]->example($example->value);
                        if (! $items[$itemIndex]->description && $example->summary) {
                            $items[$itemIndex]->setDescription($example->summary);
                        }
                    } else {
                        $items[$itemIndex]->example($example);
                    }
                } else {
                    $items[$itemIndex]->examples($examples);
                }
            }
        }

        if (! ($this->default instanceof MissingValue)) {
            foreach ($items as $item) {
                if ($item->matches($this->default)) {
                    $item->default($this->default);
                    $distributedCount['default']++;
                }
            }
        }

        if ($distributedCount['example'] > 0) {
            unset($parentArray['example']);
        }

        if (count($distributedCount['examples']) === count($this->examples) && count($this->examples) > 0) {
            unset($parentArray['examples']);
        } elseif (count($distributedCount['examples']) > 0) {
            $parentArray['examples'] = collect($this->examples)
                ->filter(fn ($e, $i) => ! in_array($i, $distributedCount['examples']))
                ->values()
                ->toArray();
        }

        if ($distributedCount['default'] > 0) {
            unset($parentArray['default']);
        }

        return [
            ...$parentArray,
            $this->combinedOperator => array_map(
                fn ($item) => $item->toArray(),
                $items,
            ),
        ];
    }

    private function itemMatchesExample(Type $item, $example): bool
    {
        if ($example instanceof Example) {
            if ($example->type) {
                return $item->type === $example->type;
            }

            return $item->matches($example->value);
        }

        return $item->matches($example);
    }
}
