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

it('builds a one-off quote create payload with empty key', function () {
    $xml = Cord::withCompany('CPH')
        ->oneOffQuote()
        ->create()
        ->branch('A01')
        ->department('FES')
        ->transportMode('SEA')
        ->portOfOrigin('AUSYD')
        ->portOfDestination('NZAKL')
        ->serviceLevel('STD')
        ->incoterm('DAP')
        ->additionalTerms('Export Only')
        ->isDomesticFreight(false)
        ->totalWeight(5000, 'KG')
        ->totalVolume(19.2, 'M3')
        ->goodsValue(15000, 'AUD')
        ->inspect();

    expect($xml)
        ->toContain('<UniversalShipmentRequest>')
        ->toContain('<Type>OneOffQuote</Type>')
        ->toContain('<TransportMode><Code>SEA</Code></TransportMode>')
        ->toContain('<PortOfOrigin><Code>AUSYD</Code></PortOfOrigin>')
        ->toContain('<PortOfDestination><Code>NZAKL</Code></PortOfDestination>')
        ->toContain('<AdditionalTerms>Export Only</AdditionalTerms>')
        ->toContain('<IsDomesticFreight>false</IsDomesticFreight>')
        ->toContain('<GoodsValue>15000</GoodsValue>');

    expect(str_contains($xml, '<Key></Key>') || str_contains($xml, '<Key/>'))->toBeTrue();
});

it('supports typed addresses and charge lines for one-off quote create', function () {
    $xml = Cord::withCompany('CPH')
        ->oneOffQuote()
        ->create()
        ->branch('A01')
        ->department('FES')
        ->transportMode('SEA')
        ->portOfOrigin('AUSYD')
        ->portOfDestination('NZAKL')
        ->clientAddress(fn ($a) => $a
            ->addressLine1('3 TENTH AVENUE')
            ->city('OYSTER BAY')
            ->country('AU')
            ->organizationCode('AU10IMSYD')
            ->phone('+61288361212'))
        ->pickupAddress(fn ($a) => $a
            ->addressLine1('3 TENTH AVENUE')
            ->city('OYSTER BAY')
            ->country('AU'))
        ->deliveryAddress(fn ($a) => $a
            ->addressLine1('10 TEST ROAD')
            ->city('AUCKLAND')
            ->country('NZ'))
        ->addChargeLine(fn ($c) => $c
            ->chargeCode('FRT')
            ->description('International Freight')
            ->costAmount('500.0000', 'AUD')
            ->sellAmount('1500.0000', 'AUD'))
        ->addChargeLine(fn ($c) => $c
            ->chargeCode('SURF')
            ->description('Surcharge Fees')
            ->costAmount('250.0000', 'NZD')
            ->sellAmount('300.0000', 'NZD')
            ->branch('B01', 'Branch 2')
            ->department('OPS', 'Operations'))
        ->inspect();

    expect($xml)
        ->toContain('<AddressType>QuotationClientAddress</AddressType>')
        ->toContain('<AddressType>OneOffQuotePickupAddress</AddressType>')
        ->toContain('<AddressType>OneOffQuoteDeliveryAddress</AddressType>')
        ->toContain('<OrganizationCode>AU10IMSYD</OrganizationCode>')
        ->toContain('<ChargeCode><Code>FRT</Code></ChargeCode>')
        ->toContain('<ChargeCode><Code>SURF</Code></ChargeCode>')
        ->toContain('<CostOSCurrency><Code>AUD</Code></CostOSCurrency>')
        ->toContain('<SellOSCurrency><Code>AUD</Code></SellOSCurrency>')
        ->toContain('<CostOSCurrency><Code>NZD</Code></CostOSCurrency>')
        ->toContain('<SellOSCurrency><Code>NZD</Code></SellOSCurrency>')
        ->toContain('<Branch><Code>B01</Code><Name>Branch 2</Name></Branch>')
        ->toContain('<Department><Code>OPS</Code><Name>Operations</Name></Department>');

    expect(substr_count($xml, '<ChargeLine>'))->toBe(2);
    expect((bool) preg_match('/<JobCosting>.*<Branch><Code>A01<\/Code><\/Branch>.*<Department><Code>FES<\/Code><\/Department>/s', $xml))->toBeTrue();
});

it('supports attached documents for one-off quote create', function () {
    $xml = Cord::withCompany('CPH')
        ->oneOffQuote()
        ->create()
        ->branch('A01')
        ->department('FES')
        ->transportMode('SEA')
        ->portOfOrigin('AUSYD')
        ->portOfDestination('NZAKL')
        ->addAttachedDocument(fn ($d) => $d
            ->fileName('quote.pdf')
            ->imageData(base64_encode('quote-data'))
            ->type('QUO')
            ->isPublished(true))
        ->addAttachedDocument(fn ($d) => $d
            ->fileName('terms.txt')
            ->imageData(base64_encode('terms'))
            ->type('TXT'))
        ->inspect();

    expect($xml)
        ->toContain('<AttachedDocumentCollection>')
        ->toContain('<FileName>quote.pdf</FileName>')
        ->toContain('<Type><Code>QUO</Code></Type>')
        ->toContain('<IsPublished>true</IsPublished>')
        ->toContain('<FileName>terms.txt</FileName>')
        ->toContain('<Type><Code>TXT</Code></Type>');

    expect(substr_count($xml, '<AttachedDocument>'))->toBe(2);
});

