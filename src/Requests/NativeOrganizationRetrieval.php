<?php

namespace Oliverbj\Cord\Requests;

class NativeOrganizationRetrieval extends NativeRequest
{
    public function schema(): array
    {
        $criteriaGroups = $this->cord->criteriaGroups;
        $address = $this->cord->address;

        return $this->defineSchema(
            criteriaGroups: $criteriaGroups,
            addresses: $addresses

        );
    }

    private function defineSchema(array $criteriaGroups, array $address): array
    {
        $schema = [
            'Body' => [
                'Organization' => [],
            ],
        ];

        //Push in the criteria group.
        array_push($schema['Body']['Organization'], $criteriaGroups[0]);

        //Push in addresses (if any)
        if (! empty($address)) {
            array_push($schema['Body']['Organization'], $address);
        }

        return $schema;
    }
}
