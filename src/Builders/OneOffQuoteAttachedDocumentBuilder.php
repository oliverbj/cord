<?php

namespace Oliverbj\Cord\Builders;

class OneOffQuoteAttachedDocumentBuilder
{
    protected array $payload = [];

    /**
     * Set the document file name.
     */
    public function fileName(string $value): self
    {
        $this->payload['fileName'] = $value;

        return $this;
    }

    /**
     * Set the base64 encoded file content.
     */
    public function imageData(string $value): self
    {
        $this->payload['imageData'] = $value;

        return $this;
    }

    /**
     * Set the CargoWise document type code.
     */
    public function type(string $code): self
    {
        $this->payload['typeCode'] = $code;

        return $this;
    }

    /**
     * Set whether the document should be published.
     */
    public function isPublished(bool $value): self
    {
        $this->payload['isPublished'] = $value;

        return $this;
    }

    /**
     * Merge raw fields into the attached document payload.
     */
    public function withPayload(array $payload): self
    {
        $this->payload['attributes'] = array_replace_recursive(
            $this->payload['attributes'] ?? [],
            $payload
        );

        return $this;
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}
