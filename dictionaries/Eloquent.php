<?php

namespace Illuminate\Database\Eloquent {
    class Model
    {
        /**
         * @template T
         * @param ?class-string<T> $resource
         * @return ($resource is null ? GuessedResource<static> : T)
         */
        public function toResource(?string $resource): mixed {}
    }
}
