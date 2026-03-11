<?php

namespace Oliverbj\Cord\Requests;

class NativeOrganizationRetrieval extends NativeRequest
{
    public function schema(): array
    {
        return $this->defineSchema($this->cord->criteriaGroups);
    }

    private function defineSchema(array $criteriaGroups): array
    {
        return [
            'Body' => [
                'Organization' => $criteriaGroups,
            ],
        ];
    }
}
