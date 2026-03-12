<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
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
        ->staff()
        ->create()
        ->code('BVO')
        ->loginName('user.test')
        ->password('1234')
        ->fullName('User Test')
        ->email('user.test@test.com')
        ->branch('TLS')
        ->department('FES')
        ->phone('+111')
        ->isActive(true)
        ->country('FR')
        ->replaceGroups(['ORGALL', 'OPSALL'])
        ->withPayload([
            'GlbWorkTime' => [
                '_attributes' => ['Action' => 'Insert'],
                'MondayWorkingHours' => '*******************',
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
        ->toContain('<IsOperational>true</IsOperational>')
        ->toContain('<GlbGroupLink Action="MERGE">')
        ->toContain('<Code>ORGALL</Code>')
        ->toContain('<WorkPhone>+111</WorkPhone>')
        ->toContain('<MondayWorkingHours>*******************</MondayWorkingHours>')
        ->toContain('<HomeBranch TableName="GlbBranch">')
        ->toContain('<Code>TLS</Code>');
});

it('supports disabling code mapping and the withRecepientId alias', function () {
    $xml = Cord::withCompany('CPH')
        ->withRecepientId('PartnerSystem')
        ->withCodeMapping(false)
        ->staff()
        ->create()
        ->code('BVO')
        ->loginName('user.test')
        ->password('1234')
        ->fullName('User Test')
        ->branch('TLS')
        ->department('FES')
        ->country('FR')
        ->inspect();

    expect($xml)
        ->toContain('<CodesMappedToTarget>false</CodesMappedToTarget>');
});

it('builds a native staff update payload for non-collection fields', function () {
    $xml = Cord::withCompany('CPH')
        ->staff('BVO')
        ->update()
        ->fullName('Updated User')
        ->email('updated@example.com')
        ->branch('CPH')
        ->department('OPS')
        ->country('DK')
        ->withPayload([
            'FriendlyName' => 'Updated',
            'Title' => 'Branch Manager',
            'GlbWorkTime' => [
                '_attributes' => ['Action' => 'Update'],
                'MondayWorkingHours' => '********',
            ],
        ])
        ->inspect();

    expect($xml)
        ->toContain('<GlbStaff Action="UPDATE">')
        ->toContain('<Code>BVO</Code>')
        ->toContain('<FullName>Updated User</FullName>')
        ->toContain('<FriendlyName>Updated</FriendlyName>')
        ->toContain('<Title>Branch Manager</Title>')
        ->toContain('<EmailAddress>updated@example.com</EmailAddress>')
        ->toContain('<GlbWorkTime Action="Update">')
        ->toContain('<MondayWorkingHours>********</MondayWorkingHours>')
        ->toContain('<HomeBranch TableName="GlbBranch"><Code>CPH</Code></HomeBranch>')
        ->toContain('<HomeDepartment TableName="GlbDepartment"><Code>OPS</Code></HomeDepartment>')
        ->toContain('<CountryCode TableName="RefCountry"><Code>DK</Code></CountryCode>')
        ->not->toContain('GlbGroupLinkCollection');
});

it('requires a company when creating staff', function () {
    expect(fn () => Cord::staff()
        ->create()
        ->code('BVO')
        ->loginName('user.test')
        ->password('1234')
        ->fullName('User Test')
        ->branch('TLS')
        ->department('FES')
        ->country('FR')
        ->inspect())
        ->toThrow(Exception::class, 'Company code must be provided for native write requests.');
});

it('supports fluent staff create with toPayload and raw payload passthrough', function () {
    $builder = Cord::staff()
        ->create()
        ->code('BVO')
        ->loginName('user.test')
        ->password('1234')
        ->fullName('User Test')
        ->branch('TLS')
        ->department('FES')
        ->country('FR')
        ->replaceGroups(['ORGALL', 'OPSALL'])
        ->withPayload([
            'CustomFieldX' => 'foo',
        ]);

    $payload = $builder->toPayload();

    expect($payload['Code'])->toBe('BVO')
        ->and($payload['LoginName'])->toBe('user.test')
        ->and($payload['GlbGroupLinkCollection']['GlbGroupLink'][0]['GlbGroup']['Code'])->toBe('ORGALL')
        ->and($payload['CustomFieldX'])->toBe('foo');

    $xml = Cord::withCompany('CPH')
        ->staff()
        ->create()
        ->code('BVO')
        ->loginName('user.test')
        ->password('1234')
        ->fullName('User Test')
        ->branch('TLS')
        ->department('FES')
        ->country('FR')
        ->replaceGroups(['ORGALL'])
        ->withPayload([
            'CustomFieldX' => 'foo',
        ])
        ->inspect();

    expect($xml)
        ->toContain('<GlbStaff Action="Insert">')
        ->toContain('<Code>BVO</Code>')
        ->toContain('<GlbGroupLink Action="MERGE">')
        ->toContain('<Code>ORGALL</Code>')
        ->toContain('<CustomFieldX>foo</CustomFieldX>');
});

it('supports fluent staff update setters', function () {
    $xml = Cord::withCompany('CPH')
        ->staff('BVO')
        ->update()
        ->addressLine1('Test 123')
        ->inspect();

    expect($xml)
        ->toContain('<GlbStaff Action="UPDATE">')
        ->toContain('<Code>BVO</Code>')
        ->toContain('<UserAddress1>Test 123</UserAddress1>');
});

it('forces ChangePasswordAtNextLogin when password is set', function () {
    $xml = Cord::withCompany('CPH')
        ->staff('BVO')
        ->update()
        ->password('new-secret')
        ->inspect();

    expect($xml)
        ->toContain('<Password>new-secret</Password>')
        ->toContain('<ChangePasswordAtNextLogin>true</ChangePasswordAtNextLogin>');
});

it('can remove a group on staff update using Action DELETE', function () {
    $xml = Cord::withCompany('CPH')
        ->staff('BVO')
        ->update()
        ->removeGroup('OPS')
        ->inspect();

    expect($xml)
        ->toContain('<GlbGroupLink Action="DELETE">')
        ->toContain('<Code>OPS</Code>');
});

it('returns deterministic validation errors for fluent staff update', function () {
    $errors = null;

    try {
        Cord::staff()
            ->update()
            ->toPayload();
    } catch (ValidationException $e) {
        $errors = $e->errors();
    }

    expect($errors)->toBe([
        'code' => ['The code field is required.'],
    ]);
});

it('returns deterministic validation errors for invalid group codes', function () {
    $errors = null;

    try {
        Cord::staff()
            ->update()
            ->code('BVO')
            ->replaceGroups(['1234', 214]);
    } catch (ValidationException $e) {
        $errors = $e->errors();
    }

    expect($errors)->toBe([
        'groups.1' => ['Group codes must be strings.'],
    ]);
});

it('exposes structured staff method metadata via describe', function () {
    $description = Cord::staff()->describe();

    expect($description['resource'])->toBe('staff')
        ->and($description['actions'])->toBe(['create', 'update', 'upsert'])
        ->and($description['methods'])->toBeArray()
        ->and(collect($description['methods'])->firstWhere('name', 'code'))->toMatchArray([
            'name' => 'code',
            'parameters' => ['code' => 'string'],
            'required_for' => ['create', 'update'],
        ])
        ->and(collect($description['methods'])->firstWhere('name', 'branch'))->toMatchArray([
            'name' => 'branch',
            'parameters' => ['branch' => 'string'],
            'required_for' => ['create'],
        ])
        ->and(collect($description['methods'])->firstWhere('name', 'phone'))->toMatchArray([
            'name' => 'phone',
            'parameters' => ['phone' => 'string'],
            'required_for' => [],
        ])
        ->and(collect($description['methods'])->firstWhere('name', 'replaceGroups'))->toMatchArray([
            'name' => 'replaceGroups',
            'parameters' => ['groups' => 'string[]'],
            'required_for' => [],
        ])
        ->and(collect($description['methods'])->firstWhere('name', 'removeGroup'))->toMatchArray([
            'name' => 'removeGroup',
            'parameters' => ['code' => 'string'],
            'required_for' => ['update'],
        ]);
});
