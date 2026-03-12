<?php

namespace Oliverbj\Cord\Builders;

class OneOffQuoteChargeLineBuilder
{
    protected array $payload = [];

    public function chargeCode(string $code): self
    {
        $this->payload['chargeCode'] = $code;

        return $this;
    }

    public function chargeCodeGroup(string $code, ?string $description = null): self
    {
        $this->payload['chargeCodeGroup'] = $code;
        $this->payload['chargeCodeGroupDescription'] = $description;

        return $this;
    }

    public function description(string $value): self
    {
        $this->payload['description'] = $value;

        return $this;
    }

    public function costAmount(float|int|string $value, string $currencyCode): self
    {
        $this->payload['costAmount'] = [
            'value' => $value,
            'currencyCode' => $currencyCode,
        ];

        return $this;
    }

    public function sellAmount(float|int|string $value, string $currencyCode): self
    {
        $this->payload['sellAmount'] = [
            'value' => $value,
            'currencyCode' => $currencyCode,
        ];

        return $this;
    }

    public function debtor(string $type, string $key): self
    {
        $this->payload['debtorType'] = $type;
        $this->payload['debtorKey'] = $key;

        return $this;
    }

    public function displaySequence(int $value): self
    {
        $this->payload['displaySequence'] = $value;

        return $this;
    }

    public function branch(string $code, ?string $name = null): self
    {
        $this->payload['branchCode'] = $code;
        $this->payload['branchName'] = $name;

        return $this;
    }

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
