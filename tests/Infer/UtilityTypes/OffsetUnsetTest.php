<?php

it('handles array unset', function () {
    $a = ['foo' => 42];
    unset($a['foo']);
    expect($a)->toHaveType('list{}');
});
