<?php

use Dedoc\Scramble\Infer;
use Dedoc\Scramble\Infer\Scope\Index;

beforeEach(function () {
    $this->index = new Index();
    $this->infer = new Infer($this->index);
});

it('creates class definition on analyzeClass call', function () {

});
