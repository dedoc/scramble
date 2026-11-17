<?php

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\TypeTransformer;
use Dedoc\Scramble\Support\InferExtensions\DateTimeMethodReturnTypeExtension;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\TypeWalker;
use Illuminate\Http\Resources\Json\JsonResource;

beforeEach(function () {
    $this->context = new OpenApiContext(new OpenApi('3.1.0'), new GeneratorConfig);
});

it('infers date and date-time format attributes for DateTimeInterface and Carbon methods', function (string $expression, string $expectedTypeString, ?string $expectedFormat) {
    $type = getStatementType($expression, [
        app(DateTimeMethodReturnTypeExtension::class),
    ]);

    expect($type->toString())->toBe($expectedTypeString);

    $stringType = (new TypeWalker)->first($type, fn ($t) => $t instanceof StringType);

    expect($stringType?->getAttribute('format'))->toBe($expectedFormat);
})->with([
    'DateTimeInterface::format(Y-m-d)' => ['(new \DateTimeImmutable())->format("Y-m-d")', 'string', 'date'],
    'DateTimeImmutable::format(c)' => ['(new \DateTimeImmutable())->format("c")', 'string', 'date-time'],
    'DateTimeImmutable::format(DateTimeInterface::RFC3339)' => ['(new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339)', 'string', 'date-time'],
    'DateTimeImmutable::format(DateTimeInterface::RFC3339_EXTENDED)' => ['(new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339_EXTENDED)', 'string', 'date-time'],
    'DateTimeImmutable::format(DateTimeInterface::ATOM)' => ['(new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)', 'string', 'date-time'],
    'DateTimeImmutable::format(DateTimeInterface::W3C)' => ['(new \DateTimeImmutable())->format(\DateTimeInterface::W3C)', 'string', 'date-time'],
    'DateTimeImmutable::format(Y-m-d H:i:s)' => ['(new \DateTimeImmutable())->format("Y-m-d H:i:s")', 'string', null],
    'DateTimeImmutable::format(DateTimeInterface::ISO8601)' => ['(new \DateTimeImmutable())->format(\DateTimeInterface::ISO8601)', 'string', null],
    'Carbon::format(Y-m-d)' => [Carbon::class.'::now()->format("Y-m-d")', 'string', 'date'],
    'Carbon::format(c)' => [Carbon::class.'::now()->format("c")', 'string', 'date-time'],
    'Carbon::format(Y-m-d H:i:s)' => [Carbon::class.'::now()->format("Y-m-d H:i:s")', 'string', null],
    'Carbon::toDateString()' => [Carbon::class.'::now()->toDateString()', 'string', 'date'],
    'CarbonImmutable::toDateString()' => [CarbonImmutable::class.'::now()->toDateString()', 'string', 'date'],
    'Carbon::toAtomString()' => [Carbon::class.'::now()->toAtomString()', 'string', 'date-time'],
    'Carbon::toIso8601String()' => [Carbon::class.'::now()->toIso8601String()', 'string', 'date-time'],
    'Carbon::toIso8601ZuluString()' => [Carbon::class.'::now()->toIso8601ZuluString()', 'string', 'date-time'],
    'Carbon::toIso8601ZuluString(microsecond)' => [Carbon::class.'::now()->toIso8601ZuluString("microsecond")', 'string', 'date-time'],
    'Carbon::toIso8601ZuluString(minute)' => [Carbon::class.'::now()->toIso8601ZuluString("minute")', 'string', null],
    'Carbon::toISOString()' => [Carbon::class.'::now()->toISOString()', 'string|null', 'date-time'],
    'Carbon::toJSON()' => [Carbon::class.'::now()->toJSON()', 'string|null', 'date-time'],
    'Carbon::toRfc3339String()' => [Carbon::class.'::now()->toRfc3339String()', 'string', 'date-time'],
    'Carbon::toRfc3339String(true)' => [Carbon::class.'::now()->toRfc3339String(true)', 'string', 'date-time'],
    'Carbon::toW3cString()' => [Carbon::class.'::now()->toW3cString()', 'string', 'date-time'],
    'Carbon::toDateTimeString()' => [Carbon::class.'::now()->toDateTimeString()', 'string', null],
]);

