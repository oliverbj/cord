<?php

namespace Oliverbj\Cord\Builders;

use Oliverbj\Cord\Attributes\StructuredField;

class OrganizationAddressBuilder
{
    protected array $payload = [];

    #[StructuredField(required: true)]
    public function code(string $value): self
    {
        $this->payload['code'] = $value;

        return $this;
    }

    #[StructuredField(name: 'address_one', required: true)]
    public function addressOne(string $value): self
    {
        $this->payload['addressOne'] = $value;

        return $this;
    }

    #[StructuredField(name: 'address_two')]
    public function addressTwo(string $value): self
    {
        $this->payload['addressTwo'] = $value;

        return $this;
    }

    #[StructuredField(required: true)]
    public function country(string $code): self
    {
        $this->payload['country'] = $code;

        return $this;
    }

    #[StructuredField(required: true)]
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

    #[StructuredField(name: 'related_port')]
    public function relatedPort(string $code): self
    {
        $this->payload['relatedPort'] = $code;

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
    public function mobile(string $value): self
    {
        $this->payload['mobile'] = $value;

        return $this;
    }

    #[StructuredField]
    public function email(string $value): self
    {
        $this->payload['email'] = $value;

        return $this;
    }

    #[StructuredField(name: 'drop_mode_fcl')]
    public function dropModeFCL(string $value): self
    {
        $this->payload['dropModeFCL'] = $value;

        return $this;
    }

    #[StructuredField(name: 'drop_mode_lcl')]
    public function dropModeLCL(string $value): self
    {
        $this->payload['dropModeLCL'] = $value;

        return $this;
    }

    #[StructuredField(name: 'drop_mode_air')]
    public function dropModeAIR(string $value): self
    {
        $this->payload['dropModeAIR'] = $value;

        return $this;
    }

    #[StructuredField]
    public function active(bool $value): self
    {
        $this->payload['active'] = $value ? 'true' : 'false';

        return $this;
    }

    /**
     * Add one capability (address type) to this address.
     *
     * Can be called multiple times. Each call appends one capability.
     *
     * Example:
     * `->capability('OFC', isMainAddress: true)`
     */
    public function capability(string $addressType, bool $isMainAddress = false): self
    {
        if (! isset($this->payload['capabilities'])) {
            $this->payload['capabilities'] = [];
        }

        $this->payload['capabilities'][] = [
            'AddressType' => $addressType,
            'IsMainAddress' => $isMainAddress ? 'true' : 'false',
        ];

        return $this;
    }

    /**
     * Set all capabilities at once from an array.
     *
     * Each item must have `address_type` and optionally `is_main_address`.
     *
     * Example:
     * `->capabilities([['address_type' => 'OFC', 'is_main_address' => false]])`
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    #[StructuredField(
        schema: [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'address_type' => ['type' => 'string'],
                    'is_main_address' => ['type' => ['boolean', 'string']],
                ],
                'required' => ['address_type'],
            ],
        ]
    )]
    public function capabilities(array $items): self
    {
        foreach ($items as $item) {
            $this->capability(
                $item['address_type'] ?? $item['AddressType'] ?? '',
                filter_var($item['is_main_address'] ?? $item['IsMainAddress'] ?? false, FILTER_VALIDATE_BOOLEAN),
            );
        }

        return $this;
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}
