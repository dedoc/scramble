<?php

namespace Dedoc\Scramble\Support\Generator;

trait WithExtensions
{
    /** @var array<string, mixed> */
    private $extensions = [];

    public function setExtensionProperty(string $key, mixed $value): void
    {
        $this->extensions[$key] = $value;
    }

    public function hasExtensionProperty(string $key): bool
    {
        return array_key_exists($key, $this->extensions);
    }

    public function getExtensionProperty(string $key): mixed
    {
        if ($this->hasExtensionProperty($key)) {
            return $this->extensions[$key];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function extensionProperties(): array
    {
        return $this->extensions;
    }

    /**
     * @param  array<string, mixed>  $extensionsProperties
     */
    public function mergeExtensionProperties(array $extensionsProperties): self
    {
        $this->extensions = array_merge($this->extensions, $extensionsProperties);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function extensionPropertiesToArray(): array
    {
        return collect($this->extensions)
            ->mapWithKeys(fn ($v, $k) => ["x-$k" => $v])
            ->all();
    }
}
