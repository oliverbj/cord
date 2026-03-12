<?php

namespace Oliverbj\Cord\Builders;

class OneOffQuoteAddressBuilder
{
    protected array $payload = [];

    public function addressLine1(string $value): self
    {
        $this->payload['address1'] = $value;

        return $this;
    }

    public function addressLine2(string $value): self
    {
        $this->payload['address2'] = $value;

        return $this;
    }

    public function city(string $value): self
    {
        $this->payload['city'] = $value;

        return $this;
    }

    public function companyName(string $value): self
    {
        $this->payload['companyName'] = $value;

        return $this;
    }

    public function country(string $code): self
    {
        $this->payload['countryCode'] = $code;

        return $this;
    }

    public function email(string $value): self
    {
        $this->payload['email'] = $value;

        return $this;
    }

    public function phone(string $value): self
    {
        $this->payload['phone'] = $value;

        return $this;
    }

    public function fax(string $value): self
    {
        $this->payload['fax'] = $value;

        return $this;
    }

    public function postcode(string $value): self
    {
        $this->payload['postcode'] = $value;

        return $this;
    }

    public function state(string $code, ?string $description = null): self
    {
        $this->payload['stateCode'] = $code;
        $this->payload['stateDescription'] = $description;

        return $this;
    }

    public function organizationCode(string $value): self
    {
        $this->payload['organizationCode'] = $value;

        return $this;
    }

    public function port(string $code, ?string $name = null): self
    {
        $this->payload['portCode'] = $code;
        $this->payload['portName'] = $name;

        return $this;
    }

    public function addressShortCode(string $value): self
    {
        $this->payload['addressShortCode'] = $value;

        return $this;
    }

    public function addressOverride(bool $value): self
    {
        $this->payload['addressOverride'] = $value;

        return $this;
    }

    public function govRegNum(string $value): self
    {
        $this->payload['govRegNum'] = $value;

        return $this;
    }

    public function govRegNumType(string $code, ?string $description = null): self
    {
        $this->payload['govRegNumTypeCode'] = $code;
        $this->payload['govRegNumTypeDescription'] = $description;

        return $this;
    }

    public function screeningStatus(string $code, ?string $description = null): self
    {
        $this->payload['screeningStatusCode'] = $code;
        $this->payload['screeningStatusDescription'] = $description;

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
