<?php

namespace Dedoc\Scramble\Configuration;

use Dedoc\Scramble\Contracts\SecurityDocumentationStrategy;
use Dedoc\Scramble\GeneratorConfig;
use InvalidArgumentException;

class SecurityStrategyResolver
{
    public static function resolve(GeneratorConfig $config): ?SecurityDocumentationStrategy
    {
        $value = $config->get('security_strategy');

        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return self::makeStrategy($value);
        }

        if (is_array($value) && count($value) === 2 && is_string($value[0])) {
            return self::makeStrategy($value[0], $value[1]);
        }

        throw new InvalidArgumentException(
            'Invalid scramble.security_strategy config. Expected null, a class-string, or [class-string, options array].'
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function makeStrategy(string $class, array $options = []): SecurityDocumentationStrategy
    {
        $strategy = app($class, $options);

        if (! $strategy instanceof SecurityDocumentationStrategy) {
            throw new InvalidArgumentException(
                "Security strategy [{$class}] must implement ".SecurityDocumentationStrategy::class.'.'
            );
        }

        return $strategy;
    }
}