it('does not infer date-time format for dynamic format arguments', function () {
    $type = analyzeFile(<<<'PHP'
<?php
function formatDate(DateTimeInterface $date, string $format): string
{
    return $date->format($format);
}
PHP, [app(DateTimeMethodReturnTypeExtension::class)])->getFunctionDefinition('formatDate')->getReturnType();

    expect($type->toString())->toBe('string')
        ->and($type->getAttribute('format'))->toBeNull();
});

it('transforms formatted DateTimeInterface and Carbon method calls into OpenAPI schema formats', function () {
    $transformer = app(TypeTransformer::class, ['context' => $this->context]);

    $transformer->transform(new ObjectType(DateTimeMethodReturnTypeExtensionTest_Resource::class));

    $properties = $this->context->openApi->components
        ->getSchema(DateTimeMethodReturnTypeExtensionTest_Resource::class)
        ->toArray()['properties'];

    expect($properties)->toBe([
        'date_format' => ['type' => 'string', 'format' => 'date'],
        'date_method' => ['type' => 'string', 'format' => 'date'],
        'immutable_date' => ['type' => 'string', 'format' => 'date'],
        'atom' => ['type' => 'string', 'format' => 'date-time'],
        'iso8601' => ['type' => 'string', 'format' => 'date-time'],
        'zulu' => ['type' => 'string', 'format' => 'date-time'],
        'zulu_minute' => ['type' => 'string'],
        'iso_nullable' => ['type' => ['string', 'null'], 'format' => 'date-time'],
        'json_nullable' => ['type' => ['string', 'null'], 'format' => 'date-time'],
        'rfc3339' => ['type' => 'string', 'format' => 'date-time'],
        'w3c' => ['type' => 'string', 'format' => 'date-time'],
        'format_c' => ['type' => 'string', 'format' => 'date-time'],
        'format_rfc3339' => ['type' => 'string', 'format' => 'date-time'],
        'datetime_string' => ['type' => 'string'],
        'format_datetime' => ['type' => 'string'],
        'format_iso8601_compat' => ['type' => 'string'],
        'nullable_carbon_to_date' => ['type' => ['string', 'null'], 'format' => 'date'],
    ]);
});

/**
 * @property Carbon $carbon
 * @property CarbonImmutable $immutableCarbon
 * @property CarbonInterface $carbonInterface
 * @property Carbon|null $nullableCarbon
 * @property \DateTimeInterface $dateTime
 */
class DateTimeMethodReturnTypeExtensionTest_Resource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'date_format' => $this->dateTime->format('Y-m-d'),
            'date_method' => $this->carbon->toDateString(),
            'immutable_date' => $this->immutableCarbon->toDateString(),
            'atom' => $this->carbon->toAtomString(),
            'iso8601' => $this->carbon->toIso8601String(),
            'zulu' => $this->carbon->toIso8601ZuluString(),
            'zulu_minute' => $this->carbon->toIso8601ZuluString('minute'),
            'iso_nullable' => $this->carbon->toISOString(),
            'json_nullable' => $this->carbon->toJSON(),
            'rfc3339' => $this->carbonInterface->toRfc3339String(),
            'w3c' => $this->carbon->toW3cString(),
            'format_c' => $this->dateTime->format('c'),
            'format_rfc3339' => $this->dateTime->format(\DateTimeInterface::RFC3339),
            'datetime_string' => $this->carbon->toDateTimeString(),
            'format_datetime' => $this->carbon->format('Y-m-d H:i:s'),
            'format_iso8601_compat' => $this->dateTime->format(\DateTimeInterface::ISO8601),
            'nullable_carbon_to_date' => $this->nullableCarbon?->toDateString(),
        ];
    }
}
