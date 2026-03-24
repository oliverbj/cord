<?php

namespace Oliverbj\Cord\Requests;

class NativeStaffRetrieval extends NativeRequest
{
    public function schema(): array
    {
        return $this->defineSchema($this->cord->criteriaGroups);
    }

    private function defineSchema(array $criteriaGroups): array
    {
        return [
            'Body' => [
                'Staff' => $criteriaGroups,
            ],
        ];
    }
}
