<?php

namespace Oliverbj\Cord\Requests;

class NativeOrganizationUpdate extends NativeRequest
{
    public function schema(): array
    {
        return $this->defineSchema($this->cord->address);
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
