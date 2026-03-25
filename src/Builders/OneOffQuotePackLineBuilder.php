<?php

namespace Oliverbj\Cord\Builders;

use Oliverbj\Cord\Attributes\StructuredField;

class OneOffQuotePackLineBuilder
{
    protected array $payload = [];

    #[StructuredField(name: 'pack_type', required: true)]
    public function packageType(string $code): self
    {
        $this->payload['packTypeCode'] = $code;

        return $this;
    }

    #[StructuredField(required: true)]
    public function quantity(int $value): self
    {
        $this->payload['quantity'] = $value;

        return $this;
    }

    #[StructuredField]
    public function weight(float|int|string $value, string $unitCode): self
    {
        $this->payload['weightValue'] = $value;
        $this->payload['weightUnitCode'] = $unitCode;

        return $this;
    }

    #[StructuredField]
    public function volume(float|int|string $value, string $unitCode): self
    {
        $this->payload['volumeValue'] = $value;
        $this->payload['volumeUnitCode'] = $unitCode;

        return $this;
    }

    #[StructuredField]
    public function length(float|int|string $value, string $unitCode): self
    {
        $this->payload['lengthValue'] = $value;
        $this->payload['lengthUnitCode'] = $unitCode;

        return $this;
    }

    #[StructuredField]
    public function width(float|int|string $value, string $unitCode): self
    {
        $this->payload['widthValue'] = $value;
        $this->payload['widthUnitCode'] = $unitCode;

        return $this;
    }

    #[StructuredField]
    public function height(float|int|string $value, string $unitCode): self
    {
        $this->payload['heightValue'] = $value;
        $this->payload['heightUnitCode'] = $unitCode;

        return $this;
    }

    #[StructuredField]
    public function description(string $value): self
    {
        $this->payload['description'] = $value;

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
