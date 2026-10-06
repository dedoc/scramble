<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class InfoObject implements JsonSerializable, OpenApiSerializable
{
    use WithAttributes;
    use WithExtensions;

    public string $title;

    public string $version;

    public string $description = '';

    public ?string $summary = null;

    public ?string $termsOfService = null;

    public ?Contact $contact = null;

    public ?License $license = null;

    public function __construct(string $title, string $version = '0.0.1')
    {
        $this->title = $title;
        $this->version = $version;
    }

    public static function make(string $title)
    {
        return new self($title);
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function setSummary(?string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    public function setTermsOfService(?string $termsOfService): self
    {
        $this->termsOfService = $termsOfService;

        return $this;
    }

    public function setContact(?Contact $contact): self
    {
        $this->contact = $contact;

        return $this;
    }

    public function setLicense(?License $license): self
    {
        $this->license = $license;

        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return $this->serializeAs31();
    }

    public function serializeAs31(): array
    {
        return $this->serialize(fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): array
    {
        return $this->serialize(fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     */
    private function serialize(callable $serializeItem): array
    {
        $result = array_filter([
            'title' => $this->title,
            'version' => $this->version,
            'description' => $this->description ?: null,
            'summary' => $this->summary,
            'termsOfService' => $this->termsOfService,
            'contact' => $this->contact ? $serializeItem($this->contact) : null,
            'license' => $this->license ? $serializeItem($this->license) : null,
        ], fn ($value) => $value !== null);

        return array_merge($result, $this->extensionPropertiesToArray());
    }
}
