<?php

namespace Dedoc\Scramble\Infer;

use Dedoc\Scramble\Infer\Extensions\ExtensionsBroker;

class Context
{
    private static $instance = null;

    public function __construct(
        public readonly ExtensionsBroker $extensionsBroker,
    ) {}

    public static function configure(
        ExtensionsBroker $extensionsBroker,
    ) {
        static::$instance = new static(
            $extensionsBroker,
        );
    }

    public static function getInstance(): static
    {
        if (! static::$instance) {
            static::$instance = new static(
                app(ExtensionsBroker::class),
            );
        }

        return static::$instance;
    }

    public static function reset()
    {
        static::$instance = null;
    }
}
