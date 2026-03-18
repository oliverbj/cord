<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Oliverbj\Cord\Attributes\OperationField;
use Oliverbj\Cord\Attributes\StructuredField;
use Oliverbj\Cord\Builders\OneOffQuoteAddressBuilder;
use Oliverbj\Cord\Builders\OneOffQuoteAttachedDocumentBuilder;
use Oliverbj\Cord\Builders\OneOffQuoteChargeLineBuilder;
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

it('includes derived sender and default recipient ids in universal payloads when company context is available', function () {
    $xml = Cord::shipment('SMIA12345678')
        ->withCompany('CPH')
        ->inspect();

    expect($xml)
        ->toContain('<SenderID>DEMO1TRNCPH</SenderID>')
        ->toContain('<RecipientID>Cord</RecipientID>')
        ->toContain('<ShipmentRequest>');
});

it('includes explicit sender and recipient ids in universal payloads', function () {
    $xml = Cord::shipment('SMIA12345678')
        ->withSenderId('PartnerA')
        ->withRecipientId('PartnerB')
        ->inspect();

    expect($xml)
        ->toContain('<SenderID>PartnerA</SenderID>')
        ->toContain('<RecipientID>PartnerB</RecipientID>');
});

it('inspects a raw xml payload without sending it', function () {
    Http::fake();

    $payload = <<<'XML'
<Native xmlns="http://www.cargowise.com/Schemas/Native">
    <Body />
</Native>
XML;

    $xml = Cord::withConfig('archive')
        ->rawXml($payload)
        ->inspect();

    Http::assertNothingSent();

    expect($xml)->toBe($payload);
});

it('returns the full parsed eadapter envelope for raw xml requests', function () {
    Http::fake([
        'https://demo1prdservices.example.invalid/eadapter' => Http::response(<<<'XML'
<Response>
    <Status>ERR</Status>
    <ProcessingLog>Duplicate LSC already exists</ProcessingLog>
    <Data>
        <Native>
            <Body>
                <Organization>
                    <OrgHeader>
                        <Code>SAGFURHEL</Code>
                    </OrgHeader>
                </Organization>
            </Body>
        </Native>
    </Data>
</Response>
XML, 200, ['Content-Type' => 'application/xml']),
    ]);

    $payload = <<<'XML'
<Native xmlns="http://www.cargowise.com/Schemas/Native">
    <Body>
        <Organization />
    </Body>
</Native>
XML;

    $response = Cord::withConfig('archive')
        ->rawXml($payload)
        ->run();

    Http::assertSent(function ($request) use ($payload) {
        return $request->url() === 'https://demo1prdservices.example.invalid/eadapter'
            && $request->method() === 'POST'
            && $request->body() === $payload
            && $request->hasHeader('Accept', 'application/xml')
            && $request->hasHeader('Content-Type', 'application/xml');
    });

    expect($response)->toMatchArray([
        'Status' => 'ERR',
        'ProcessingLog' => 'Duplicate LSC already exists',
        'Data' => [
            'Native' => [
                'Body' => [
                    'Organization' => [
                        'OrgHeader' => [
                            'Code' => 'SAGFURHEL',
                        ],
                    ],
                ],
            ],
        ],
    ]);
});

it('returns the full xml response envelope for raw xml requests when toXml is enabled', function () {
    Http::fake([
        '*' => Http::response(<<<'XML'
<Response>
    <Status>OK</Status>
    <ProcessingLog>1 updates</ProcessingLog>
    <Data>
        <Native>
            <Body>
                <Organization>
                    <OrgHeader>
                        <Code>SAGFURHEL</Code>
                    </OrgHeader>
                </Organization>
            </Body>
        </Native>
    </Data>
</Response>
XML, 200, ['Content-Type' => 'application/xml']),
    ]);

    $response = Cord::rawXml('<Native><Body /></Native>')
        ->toXml()
        ->run();

    expect($response->getName())->toBe('Response')
        ->and((string) $response->Status)->toBe('OK')
        ->and((string) $response->ProcessingLog)->toBe('1 updates')
        ->and((string) $response->Data->Native->Body->Organization->OrgHeader->Code)->toBe('SAGFURHEL');
});

