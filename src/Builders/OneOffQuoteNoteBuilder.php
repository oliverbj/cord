<?php

namespace Oliverbj\Cord\Builders;

use Oliverbj\Cord\Attributes\StructuredField;

class OneOffQuoteNoteBuilder
{
    protected array $payload = [];

    /**
     * Set the note key.
     */
    #[StructuredField(required: true)]
    public function key(string $value): self
    {
        $this->payload['key'] = $value;

        return $this;
    }

    /**
     * Set the note text.
     */
    #[StructuredField(required: true)]
    public function text(string $value): self
    {
        $this->payload['text'] = $value;

        return $this;
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}
