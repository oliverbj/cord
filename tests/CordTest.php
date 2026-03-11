<?php

use Illuminate\Support\Facades\Http;
use Oliverbj\Cord\Facades\Cord;

it('builds request xml without sending a network request when inspecting', function () {
    Http::fake();

    $xml = Cord::shipment('SMIA12345678')->inspect();

    Http::assertNothingSent();

    expect($xml)
        ->toContain('<UniversalShipmentRequest>')
        ->toContain('<Type>ForwardingShipment</Type>')
        ->toContain('<Key>SMIA12345678</Key>');
});

it('includes all native criteria groups in generated xml', function () {
    $xml = Cord::organization()
        ->criteriaGroup([
            [
                'Entity' => 'OrgHeader',
                'FieldName' => 'Code',
                'Value' => 'US%',
            ],
        ], type: 'Partial')
        ->criteriaGroup([
            [
                'Entity' => 'OrgHeader',
                'FieldName' => 'IsBroker',
                'Value' => 'True',
            ],
        ], type: 'Partial')
        ->inspect();

    expect(substr_count($xml, '<CriteriaGroup Type="Partial">'))->toBe(2);
});

it('accepts the documented flat address capability payload', function () {
    $xml = Cord::withCompany('CPH')
        ->organization('SAGFURHEL')
        ->addAddress([
            'code' => 'MAIN STREET NO. 1',
            'addressOne' => 'Main Street',
            'addressTwo' => 'Number One',
            'country' => 'US',
            'city' => 'Anytown',
            'state' => 'NY',
            'postcode' => '12345',
            'relatedPort' => 'USNYC',
            'capabilities' => [
                'AddressType' => 'OFC',
                'IsMainAddress' => 'false',
            ],
        ])
        ->inspect();

    expect($xml)
        ->toContain('<OrgAddressCapability Action="INSERT">')
        ->toContain('<AddressType>OFC</AddressType>')
        ->toContain('<IsMainAddress>false</IsMainAddress>');
});

it('allows adding a contact without documents to deliver', function () {
    $xml = Cord::withCompany('CPH')
        ->organization('SAGFURHEL')
        ->addContact([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ])
        ->inspect();

    expect($xml)
        ->toContain('<ContactName>Jane Doe</ContactName>')
        ->toContain('<Email>jane@example.com</Email>')
        ->not->toContain('OrgDocumentCollection');
});

it('supports the receivable alias for document requests', function () {
    $xml = Cord::receivable('INV-001')
        ->withDocuments()
        ->inspect();

    expect($xml)
        ->toContain('<Type>AccountingInvoice</Type>')
        ->toContain('<Key>AR INV INV-001</Key>');
});

it('builds a native staff creation payload with headers, groups, and working hours', function () {
    $xml = Cord::withCompany('CPH')
        ->addStaff([
            'code' => 'BVO',
            'loginName' => 'user.test',
            'password' => '1234',
            'fullName' => 'User Test',
            'friendlyName' => 'User Test',
            'addressOne' => 'Test address',
            'city' => 'Tst city',
            'postcode' => '31700',
            'title' => 'Operations Specialist',
            'workPhone' => '+111',
            'email' => 'user.test@test.com',
            'homeBranch' => 'TLS',
            'homeDepartment' => 'FES',
            'country' => 'FR',
            'groups' => ['ORGALL', 'OPSALL'],
            'workingHours' => [
                'monday' => '*******************',
                'tuesday' => '*******************',
            ],
        ])
        ->inspect();

    expect($xml)
        ->toContain('<Native xmlns="http://www.cargowise.com/Schemas/Native">')
        ->toContain('<CodesMappedToTarget>true</CodesMappedToTarget>')
        ->toContain('<Company><Code>CPH</Code></Company>')
        ->toContain('<EnterpriseID>DEMO1</EnterpriseID>')
        ->toContain('<ServerID>TRN</ServerID>')
        ->toContain('<GlbStaff Action="Insert">')
        ->toContain('<Code>BVO</Code>')
        ->toContain('<GlbGroupLink Action="MERGE">')
        ->toContain('<Code>ORGALL</Code>')
        ->toContain('<MondayWorkingHours>*******************</MondayWorkingHours>')
        ->toContain('<HomeBranch TableName="GlbBranch">')
        ->toContain('<Code>TLS</Code>');
});

it('supports disabling code mapping and the withRecepientId alias', function () {
    $xml = Cord::withCompany('CPH')
        ->withRecepientId('PartnerSystem')
        ->withCodeMapping(false)
        ->addStaff([
            'code' => 'BVO',
            'loginName' => 'user.test',
            'password' => '1234',
            'fullName' => 'User Test',
            'homeBranch' => 'TLS',
            'homeDepartment' => 'FES',
            'country' => 'FR',
        ])
        ->inspect();

    expect($xml)
        ->toContain('<CodesMappedToTarget>false</CodesMappedToTarget>');
});

it('requires a company when creating staff', function () {
    expect(fn () => Cord::addStaff([
        'code' => 'BVO',
        'loginName' => 'user.test',
        'password' => '1234',
        'fullName' => 'User Test',
        'homeBranch' => 'TLS',
        'homeDepartment' => 'FES',
        'country' => 'FR',
    ]))->toThrow(Exception::class, 'Company code must be provided when creating staff.');
});
