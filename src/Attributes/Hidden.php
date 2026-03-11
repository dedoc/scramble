<?php

namespace Dedoc\Scramble\Attributes;

use Attribute;

/**
 * Marks a class property as hidden from OpenAPI object schema.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Hidden {}
