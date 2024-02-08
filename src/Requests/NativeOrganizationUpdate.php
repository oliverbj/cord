<?php

namespace Oliverbj\Cord\Requests;

class NativeOrganizationUpdate extends NativeRequest
{
    public function schema(): array
    {
        $address = $this->cord->address;

        return $this->defineSchema(
            address: $address

        );
    }

    private function defineSchema(array $address): array
    {
        $schema = [
            'Body' => [
                'Organization' => [
                    'Code' => $this->cord->targetKey,
                ],
            ],
        ];

        //Push in addresses (if any)
        if (! empty($address)) {
            array_push($schema['Body']['Organization'], $address);
        }

        return $schema;
    }
}
