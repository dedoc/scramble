<?php

namespace Dedoc\Scramble\Support\Generator;

class Encoding
{
    use WithAttributes;
    use WithExtensions;

    public function __construct(
        public ?string $contentType = null,
        /** @var array<string, Header|Reference> */
        public array $headers = [],
        public ?string $style = null,
        public ?bool $explode = null,
        public ?bool $allowReserved = null,
    ) {}

    /**
     * @return $this
     */
    public function setContentType(?string $contentType): self
    {
        $this->contentType = $contentType;

        return $this;
    }

    /**
     * @param  array<string, Header|Reference>  $headers
     * @return $this
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * @return $this
     */
    public function addHeader(string $key, Header|Reference $header): self
    {
        $this->headers[$key] = $header;

        return $this;
    }

    /**
     * @return $this
     */
    public function removeHeader(string $key): self
    {
        unset($this->headers[$key]);

        return $this;
    }

    /**
     * @return $this
     */
    public function setStyle(?string $style): self
    {
        $this->style = $style;

        return $this;
    }

    /**
     * @return $this
     */
    public function setExplode(?bool $explode): self
    {
        $this->explode = $explode;

        return $this;
    }

    /**
     * @return $this
     */
    public function setAllowReserved(?bool $allowReserved): self
    {
        $this->allowReserved = $allowReserved;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = array_filter([
            'contentType' => $this->contentType,
            'style' => $this->style,
            'explode' => $this->explode,
            'allowReserved' => $this->allowReserved,
        ], fn ($value) => $value !== null);

        $headers = array_map(fn ($h) => $h->toArray(), $this->headers);

        return array_merge(
            $result,
            $headers ? ['headers' => $headers] : [],
            $this->extensionPropertiesToArray(),
        );
    }
}
