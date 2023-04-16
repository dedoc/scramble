<?php

it('infers array spread in resulting type', function () {
    expect(getStatementType("[42, 'b' => 'foo', ...['a' => 1, 'b' => 'wow', 16], 23]")->toString())
        ->toBe('array{0: int(42), b: string(wow), a: int(1), 1: int(16), 2: int(23)}');
});

it('ignores unknown type spread in resulting type', function () {
    expect(getStatementType("[42, 'b' => 'foo', ...someFunction(), 23]")->toString())
        ->toBe('array{0: int(42), b: string(foo), 1: int(23)}');
});
