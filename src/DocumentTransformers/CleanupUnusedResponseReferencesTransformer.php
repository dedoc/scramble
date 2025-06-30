<?php

namespace Dedoc\Scramble\DocumentTransformers;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Illuminate\Support\Str;

class CleanupUnusedResponseReferencesTransformer implements DocumentTransformer
{
    private string $serializedDocumentJson;

    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $components = $document->components;
        $responses = $components->responses;

        foreach ($responses as $responseName => $reference) {
            if (! $this->isResponseReferenceUsed($document, $context->references->responses->uniqueName($responseName))) {
                $components->removeResponse($responseName);
            }
        }
    }

    private function isResponseReferenceUsed(OpenApi $document, string $responseName): bool
    {
        if (! isset($this->serializedDocumentJson)) {
            $this->serializedDocumentJson = json_encode($document->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        $referencePath = "#/components/responses/{$responseName}";

        return Str::contains($this->serializedDocumentJson, $referencePath);
    }
}
