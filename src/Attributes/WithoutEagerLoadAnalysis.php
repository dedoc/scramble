<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;

/**
 * Opts a JsonResource out of eager-loaded relations analysis when documenting its schema.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class WithoutEagerLoadAnalysis {}