it('supports one-off quote raw payload merge without clobbering fluent fields', function () {
    $xml = Cord::withCompany('CPH')
        ->oneOffQuote()
        ->create()
        ->branch('A01')
        ->department('FES')
        ->transportMode('SEA')
        ->portOfOrigin('AUSYD')
        ->portOfDestination('NZAKL')
        ->withPayload([
            'CustomizedFieldCollection' => [
                'CustomizedField' => [
                    'DataType' => 'String',
                    'Key' => 'Test User',
                    'Value' => 'Janice Testing',
                ],
            ],
        ])
        ->inspect();

    expect($xml)
        ->toContain('<TransportMode><Code>SEA</Code></TransportMode>')
        ->toContain('<CustomizedFieldCollection>')
        ->toContain('<Key>Test User</Key>')
        ->toContain('<Value>Janice Testing</Value>');
});

it('exposes structured one-off quote method metadata via describe', function () {
    $description = Cord::oneOffQuote()->describe();

    expect($description['resource'])->toBe('oneOffQuote')
        ->and($description['actions'])->toBe(['create'])
        ->and(collect($description['methods'])->firstWhere('name', 'transportMode'))->toMatchArray([
            'name' => 'transportMode',
            'required_for' => ['create'],
        ])
        ->and(collect($description['methods'])->firstWhere('name', 'addChargeLine'))->toMatchArray([
            'name' => 'addChargeLine',
            'required_for' => [],
        ])
        ->and(collect($description['methods'])->firstWhere('name', 'addAttachedDocument'))->toMatchArray([
            'name' => 'addAttachedDocument',
            'required_for' => [],
        ]);
});

it('returns deterministic validation errors for one-off quote create required fields', function () {
    $errors = null;

    try {
        Cord::withCompany('CPH')
            ->oneOffQuote()
            ->create()
            ->toPayload();
    } catch (ValidationException $e) {
        $errors = $e->errors();
    }

    expect($errors)->toMatchArray([
        'branch' => ['The branch field is required.'],
        'department' => ['The department field is required.'],
        'transportMode' => ['The transportMode field is required.'],
        'portOfOrigin' => ['The portOfOrigin field is required.'],
        'portOfDestination' => ['The portOfDestination field is required.'],
    ]);
});

it('returns deterministic validation errors for one-off quote nested fields', function () {
    $errors = null;

    try {
        Cord::withCompany('CPH')
            ->oneOffQuote()
            ->create()
            ->branch('A01')
            ->department('FES')
            ->transportMode('SEA')
            ->portOfOrigin('AUSYD')
            ->portOfDestination('NZAKL')
            ->clientAddress(fn ($a) => $a
                ->addressLine1('3 TENTH AVENUE')
                ->country('AU'))
            ->addChargeLine(fn ($c) => $c
                ->chargeCode('FRT'))
            ->toPayload();
    } catch (ValidationException $e) {
        $errors = $e->errors();
    }

    expect($errors)->toMatchArray([
        'addresses.client.city' => ['The city field is required.'],
        'chargeLines.0.description' => ['The description field is required.'],
    ]);
});

it('returns deterministic validation errors for one-off quote attached documents', function () {
    $errors = null;

    try {
        Cord::withCompany('CPH')
            ->oneOffQuote()
            ->create()
            ->branch('A01')
            ->department('FES')
            ->transportMode('SEA')
            ->portOfOrigin('AUSYD')
            ->portOfDestination('NZAKL')
            ->addAttachedDocument(fn ($d) => $d
                ->fileName('quote.pdf'))
            ->toPayload();
    } catch (ValidationException $e) {
        $errors = $e->errors();
    }

    expect($errors)->toMatchArray([
        'attachedDocuments.0.imageData' => ['The imageData field is required.'],
        'attachedDocuments.0.typeCode' => ['The typeCode field is required.'],
    ]);
});

it('requires company context for one-off quote create', function () {
    $errors = null;

    try {
        Cord::oneOffQuote()
            ->create()
            ->branch('A01')
            ->department('FES')
            ->transportMode('SEA')
            ->portOfOrigin('AUSYD')
            ->portOfDestination('NZAKL')
            ->toPayload();
    } catch (ValidationException $e) {
        $errors = $e->errors();
    }

    expect($errors)->toMatchArray([
        'company' => ['The company field is required.'],
    ]);
});

it('does not support one-off quote update in v1', function () {
    expect(fn () => Cord::oneOffQuote('00001063')->update())
        ->toThrow(Exception::class, 'OneOffQuote update() is not implemented yet.');
});
