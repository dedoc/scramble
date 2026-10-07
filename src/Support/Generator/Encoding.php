<?php

namespace Dedoc\Scramble\Support\Generator;

use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\Contracts\OpenApiSerializable;
use JsonSerializable;

class Encoding implements JsonSerializable, OpenApiSerializable
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
        /**
         * OAS 3.2.0+
         *
         * @var array<string, Encoding>
         */
        public array $encoding = [],
        /**
         * OAS 3.2.0+
         *
         * @var list<Encoding>
         */
        public array $prefixEncoding = [],
        /** OAS 3.2.0+ */
        public ?Encoding $itemEncoding = null,
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
     * @param  array<string, Encoding>  $encoding
     * @return $this
     */
    public function setEncoding(array $encoding): self
    {
        $this->encoding = $encoding;

        return $this;
    }

    /**
     * @param  list<Encoding>  $prefixEncoding
     * @return $this
     */
    public function setPrefixEncoding(array $prefixEncoding): self
    {
        $this->prefixEncoding = array_values($prefixEncoding);

        return $this;
    }

    /**
     * @return $this
     */
    public function setItemEncoding(?Encoding $itemEncoding): self
    {
        $this->itemEncoding = $itemEncoding;

        return $this;
    }

    /**
     * @return $this
     */
    public function addEncoding(string $key, Encoding $encoding): self
    {
        $this->encoding[$key] = $encoding;

        return $this;
    }

    /**
     * @return $this
     */
    public function addPrefixEncoding(Encoding $encoding): self
    {
        $this->prefixEncoding[] = $encoding;

        return $this;
    }

    /**
     * @return $this
     */
    public function removeEncoding(string $key): self
    {
        unset($this->encoding[$key]);

        return $this;
    }

    /**
     * @return $this
     */
    public function removePrefixEncoding(int $index): self
    {
        unset($this->prefixEncoding[$index]);
        $this->prefixEncoding = array_values($this->prefixEncoding);

        return $this;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function toArray(): mixed
    {
        return $this->serializeAs31();
    }

    public function serializeAs31(): mixed
    {
        return $this->serialize(OpenApiVersion::V3_1, fn (OpenApiSerializable $item) => $item->serializeAs31());
    }

    public function serializeAs32(): mixed
    {
        return $this->serialize(OpenApiVersion::V3_2, fn (OpenApiSerializable $item) => $item->serializeAs32());
    }

    /**
     * @param  callable(OpenApiSerializable): mixed  $serializeItem
     */
    private function serialize(OpenApiVersion $version, callable $serializeItem): mixed
    {
        $result = array_filter([
            'contentType' => $this->contentType,
            'style' => $this->style,
            'explode' => $this->explode,
            'allowReserved' => $this->allowReserved,
        ], fn ($value) => $value !== null);

        $headers = array_map($serializeItem, $this->headers);

        $result = array_merge(
            $result,
            $headers ? ['headers' => $headers] : [],
        );

        if ($version === OpenApiVersion::V3_2) {
            if ($this->encoding) {
                $result['encoding'] = array_map($serializeItem, $this->encoding);
            }

            if ($this->prefixEncoding) {
                $result['prefixEncoding'] = array_values(array_map($serializeItem, $this->prefixEncoding));
            }

            if ($this->itemEncoding !== null) {
                $result['itemEncoding'] = $serializeItem($this->itemEncoding);
            }
        }

        $result = array_merge(
            $result,
            $this->extensionPropertiesToArray(),
        );

        return $result ?: (object) [];
    }
}
