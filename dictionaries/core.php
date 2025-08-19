<?php

namespace Illuminate\Http\Resources\Json
{
    /**
     * @template TResource
     * @template TAdditional = array<int, mixed>
     */
    class JsonResource
    {
        /**
         * @template TNewAdditional
         * @param TNewAdditional $additional
         * @return $this
         * @self-out-type self<_, TNewAdditional>
         */
        public function additional($additional): self {}
    }
}
