<?php

it('handles array fetch', function () {
    expect(['a' => 1]['a'])->toHaveType('int(1)');
});

it('handles array deep fetch', function () {
    expect(['b' => ['c' => 42]]['b']['c'])->toHaveType('int(42)');
});
