<?php

namespace Oliverbj\Cord\Builders;

use Oliverbj\Cord\Attributes\StructuredField;

class OneOffQuoteContainerBuilder
{
    protected array $payload = [];

    #[StructuredField(name: 'type', required: true)]
    public function type(string $code): self
    {
        $this->payload['containerTypeCode'] = $code;

        return $this;
    }

    #[StructuredField]
    public function count(int $value): self
    {
        $this->payload['containerCount'] = $value;

        return $this;
    }

    #[StructuredField(name: 'type_description')]
    public function typeDescription(string $value): self
    {
        $this->payload['containerTypeDescription'] = $value;

        return $this;
    }

    #[StructuredField(name: 'iso_code')]
    public function isoCode(string $value): self
    {
        $this->payload['containerTypeIsoCode'] = $value;

        return $this;
    }

    #[StructuredField]
    public function category(string $code, ?string $description = null): self
    {
        $this->payload['containerTypeCategoryCode'] = $code;
        $this->payload['containerTypeCategoryDescription'] = $description;

        return $this;
    }

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
