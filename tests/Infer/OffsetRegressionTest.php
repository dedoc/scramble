<?php

it('preserves a nullable presenter URL after null coalescing assignment', function (bool $expanded) {
    $code = <<<'PHP'
<?php
class OffsetPresenter {
    /** @return array{redirect_url: string}|null */
    public static function present(): ?array {
        if (unavailable()) {
            return null;
        }

        return ['redirect_url' => (string) unavailable()];
    }
}
class OffsetController {
    public function claimData(?array $presentation = null) {
        $presentation ??= OffsetPresenter::present();

        return $presentation['redirect_url'] ?? null;
    }
}
PHP;

    if ($expanded) {
        $code = str_replace(
            '$presentation ??= OffsetPresenter::present();',
            '$presentation = $presentation ?? OffsetPresenter::present();',
            $code,
        );
    }

    $analysis = analyzeFile($code);

    expect(explode('|', $analysis->getExpressionType('(new OffsetController)->claimData()')->toString()))
        ->toContain('string', 'null');
})->with(['??=' => false, 'expanded assignment' => true]);

it('preserves string IDs after an array guard and string checks', function (bool $withGuard) {
    $code = <<<'PHP'
<?php
class OffsetService {
    public function read(array $item) {
        if (! is_array($item)) {
            $item = [];
        }

        $recordKey = isset($item['record_key']) && is_string($item['record_key'])
            ? $item['record_key']
            : null;
        $externalRowId = isset($item['external_row_id']) && is_string($item['external_row_id'])
            ? $item['external_row_id']
            : null;

        return ['record_key' => $recordKey, 'external_row_id' => $externalRowId];
    }
}
PHP;

    if (! $withGuard) {
        $code = str_replace("        if (! is_array(\$item)) {\n            \$item = [];\n        }", '', $code);
    }

    $analysis = analyzeFile($code);

    expect($analysis->getExpressionType('(new OffsetService)->read([
        "record_key" => (string) $input,
        "external_row_id" => (string) $input,
    ])')->toString())->toBe('array{record_key: string|null, external_row_id: string|null}');
})->with(['with guard' => true, 'without guard' => false]);
