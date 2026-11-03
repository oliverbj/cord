<?php

namespace Oliverbj\Cord\Builders;

use Oliverbj\Cord\Attributes\StructuredField;

class JobPartyBuilder
{
    protected array $payload = [];

    #[StructuredField(name: 'address_type', required: true)]
    public function addressType(string $value): self
    {
        $this->payload['addressType'] = $value;

        return $this;
    }

    #[StructuredField(name: 'organization_code')]
    public function organizationCode(string $value): self
    {
        $this->payload['organizationCode'] = $value;

        return $this;
    }

    #[StructuredField(name: 'company_name')]
    public function companyName(string $value): self
    {
        $this->payload['companyName'] = $value;

        return $this;
    }

    #[StructuredField(name: 'address_line_1')]
    public function addressLine1(string $value): self
    {
        $this->payload['address1'] = $value;

        return $this;
    }

    #[StructuredField(name: 'address_line_2')]
    public function addressLine2(string $value): self
    {
        $this->payload['address2'] = $value;

        return $this;
    }

    #[StructuredField]
    public function city(string $value): self
    {
        $this->payload['city'] = $value;

        return $this;
    }

    #[StructuredField]
    public function state(string $value): self
    {
        $this->payload['state'] = $value;

        return $this;
    }

    #[StructuredField]
    public function postcode(string $value): self
    {
        $this->payload['postcode'] = $value;

        return $this;
    }

    #[StructuredField]
    public function country(string $code): self
    {
        $this->payload['countryCode'] = $code;

        return $this;
    }

    #[StructuredField]
    public function phone(string $value): self
    {
        $this->payload['phone'] = $value;

        return $this;
    }

    #[StructuredField]
    public function email(string $value): self
    {
        $this->payload['email'] = $value;

        return $this;
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}
