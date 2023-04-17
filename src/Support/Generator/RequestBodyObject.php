<?php

namespace Dedoc\Scramble\Support\Generator;

class RequestBodyObject
{
    /** @var array<string, Schema> */
    private array $content;

    public static function make()
    {
        return new self();
    }

    public function setContent(string $type, Schema $schema)
    {
        $this->content[$type] = $schema;

        return $this;
    }

    public function toArray(OpenApi $openApi)
    {
        $result = [];

        $content = [];
        foreach ($this->content as $mediaType => $schema) {
            $content[$mediaType] = [
                'schema' => $schema->toArray($openApi),
            ];
        }

        $result['content'] = $content;

        return $result;
    }
}
