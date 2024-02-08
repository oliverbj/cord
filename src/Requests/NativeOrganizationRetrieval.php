<?php

namespace Oliverbj\Cord\Requests;

class NativeOrganizationRetrieval extends NativeRequest
{
    public function schema(): array
    {
        $criteriaGroups = $this->cord->criteriaGroups;
        $addresses = $this->cord->addresses;
        
        return $this->defineSchema(
            criteriaGroups: $criteriaGroups,
            addresses: $addresses
        
        );
    }

    private function defineSchema(array $criteriaGroups, array $addresses): array
    {
        $schema = [
            'Body' => [
                'Organization' => [],
            ],
        ];

        //Push in the criteria group.
        array_push($schema['Body']['Organization'], $criteriaGroups[0]);

        //Push in addresses (if any)
        if(! empty($addresses)){
            array_push($schema['Body']['Organization'], $addresses);
        }
        
        return $schema;
    }
}
