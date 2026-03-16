<?php

namespace Oliverbj\Cord\Requests;

class NativeOrganizationCreation extends NativeRequest
{
    public function schema(): array
    {
        return [
            'Header' => $this->cord->nativeHeader(),
            'Body' => [
                'Organization' => [
                    'OrgHeader' => $this->buildOrgHeader(),
                ],
            ],
        ];
    }

    private function buildOrgHeader(): array
    {
        $draft = $this->cord->organizationDraft;

        $header = [
            '_attributes' => ['Action' => 'INSERT'],
            'Code' => $this->cord->targetKey,
            'IsActive' => isset($draft['isActive']) ? ($draft['isActive'] ? 'true' : 'false') : 'true',
        ];

        if (isset($draft['fullName'])) {
            $header['FullName'] = $draft['fullName'];
        }

        foreach ([
            'isConsignee'  => 'IsConsignee',
            'isConsignor'  => 'IsConsignor',
            'isForwarder'  => 'IsForwarder',
            'isAirLine'    => 'IsAirLine',
        ] as $draftKey => $xmlTag) {
            if (array_key_exists($draftKey, $draft)) {
                $header[$xmlTag] = $draft[$draftKey] ? 'true' : 'false';
            }
        }

        $addresses = $draft['addresses'] ?? [];
        if ($addresses !== []) {
            $header['OrgAddressCollection'] = [
                'OrgAddress' => count($addresses) === 1 ? $addresses[0] : $addresses,
            ];
        }

        $contacts = $draft['contacts'] ?? [];
        if ($contacts !== []) {
            $header['OrgContactCollection'] = [
                'OrgContact' => count($contacts) === 1 ? $contacts[0] : $contacts,
            ];
        }

        if (isset($draft['closestPort'])) {
            $header['ClosestPort'] = [
                '_attributes' => ['TableName' => 'RefUNLOCO'],
                'Code' => $draft['closestPort'],
            ];
        }

        return $header;
    }
}
