<?php

namespace Oliverbj\Cord\Builders;

use Oliverbj\Cord\Attributes\StructuredField;

class OneOffQuoteChargeLineBuilder
{
    protected array $payload = [];

    #[StructuredField(name: 'charge_code', required: true)]
    public function chargeCode(string $code): self
    {
        $this->payload['chargeCode'] = $code;

        return $this;
    }

    #[StructuredField(name: 'charge_code_group')]
    public function chargeCodeGroup(string $code, ?string $description = null): self
    {
        $this->payload['chargeCodeGroup'] = $code;
        $this->payload['chargeCodeGroupDescription'] = $description;

        return $this;
    }

    #[StructuredField(required: true)]
    public function description(string $value): self
    {
        $this->payload['description'] = $value;

        return $this;
    }

    #[StructuredField(name: 'cost_amount')]
    public function costAmount(float|int|string $value, string $currencyCode): self
    {
        $this->payload['costAmount'] = [
            'value' => $value,
            'currencyCode' => $currencyCode,
        ];

        return $this;
    }

    #[StructuredField(name: 'sell_amount')]
    public function sellAmount(float|int|string $value, string $currencyCode): self
    {
        $this->payload['sellAmount'] = [
            'value' => $value,
            'currencyCode' => $currencyCode,
        ];

        return $this;
    }

    #[StructuredField]
    public function debtor(string $type, string $key): self
    {
        $this->payload['debtorType'] = $type;
        $this->payload['debtorKey'] = $key;

        return $this;
    }

    #[StructuredField(name: 'display_sequence')]
    public function displaySequence(int $value): self
    {
        $this->payload['displaySequence'] = $value;

        return $this;
    }

    #[StructuredField]
    public function branch(string $code, ?string $name = null): self
    {
        $this->payload['branchCode'] = $code;
        $this->payload['branchName'] = $name;

        return $this;
    }

    #[StructuredField]
    public function department(string $code, ?string $name = null): self
    {
        $this->payload['departmentCode'] = $code;
        $this->payload['departmentName'] = $name;

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
