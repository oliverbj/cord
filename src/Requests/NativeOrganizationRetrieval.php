<?php

namespace Oliverbj\Cord\Requests;

class NativeOrganizationRetrieval extends NativeRequest
{
    public function schema(): array
    {
        $criteriaGroups = $this->cord->criteriaGroups;

        return $this->defineSchema(
            criteriaGroups: $criteriaGroups,
        );
    }

    private function defineSchema(array $criteriaGroups): array
    {
        $schema = [
            'Body' => [
                'Organization' => [],
            ],
        ];

        //Push in the criteria group.
        array_push($schema['Body']['Organization'], $criteriaGroups[0]);

        return $schema;
    }
}
