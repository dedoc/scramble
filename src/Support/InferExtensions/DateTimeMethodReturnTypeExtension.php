<?php

namespace Dedoc\Scramble\Support\InferExtensions;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Extensions\MethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\Contracts\LiteralString;
use Dedoc\Scramble\Support\Type\NullType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\Union;

class DateTimeMethodReturnTypeExtension implements MethodReturnTypeExtension
{
    private const DATE_TIME_LITERAL_FORMATS = [
        'c',
        DATE_ATOM,
        DATE_RFC3339_EXTENDED,
        'Y-m-d\TH:i:s.uP',
        'Y-m-d\TH:i:s\Z',
        'Y-m-d\TH:i:s.v\Z',
        'Y-m-d\TH:i:s.u\Z',
    ];

    private const ZULU_PRECISION_UNITS = [
        'second',
        'seconds',
        'm',
        'millisecond',
        'milliseconds',
        'µ',
        'microsecond',
        'microseconds',
    ];

    public function shouldHandle(ObjectType $type): bool
    {
        return $type->isInstanceOf(DateTimeInterface::class);
    }

    public function getMethodReturnType(MethodCallEvent $event): ?Type
    {
        if ($event->getName() === 'format') {
            return $this->getFormatMethodReturnType($event);
        }

        if (! $event->getInstance()->isInstanceOf(CarbonInterface::class)) {
            return null;
        }

        return match ($event->getName()) {
            'toDateString' => $this->stringWithFormat('date'),
            'toAtomString', 'toIso8601String', 'toRfc3339String', 'toW3cString' => $this->stringWithFormat('date-time'),
            'toIso8601ZuluString' => $this->getZuluStringReturnType($event),
            'toISOString', 'toJSON' => Union::wrap([
                $this->stringWithFormat('date-time'),
                new NullType,
            ]),
            default => null,
        };
    }

    private function getFormatMethodReturnType(MethodCallEvent $event): StringType
    {
        $formatArg = $event->getArg('format', 0);

        if (! $formatArg instanceof LiteralString) {
            return new StringType;
        }

        $format = match (true) {
            $formatArg->getValue() === 'Y-m-d' => 'date',
            in_array($formatArg->getValue(), self::DATE_TIME_LITERAL_FORMATS, true) => 'date-time',
            default => null,
        };

        if ($format === null) {
            return new StringType;
        }

        return $this->stringWithFormat($format);
    }

    private function getZuluStringReturnType(MethodCallEvent $event): Type
    {
        if (count($event->arguments) === 0) {
            return $this->stringWithFormat('date-time');
        }

        $precisionArg = $event->getArg('unitPrecision', 0);

        if ($precisionArg instanceof LiteralString && in_array($precisionArg->getValue(), self::ZULU_PRECISION_UNITS, true)) {
            return $this->stringWithFormat('date-time');
        }

        return new StringType;
    }

    private function stringWithFormat(string $format): StringType
    {
        return tap(new StringType, fn (StringType $type) => $type->setAttribute('format', $format));
    }
}