it('returns the normalized response as json when toJson is enabled', function () {
    Http::fake([
        '*' => Http::response(<<<'XML'
<Response>
    <Status>OK</Status>
    <ProcessingLog>1 updates</ProcessingLog>
    <Data>
        <Native>
            <Body>
                <Organization>
                    <OrgHeader>
                        <Code>SAGFURHEL</Code>
                        <FullName>Sagfur Hel</FullName>
                    </OrgHeader>
                </Organization>
            </Body>
        </Native>
    </Data>
</Response>
XML, 200, ['Content-Type' => 'application/xml']),
    ]);

    $response = Cord::organization()
        ->criteriaGroup([
            [
                'Entity' => 'OrgHeader',
                'FieldName' => 'Code',
                'Value' => 'SAGFURHEL',
            ],
        ], type: 'Exact')
        ->toJson()
        ->run();

    expect($response)->toBeString()
        ->and(json_decode($response, true, 512, JSON_THROW_ON_ERROR))->toMatchArray([
            'Code' => 'SAGFURHEL',
            'FullName' => 'Sagfur Hel',
        ]);
});

it('returns the full json response envelope for raw xml requests when toJson is enabled', function () {
    Http::fake([
        '*' => Http::response(<<<'XML'
<Response>
    <Status>OK</Status>
    <ProcessingLog>1 updates</ProcessingLog>
    <Data>
        <Native>
            <Body>
                <Organization>
                    <OrgHeader>
                        <Code>SAGFURHEL</Code>
                    </OrgHeader>
                </Organization>
            </Body>
        </Native>
    </Data>
</Response>
XML, 200, ['Content-Type' => 'application/xml']),
    ]);

    $response = Cord::rawXml('<Native><Body /></Native>')
        ->toJson()
        ->run();

    expect($response)->toBeString()
        ->and(json_decode($response, true, 512, JSON_THROW_ON_ERROR))->toMatchArray([
            'Status' => 'OK',
            'ProcessingLog' => '1 updates',
            'Data' => [
                'Native' => [
                    'Body' => [
                        'Organization' => [
                            'OrgHeader' => [
                                'Code' => 'SAGFURHEL',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
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
        ->update()
        ->addAddress(fn ($a) => $a
            ->code('MAIN STREET NO. 1')
            ->addressOne('Main Street')
            ->addressTwo('Number One')
            ->country('US')
            ->city('Anytown')
            ->state('NY')
            ->postcode('12345')
            ->relatedPort('USNYC')
            ->capability('OFC', isMainAddress: false)
        )
        ->inspect();

    expect($xml)
        ->toContain('<OrgAddressCapability Action="INSERT">')
        ->toContain('<AddressType>OFC</AddressType>')
        ->toContain('<IsMainAddress>false</IsMainAddress>');
});

it('allows adding a contact without documents to deliver', function () {
    $xml = Cord::withCompany('CPH')
        ->organization('SAGFURHEL')
        ->update()
        ->addContact(fn ($c) => $c
            ->name('Jane Doe')
            ->email('jane@example.com')
        )
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

it('describes staff operations from the registry', function () {
    $description = Cord::staff()->describe();

    expect($description['resource'])->toBe('staff')
        ->and($description['operations'])->toBe([
            ['id' => 'staff.create', 'action' => 'create'],
            ['id' => 'staff.update', 'action' => 'update'],
        ]);
});

it('publishes representative operation schemas', function () {
    $oneOffQuoteGet = Cord::schema('one_off_quote.get');
    $oneOffQuote = Cord::schema('one_off_quote.create');
    $staffCreate = Cord::schema('staff.create');
    $staffUpdate = Cord::schema('staff.update');
    $organizationQuery = Cord::schema('organization.query');
    $shipmentGet = Cord::schema('shipment.get');

    expect($oneOffQuoteGet)->toMatchArray([
        'type' => 'object',
        'required' => ['company', 'key'],
        'x-cord' => [
            'operation_id' => 'one_off_quote.get',
            'resource' => 'one_off_quote',
            'action' => 'get',
        ],
    ])->and($oneOffQuoteGet['properties'])->toHaveKeys(['enterprise', 'server', 'sender_id', 'recipient_id'])
        ->and($oneOffQuote)->toMatchArray([
        'type' => 'object',
        'required' => ['company', 'branch', 'department', 'transport_mode', 'port_of_origin', 'port_of_destination'],
        'x-cord' => [
            'operation_id' => 'one_off_quote.create',
            'resource' => 'one_off_quote',
            'action' => 'create',
        ],
    ])->and($oneOffQuote['properties']['transport_mode'])->toMatchArray([
        'type' => 'string',
        'enum' => ['SEA', 'AIR', 'ROA'],
    ])->and($oneOffQuote['properties']['client_address']['type'])->toBe('object')
        ->and($oneOffQuote['properties']['charge_lines']['type'])->toBe('array')
        ->and($oneOffQuote['properties']['attached_documents']['type'])->toBe('array')
        ->and($oneOffQuote['properties'])->toHaveKeys(['sender_id', 'recipient_id'])
        ->and($staffCreate['required'])->toBe(['company', 'code', 'login_name', 'password', 'full_name', 'branch', 'department', 'country'])
        ->and($staffCreate['properties'])->not->toHaveKeys(['sender_id', 'recipient_id'])
        ->and($staffUpdate['required'])->toBe(['company', 'code'])
        ->and($organizationQuery['properties']['criteria_groups']['type'])->toBe('array')
        ->and($shipmentGet['required'])->toBe(['key'])
        ->and($shipmentGet['properties'])->toHaveKeys(['sender_id', 'recipient_id']);
});

it('describes published resources from an unscoped builder', function () {
    $description = Cord::describe();

    expect($description['resources'])->toHaveKeys([
        'booking',
        'company',
        'custom',
        'one_off_quote',
        'organization',
        'receivable',
        'shipment',
        'staff',
    ]);
});

it('returns the active schema when a builder is fully scoped', function () {
    $description = Cord::oneOffQuote()->create()->describe();

    expect($description)->toBe(Cord::schema('one_off_quote.create'));
});

it('returns the active schema for a scoped one-off quote query', function () {
    $description = Cord::oneOffQuote('QCPH00001004')->get()->describe();

    expect($description)->toBe(Cord::schema('one_off_quote.get'));
});

it('builds the same one-off quote query xml from structured input', function () {
    $fluentXml = Cord::withCompany('CPH')
        ->oneOffQuote('QCPH00001004')
        ->get()
        ->inspect();

    $structuredXml = Cord::fromStructured('one_off_quote.get', [
        'company' => 'CPH',
        'key' => 'QCPH00001004',
    ])->inspect();

    expect($structuredXml)->toBe($fluentXml)
        ->and($structuredXml)->toContain('<Type>OneOffQuote</Type>')
        ->toContain('<Key>QCPH00001004</Key>')
        ->toContain('<Company><Code>CPH</Code></Company>')
        ->toContain('<EnterpriseID>DEMO1</EnterpriseID>')
        ->toContain('<ServerID>TRN</ServerID>')
        ->toContain('<RecipientRoleCollection>')
        ->toContain('<Code>ORP</Code>');
});

it('builds the same one-off quote xml from structured input', function () {
    $fluentXml = Cord::withCompany('CPH')
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
        ->addAttachedDocument(fn ($d) => $d
            ->fileName('quote.pdf')
            ->imageData(base64_encode('quote-data'))
            ->type('QUO')
            ->isPublished(true))
        ->inspect();

    $structuredXml = Cord::fromStructured('one_off_quote.create', [
        'company' => 'CPH',
        'branch' => 'A01',
        'department' => 'FES',
        'transport_mode' => 'SEA',
        'port_of_origin' => 'AUSYD',
        'port_of_destination' => 'NZAKL',
        'service_level' => 'STD',
        'incoterm' => 'DAP',
        'additional_terms' => 'Export Only',
        'is_domestic_freight' => false,
        'total_weight' => ['value' => 5000, 'unit_code' => 'KG'],
        'total_volume' => ['value' => 19.2, 'unit_code' => 'M3'],
        'goods_value' => ['amount' => 15000, 'currency_code' => 'AUD'],
        'client_address' => [
            'address_line_1' => '3 TENTH AVENUE',
            'city' => 'OYSTER BAY',
            'country' => 'AU',
            'organization_code' => 'AU10IMSYD',
            'phone' => '+61288361212',
        ],
        'pickup_address' => [
            'address_line_1' => '3 TENTH AVENUE',
            'city' => 'OYSTER BAY',
            'country' => 'AU',
        ],
        'delivery_address' => [
            'address_line_1' => '10 TEST ROAD',
            'city' => 'AUCKLAND',
            'country' => 'NZ',
        ],
        'charge_lines' => [
            [
                'charge_code' => 'FRT',
                'description' => 'International Freight',
                'cost_amount' => ['value' => '500.0000', 'currency_code' => 'AUD'],
                'sell_amount' => ['value' => '1500.0000', 'currency_code' => 'AUD'],
            ],
        ],
        'attached_documents' => [
            [
                'file_name' => 'quote.pdf',
                'image_data' => base64_encode('quote-data'),
                'type' => 'QUO',
                'is_published' => true,
            ],
        ],
    ])->inspect();

    expect($structuredXml)->toBe($fluentXml);
});

it('builds the same staff xml from structured create and update input', function () {
    $createXml = Cord::fromStructured('staff.create', [
        'company' => 'CPH',
        'code' => 'BVO',
        'login_name' => 'user.test',
        'password' => '1234',
        'full_name' => 'User Test',
        'email' => 'user.test@test.com',
        'branch' => 'TLS',
        'department' => 'FES',
        'phone' => '+111',
        'is_active' => true,
        'country' => 'FR',
        'groups' => ['ORGALL', 'OPSALL'],
    ])->inspect();

    expect($createXml)->toBe(
        Cord::withCompany('CPH')
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
            ->inspect()
    );

    $updateXml = Cord::fromStructured('staff.update', [
        'company' => 'CPH',
        'code' => 'BVO',
        'full_name' => 'Updated User',
        'email' => 'updated@example.com',
        'branch' => 'CPH',
        'department' => 'OPS',
        'country' => 'DK',
        'groups_to_add' => ['NEWOPS'],
        'groups_to_remove' => ['OLDOPS'],
    ])->inspect();

    expect($updateXml)->toBe(
        Cord::withCompany('CPH')
            ->staff('BVO')
            ->update()
            ->fullName('Updated User')
            ->email('updated@example.com')
            ->branch('CPH')
            ->department('OPS')
            ->country('DK')
            ->addGroup('NEWOPS')
            ->removeGroup('OLDOPS')
            ->inspect()
    );
});

it('builds the same organization query xml from structured input', function () {
    $structuredXml = Cord::fromStructured('organization.query', [
        'criteria_groups' => [
            [
                'type' => 'Partial',
                'criteria' => [
                    [
                        'entity' => 'OrgHeader',
                        'field_name' => 'Code',
                        'value' => 'US%',
                    ],
                ],
            ],
            [
                'type' => 'Partial',
                'criteria' => [
                    [
                        'entity' => 'OrgHeader',
                        'field_name' => 'IsBroker',
                        'value' => 'True',
                    ],
                ],
            ],
        ],
    ])->inspect();

    $fluentXml = Cord::organization()
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

    expect($structuredXml)->toBe($fluentXml);
});

it('builds the same organization address xml from structured input', function () {
    $structuredXml = Cord::fromStructured('organization.address.add', [
        'company' => 'CPH',
        'code' => 'SAGFURHEL',
        'address' => [
            'code' => 'MAIN STREET NO. 1',
            'address_one' => 'Main Street',
            'address_two' => 'Number One',
            'country' => 'US',
            'city' => 'Anytown',
            'state' => 'NY',
            'postcode' => '12345',
            'related_port' => 'USNYC',
            'capabilities' => [
                [
                    'address_type' => 'OFC',
                    'is_main_address' => false,
                ],
            ],
        ],
    ])->inspect();

    $fluentXml = Cord::withCompany('CPH')
        ->organization('SAGFURHEL')
        ->update()
        ->addAddress(fn ($a) => $a
            ->code('MAIN STREET NO. 1')
            ->addressOne('Main Street')
            ->addressTwo('Number One')
            ->country('US')
            ->city('Anytown')
            ->state('NY')
            ->postcode('12345')
            ->relatedPort('USNYC')
            ->capability('OFC', isMainAddress: false)
        )
        ->inspect();

    expect($structuredXml)->toBe($fluentXml);
});

it('builds the same organization contact xml from structured input', function () {
    $structuredXml = Cord::fromStructured('organization.contact.add', [
        'company' => 'CPH',
        'code' => 'SAGFURHEL',
        'contact' => [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ],
    ])->inspect();

    $fluentXml = Cord::withCompany('CPH')
        ->organization('SAGFURHEL')
        ->update()
        ->addContact(fn ($c) => $c
            ->name('Jane Doe')
            ->email('jane@example.com')
        )
        ->inspect();

    expect($structuredXml)->toBe($fluentXml);
});

it('builds the same organization edi xml from structured input', function () {
    $structuredXml = Cord::fromStructured('organization.edi_communication.add', [
        'company' => 'CPH',
        'code' => 'SAGFURHEL',
        'edi_communication' => [
            'module' => 'IMP',
            'purpose' => 'CUS',
            'direction' => 'OUT',
            'transport' => 'EML',
            'destination' => 'ops@example.com',
            'format' => 'XML',
        ],
    ])->inspect();

    $fluentXml = Cord::withCompany('CPH')
        ->organization('SAGFURHEL')
        ->update()
        ->addEDICommunication(fn ($e) => $e
            ->module('IMP')
            ->purpose('CUS')
            ->direction('OUT')
            ->transport('EML')
            ->destination('ops@example.com')
            ->format('XML')
        )
        ->inspect();

    expect($structuredXml)->toBe($fluentXml);
});

it('builds the same shipment helper xml from structured input', function () {
    $eventXml = Cord::fromStructured('shipment.event.add', [
        'key' => 'SJFK21060014',
        'event' => [
            'date' => '2026-01-01T00:00:00+00:00',
            'type' => 'DIM',
            'reference' => 'My Reference',
            'is_estimate' => true,
        ],
    ])->inspect();

    expect($eventXml)->toBe(
        Cord::shipment('SJFK21060014')
            ->addEvent(
                date: '2026-01-01T00:00:00+00:00',
                type: 'DIM',
                reference: 'My Reference',
                isEstimate: true,
            )
            ->inspect()
    );

    $structuredDocumentXml = Cord::fromStructured('shipment.document.add', [
        'key' => 'SJFK21060014',
        'document' => [
            'file_contents' => base64_encode('doc'),
            'name' => 'myfile.pdf',
            'type' => 'MSC',
            'description' => 'Optional description',
            'is_published' => true,
        ],
    ])->inspect();

    $fluentDocumentXml = Cord::shipment('SJFK21060014')
        ->addDocument(
            file_contents: base64_encode('doc'),
            name: 'myfile.pdf',
            type: 'MSC',
            description: 'Optional description',
            isPublished: true,
        )
        ->inspect();

    $normalizeEventTime = fn (string $xml) => preg_replace('/<EventTime>.*?<\/EventTime>/', '<EventTime>normalized</EventTime>', $xml);

    expect($normalizeEventTime($structuredDocumentXml))->toBe($normalizeEventTime($fluentDocumentXml));
});

it('validates structured payload enums, unknown fields, and nested dotted paths', function () {
    expect(fn () => Cord::fromStructured('one_off_quote.create', [
        'company' => 'CPH',
        'branch' => 'A01',
        'department' => 'FES',
        'transport_mode' => 'TRUCK',
        'port_of_origin' => 'AUSYD',
        'port_of_destination' => 'NZAKL',
    ]))->toThrow(ValidationException::class);

    expect(function () {
        Cord::fromStructured('one_off_quote.create', [
            'company' => 'CPH',
            'branch' => 'A01',
            'department' => 'FES',
            'transport_mode' => 'SEA',
            'port_of_origin' => 'AUSYD',
            'port_of_destination' => 'NZAKL',
            'unknown_field' => 'x',
        ]);
    })->toThrow(ValidationException::class);

    try {
        Cord::fromStructured('one_off_quote.create', [
            'company' => 'CPH',
            'branch' => 'A01',
            'department' => 'FES',
            'transport_mode' => 'SEA',
            'port_of_origin' => 'AUSYD',
            'port_of_destination' => 'NZAKL',
            'client_address' => [
                'address_line_1' => '3 TENTH AVENUE',
                'country' => 'AU',
            ],
            'charge_lines' => [
                [
                    'charge_code' => 'FRT',
                ],
            ],
        ]);

        $errors = [];
    } catch (ValidationException $e) {
        $errors = $e->errors();
    }

    expect($errors)->toMatchArray([
        'client_address.city' => ['The field is required.'],
        'charge_lines.0.description' => ['The field is required.'],
    ]);
});

it('lets preconfigured builder state win over duplicate structured fields', function () {
    $xml = Cord::shipment('SMIA12345678')
        ->fromStructured('shipment.documents.get', [
            'key' => 'SHOULD-NOT-WIN',
            'filters' => [
                ['type' => 'DocumentType', 'value' => 'ARN'],
            ],
        ])
        ->inspect();

    expect($xml)
        ->toContain('<Key>SMIA12345678</Key>')
        ->not->toContain('SHOULD-NOT-WIN')
        ->toContain('<Type>DocumentType</Type>');
});

it('keeps structured metadata coverage in sync with published fluent methods', function () {
    $cordReflection = new ReflectionClass(Oliverbj\Cord\Cord::class);
    $ignoredCordMethods = [
        '__construct',
        'withCompany',
        'withServer',
        'withEnterprise',
        'withConfig',
        'withOwnerCode',
        'withSenderId',
        'withRecipientId',
        'withRecepientId',
        'withCodeMapping',
        'rawXml',
        'booking',
        'receiveable',
        'receivable',
        'shipment',
        'oneOffQuote',
        'organization',
        'staff',
        'get',
        'create',
        'update',
        'delete',
        'upsert',
        'withPayload',
        'toPayload',
        'schema',
        'fromStructured',
        'describe',
        'run',
        'inspect',
        'toJson',
        'toXml',
        'resolveSenderId',
        'resolveRecipientId',
        'resolveEnterpriseId',
        'resolveServerId',
        'nativeHeader',
        'company',
        'custom',
        'withDocuments',
        'activeOneOffQuoteIntent',
    ];

    foreach ($cordReflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->class !== Oliverbj\Cord\Cord::class || in_array($method->getName(), $ignoredCordMethods, true)) {
            continue;
        }

        expect($method->getAttributes(OperationField::class))
            ->not->toBeEmpty('Missing OperationField coverage for '.$method->getName());
    }

    foreach ([
        OneOffQuoteAddressBuilder::class,
        OneOffQuoteChargeLineBuilder::class,
        OneOffQuoteAttachedDocumentBuilder::class,
    ] as $builderClass) {
        $reflection = new ReflectionClass($builderClass);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $builderClass || in_array($method->getName(), ['withPayload', 'toArray'], true)) {
                continue;
            }

            expect($method->getAttributes(StructuredField::class))
                ->not->toBeEmpty('Missing StructuredField coverage for '.$builderClass.'::'.$method->getName());
        }
    }
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

it('describes one-off quote operations from the registry', function () {
    $description = Cord::oneOffQuote()->describe();

    expect($description['resource'])->toBe('one_off_quote')
        ->and($description['operations'])->toBe([
            ['id' => 'one_off_quote.create', 'action' => 'create'],
            ['id' => 'one_off_quote.get', 'action' => 'get'],
        ]);
});

it('builds a one-off quote query payload with company context', function () {
    $xml = Cord::withCompany('CPH')
        ->oneOffQuote('QCPH00001004')
        ->get()
        ->inspect();

    expect($xml)
        ->toContain('<UniversalShipmentRequest>')
        ->toContain('<Type>OneOffQuote</Type>')
        ->toContain('<Key>QCPH00001004</Key>')
        ->toContain('<Company><Code>CPH</Code></Company>')
        ->toContain('<EnterpriseID>DEMO1</EnterpriseID>')
        ->toContain('<ServerID>TRN</ServerID>')
        ->toContain('<RecipientRoleCollection>')
        ->toContain('<Code>ORP</Code>');
});

it('returns one-off quote query data from a universal shipment response', function () {
    Http::fake([
        '*' => Http::response(<<<'XML'
<UniversalResponse version="1.1" xmlns="http://www.cargowise.com/Schemas/Universal/2011/11">
    <Status>PRS</Status>
    <Data>
        <UniversalShipment version="1.1" xmlns="http://www.cargowise.com/Schemas/Universal/2011/11">
            <Shipment>
                <DataContext>
                    <DataSourceCollection>
                        <DataSource>
                            <Type>OneOffQuote</Type>
                            <Key>QCPH00001004</Key>
                        </DataSource>
                    </DataSourceCollection>
                    <Company>
                        <Code>CPH</Code>
                    </Company>
                    <EnterpriseID>NT1</EnterpriseID>
                    <ServerID>TRN</ServerID>
                </DataContext>
                <ActualChargeable>71.300</ActualChargeable>
                <ContainerMode>
                    <Code>LSE</Code>
                    <Description>Loose</Description>
                </ContainerMode>
            </Shipment>
        </UniversalShipment>
    </Data>
</UniversalResponse>
XML, 200, ['Content-Type' => 'application/xml']),
    ]);

    $response = Cord::withCompany('CPH')
        ->oneOffQuote('QCPH00001004')
        ->get()
        ->run();

    expect($response)->toMatchArray([
        'UniversalShipment' => [
            '@attributes' => [
                'version' => '1.1',
            ],
            'Shipment' => [
                'DataContext' => [
                    'DataSourceCollection' => [
                        'DataSource' => [
                            'Type' => 'OneOffQuote',
                            'Key' => 'QCPH00001004',
                        ],
                    ],
                    'Company' => [
                        'Code' => 'CPH',
                    ],
                    'EnterpriseID' => 'NT1',
                    'ServerID' => 'TRN',
                ],
                'ActualChargeable' => '71.300',
                'ContainerMode' => [
                    'Code' => 'LSE',
                    'Description' => 'Loose',
                ],
            ],
        ],
    ]);
});

it('requires company context for one-off quote query', function () {
    expect(fn () => Cord::oneOffQuote('QCPH00001004')
        ->get()
        ->inspect())
        ->toThrow(Exception::class, 'Company code must be provided for one-off quote query requests.');
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

it('builds an organization create payload with INSERT action', function () {
    $xml = Cord::withCompany('CPH')
        ->organization('NEWORG')
        ->create()
        ->fullName('New Organization Ltd')
        ->isForwarder(true)
        ->isConsignee(true)
        ->isConsignor(false)
        ->isAirLine(false)
        ->closestPort('AUSYD')
        ->addAddress(fn ($a) => $a
            ->code('MAIN ST')
            ->addressOne('1 Main Street')
            ->country('AU')
            ->city('Sydney')
            ->capability('OFC', isMainAddress: true)
        )
        ->addContact(fn ($c) => $c
            ->name('Operations')
            ->email('ops@example.com')
        )
        ->inspect();

    expect($xml)
        ->toContain('<OrgHeader Action="INSERT">')
        ->toContain('<Code>NEWORG</Code>')
        ->toContain('<FullName>New Organization Ltd</FullName>')
        ->toContain('<IsForwarder>true</IsForwarder>')
        ->toContain('<IsConsignee>true</IsConsignee>')
        ->toContain('<IsConsignor>false</IsConsignor>')
        ->toContain('<IsAirLine>false</IsAirLine>')
        ->toContain('<OrgAddressCollection>')
        ->toContain('<OrgAddress Action="INSERT">')
        ->toContain('<OrgContactCollection>')
        ->toContain('<OrgContact Action="INSERT">')
        ->toContain('<ContactName>Operations</ContactName>')
        ->toContain('<ClosestPort TableName="RefUNLOCO">')
        ->toContain('<Code>AUSYD</Code>');
});

it('builds the same organization create xml from structured input', function () {
    $structuredXml = Cord::fromStructured('organization.create', [
        'company' => 'CPH',
        'code' => 'NEWORG',
        'full_name' => 'New Organization Ltd',
        'is_forwarder' => true,
        'is_consignee' => true,
        'closest_port' => 'AUSYD',
        'addresses' => [
            [
                'code' => 'MAIN ST',
                'address_one' => '1 Main Street',
                'country' => 'AU',
                'city' => 'Sydney',
                'capabilities' => [
                    ['address_type' => 'OFC', 'is_main_address' => true],
                ],
            ],
        ],
        'contacts' => [
            [
                'name' => 'Operations',
                'email' => 'ops@example.com',
            ],
        ],
    ])->inspect();

    $fluentXml = Cord::withCompany('CPH')
        ->organization('NEWORG')
        ->create()
        ->fullName('New Organization Ltd')
        ->isForwarder(true)
        ->isConsignee(true)
        ->closestPort('AUSYD')
        ->addAddress(fn ($a) => $a
            ->code('MAIN ST')
            ->addressOne('1 Main Street')
            ->country('AU')
            ->city('Sydney')
            ->capability('OFC', isMainAddress: true)
        )
        ->addContact(fn ($c) => $c
            ->name('Operations')
            ->email('ops@example.com')
        )
        ->inspect();

    expect($structuredXml)->toBe($fluentXml);
});

it('validates fullName is required for organization create', function () {
    $errors = null;

    try {
        Cord::withCompany('CPH')
            ->organization('NEWORG')
            ->create()
            ->inspect();
    } catch (ValidationException $e) {
        $errors = $e->errors();
    }

    expect($errors)->toMatchArray([
        'full_name' => ['The full_name field is required.'],
    ]);
});

it('requires a code when calling organization create', function () {
    expect(fn () => Cord::organization()->create())
        ->toThrow(Exception::class, 'organization() requires a code for create().');
});
