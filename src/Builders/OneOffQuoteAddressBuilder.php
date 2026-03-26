<?php

namespace Oliverbj\Cord\Builders;

use Oliverbj\Cord\Attributes\StructuredField;

class OneOffQuoteAddressBuilder
{
    protected array $payload = [];

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

    #[StructuredField(required: true)]
    public function city(string $value): self
    {
        $this->payload['city'] = $value;

        return $this;
    }

    #[StructuredField(name: 'company_name')]
    public function companyName(string $value): self
    {
        $this->payload['companyName'] = $value;

        return $this;
    }

    #[StructuredField(required: true)]
    public function country(string $code): self
    {
        $this->payload['countryCode'] = $code;

        return $this;
    }

    #[StructuredField]
    public function email(string $value): self
    {
        $this->payload['email'] = $value;

        return $this;
    }

    #[StructuredField]
    public function phone(string $value): self
    {
        $this->payload['phone'] = $value;

        return $this;
    }

    #[StructuredField]
    public function fax(string $value): self
    {
        $this->payload['fax'] = $value;

        return $this;
    }

    #[StructuredField]
    public function postcode(string $value): self
    {
        $this->payload['postcode'] = $value;

        return $this;
    }

    #[StructuredField]
    public function state(string $code, ?string $description = null): self
    {
        $this->payload['stateCode'] = $code;
        $this->payload['stateDescription'] = $description;

        return $this;
    }

    #[StructuredField(name: 'organization_code')]
    public function organizationCode(string $value): self
    {
        $this->payload['organizationCode'] = $value;

        return $this;
    }

    #[StructuredField]
    public function port(string $code, ?string $name = null): self
    {
        $this->payload['portCode'] = $code;
        $this->payload['portName'] = $name;

        return $this;
    }

    #[StructuredField(name: 'address_short_code')]
    public function addressShortCode(string $value): self
    {
        $this->payload['addressShortCode'] = $value;

        return $this;
    }

    #[StructuredField(name: 'address_override')]
    public function addressOverride(bool $value): self
    {
        $this->payload['addressOverride'] = $value;

        return $this;
    }

    #[StructuredField(name: 'gov_reg_num')]
    public function govRegNum(string $value): self
    {
        $this->payload['govRegNum'] = $value;

        return $this;
    }

    #[StructuredField(name: 'gov_reg_num_type')]
    public function govRegNumType(string $code, ?string $description = null): self
    {
        $this->payload['govRegNumTypeCode'] = $code;
        $this->payload['govRegNumTypeDescription'] = $description;

        return $this;
    }

    #[StructuredField(name: 'screening_status')]
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
