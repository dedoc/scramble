<?php

namespace Dedoc\Scramble\DocumentTransformers;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\OpenApiVersion;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Illuminate\Support\Str;

class CleanupUnusedResponseReferencesTransformer implements DocumentTransformer
{
    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $components = $document->components;
        $responses = $components->responses;
        $serializedDocument = match ($context->config->openApiVersion()) {
            OpenApiVersion::V3_1 => $document->serializeAs31(),
            OpenApiVersion::V3_2 => $document->serializeAs32(),
        };
        $serializedDocumentJson = json_encode($serializedDocument, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        foreach ($responses as $responseName => $reference) {
            if (! $this->isResponseReferenceUsed($serializedDocumentJson, $context->references->responses->uniqueName($responseName))) {
                $components->removeResponse($responseName);
            }
        }
    }

    private function isResponseReferenceUsed(string $serializedDocumentJson, string $responseName): bool
    {
        $referencePath = "#/components/responses/{$responseName}";

        return Str::contains($serializedDocumentJson, $referencePath);
    }
}
