<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Oliverbj\Cord\Exceptions\EadapterRejectedException;
use Oliverbj\Cord\Facades\Cord;

function bookingStructuredPayload(): array
{
    return [
        'company' => 'NYC',
        'branch' => 'ATL',
        'department' => 'AIR',
        'transport_mode' => 'AIR',
        'packing_mode' => 'LSE',
        'service_level' => 'STD',
        'incoterm' => 'DDP',
        'additional_terms' => 'Savannah',
        'goods_description' => 'Quarrying and crushing equipment',
        'outer_packs' => ['quantity' => 1, 'pack_type' => 'PLT'],
        'total_weight' => ['value' => 415, 'unit_code' => 'KG'],
        'total_volume' => ['value' => 2.2, 'unit_code' => 'M3'],
        'actual_chargeable' => 415,
        'goods_value' => ['amount' => 3947.38, 'currency_code' => 'USD'],
        'port_of_loading' => 'GBBEL',
        'port_of_discharge' => 'USDFW',
        'port_of_origin' => 'GBBEL',
        'port_of_destination' => 'USCA4',
        'house_bill' => 'SAE132767',
        'carrier_contract_number' => 'CC-1',
        'departure_date' => ['date' => '2026-10-25', 'is_estimate' => true],
        'arrival_date' => ['date' => '2026-10-26', 'is_estimate' => false],
        'pickup_required_by' => '2026-10-20 09:00',
        'pickup_drop_mode' => 'CSA',
        'controlling_agent' => 'TCBNIBFS',
        'parties' => [
            ['address_type' => 'ConsignorDocumentaryAddress', 'organization_code' => 'CDEGLOBFS'],
            [
                'address_type' => 'ConsigneePickupDeliveryAddress',
                'company_name' => 'SRM MATERIALS LLC',
                'address_line_1' => '4199 COUNTY ROAD',
                'city' => 'CHANNING',
                'state' => 'TX',
                'postcode' => '79018',
                'country' => 'US',
                'email' => 'IRODRIGUEZ@SRMMATERIALS.COM',
                'phone' => '+1 806-683-1249',
            ],
        ],
        'pack_lines' => [[
            'pack_type' => 'PLT',
            'quantity' => 1,
            'weight' => ['value' => 415, 'unit_code' => 'KG'],
            'volume' => ['value' => 2.2, 'unit_code' => 'M3'],
            'length' => ['value' => 200, 'unit_code' => 'CM'],
            'width' => ['value' => 100, 'unit_code' => 'CM'],
            'height' => ['value' => 110, 'unit_code' => 'CM'],
            'description' => '1.8MTR M/C CROSS MEMBER',
            'hs_code' => '847490',
            'marks_and_numbers' => 'N/M',
        ]],
        'containers' => [['type' => '40HC', 'count' => 1, 'iso_code' => '45G1']],
        'order_numbers' => ['UKPO62027'],
        'additional_references' => [
            ['type' => 'CLR', 'number' => 'ST-1356'],
            ['type' => 'BKG', 'number' => '46017692', 'country_of_issue' => 'GB'],
        ],
    ];
}

it('builds a booking create payload from fluent and structured input', function () {
    $structured = Cord::fromStructured('booking.create', bookingStructuredPayload())->inspect();

    $fluent = Cord::withCompany('NYC')
        ->booking()
        ->create()
        ->branch('ATL')
        ->department('AIR')
        ->transportMode('AIR')
        ->packingMode('LSE')
        ->serviceLevel('STD')
        ->incoterm('DDP')
        ->additionalTerms('Savannah')
        ->goodsDescription('Quarrying and crushing equipment')
        ->outerPacks(1, 'PLT')
        ->totalWeight(415, 'KG')
        ->totalVolume(2.2, 'M3')
        ->actualChargeable(415)
        ->goodsValue(3947.38, 'USD')
        ->portOfLoading('GBBEL')
        ->portOfDischarge('USDFW')
        ->portOfOrigin('GBBEL')
        ->portOfDestination('USCA4')
        ->houseBill('SAE132767')
        ->carrierContractNumber('CC-1')
        ->departureDate('2026-10-25')
        ->arrivalDate('2026-10-26', false)
        ->pickupRequiredBy('2026-10-20 09:00')
        ->pickupDropMode('CSA')
        ->controllingAgent('TCBNIBFS')
        ->addParty(fn ($p) => $p->addressType('ConsignorDocumentaryAddress')->organizationCode('CDEGLOBFS'))
        ->addParty(fn ($p) => $p
            ->addressType('ConsigneePickupDeliveryAddress')
            ->companyName('SRM MATERIALS LLC')
            ->addressLine1('4199 COUNTY ROAD')
            ->city('CHANNING')
            ->state('TX')
            ->postcode('79018')
            ->country('US')
            ->email('IRODRIGUEZ@SRMMATERIALS.COM')
            ->phone('+1 806-683-1249'))
        ->addPackLine(fn ($l) => $l
            ->packageType('PLT')
            ->quantity(1)
            ->weight(415, 'KG')
            ->volume(2.2, 'M3')
            ->length(200, 'CM')
            ->width(100, 'CM')
            ->height(110, 'CM')
            ->description('1.8MTR M/C CROSS MEMBER')
            ->hsCode('847490')
            ->marksAndNumbers('N/M'))
        ->addContainer(fn ($c) => $c->type('40HC')->count(1)->isoCode('45G1'))
        ->addOrderNumber('UKPO62027')
        ->addAdditionalReference('CLR', 'ST-1356')
        ->addAdditionalReference('BKG', '46017692', 'GB')
        ->inspect();

    expect($structured)->toBe($fluent)
        ->and($fluent)
        ->toContain('<UniversalShipment><Shipment><DataContext>')
        ->toContain('<DataTarget><Type>ForwardingBooking</Type></DataTarget>')
        ->toContain('<Company><Code>NYC</Code></Company>')
        ->toContain('<EnterpriseID>DEMO1</EnterpriseID>')
        ->toContain('<Branch><Code>ATL</Code></Branch>')
        ->toContain('<JobCosting><Branch><Code>ATL</Code></Branch><Department><Code>AIR</Code></Department></JobCosting>')
        ->toContain('<ContainerMode><Code>LSE</Code></ContainerMode>')
        ->toContain('<ShipmentIncoTerm><Code>DDP</Code></ShipmentIncoTerm>')
        ->toContain('<AdditionalTerms>Savannah</AdditionalTerms>')
        ->toContain('<OuterPacks>1</OuterPacks><OuterPacksPackageType><Code>PLT</Code></OuterPacksPackageType>')
        ->toContain('<GoodsValue>3947.38</GoodsValue><GoodsValueCurrency><Code>USD</Code></GoodsValueCurrency>')
        ->toContain('<TotalVolumeUnit><Code>M3</Code></TotalVolumeUnit><ActualChargeable>415</ActualChargeable>')
        ->toContain('<WayBillNumber>SAE132767</WayBillNumber><WayBillType><Code>HWB</Code></WayBillType>')
        ->toContain('<CarrierContractNumber>CC-1</CarrierContractNumber>')
        ->toContain('<Date><Type>Departure</Type><IsEstimate>true</IsEstimate><Value>2026-10-25T00:00:00</Value></Date>')
        ->toContain('<Date><Type>Arrival</Type><IsEstimate>false</IsEstimate><Value>2026-10-26T00:00:00</Value></Date>')
        ->toContain('<OrganizationAddress><AddressType>ConsignorDocumentaryAddress</AddressType><OrganizationCode>CDEGLOBFS</OrganizationCode></OrganizationAddress>')
        ->toContain('<OrganizationAddress><AddressType>ConsigneePickupDeliveryAddress</AddressType><CompanyName>SRM MATERIALS LLC</CompanyName><Address1>4199 COUNTY ROAD</Address1><City>CHANNING</City><State>TX</State><Postcode>79018</Postcode><Country><Code>US</Code></Country><Email>IRODRIGUEZ@SRMMATERIALS.COM</Email><Phone>+1 806-683-1249</Phone></OrganizationAddress>')
        ->toContain('<OrganizationAddress><AddressType>ControllingAgent</AddressType><OrganizationCode>TCBNIBFS</OrganizationCode></OrganizationAddress>')
        ->toContain('<PackingLine><GoodsDescription>1.8MTR M/C CROSS MEMBER</GoodsDescription><PackQty>1</PackQty><PackType><Code>PLT</Code></PackType><Weight>415</Weight><WeightUnit><Code>KG</Code></WeightUnit><Volume>2.2</Volume><VolumeUnit><Code>M3</Code></VolumeUnit><Length>200</Length><LengthUnit><Code>CM</Code></LengthUnit><Width>100</Width><Height>110</Height><HarmonisedCode>847490</HarmonisedCode><MarksAndNos>N/M</MarksAndNos></PackingLine>')
        ->toContain('<Container><ContainerCount>1</ContainerCount><ContainerType><Code>40HC</Code><ISOCode>45G1</ISOCode></ContainerType></Container>')
        ->toContain('<LocalProcessing><PickupRequiredBy>2026-10-20T09:00:00</PickupRequiredBy><FCLPickupEquipmentNeeded><Code>CSA</Code></FCLPickupEquipmentNeeded><OrderNumberCollection><OrderNumber><OrderReference>UKPO62027</OrderReference></OrderNumber></OrderNumberCollection></LocalProcessing>')
        ->toContain('<AdditionalReference><Type><Code>CLR</Code></Type><ReferenceNumber>ST-1356</ReferenceNumber></AdditionalReference>')
        ->toContain('<AdditionalReference><Type><Code>BKG</Code></Type><ReferenceNumber>46017692</ReferenceNumber><CountryOfIssue><Code>GB</Code></CountryOfIssue></AdditionalReference>')
        ->not->toContain('<SenderID>')
        ->not->toContain('<RecipientID>')
        ->not->toContain('<Key>')
        ->not->toContain('<WidthUnit>')
        ->not->toContain('<AddressOverride>');
});

it('only emits technical defaults for booking dates', function () {
    $xml = Cord::withCompany('NYC')
        ->booking()
        ->create()
        ->branch('ATL')->department('AIR')->transportMode('SEA')->packingMode('FCL')
        ->controllingAgent('TCBNIBFS')
        ->departureDate('2026-10-14')
        ->inspect();

    expect($xml)
        ->toContain('<Type>Departure</Type><IsEstimate>true</IsEstimate>')
        ->not->toContain('<ServiceLevel>')
        ->not->toContain('<ShipmentIncoTerm>')
        ->not->toContain('<LocalProcessing>');
});

it('validates required booking create fields', function () {
    $errors = [];

    try {
        Cord::booking()->create()->inspect();
    } catch (ValidationException $e) {
        $errors = array_keys($e->errors());
    }

    expect($errors)->toContain('company', 'branch', 'department', 'transport_mode', 'packing_mode')
        ->not->toContain('controlling_agent');

    expect(fn () => Cord::fromStructured('booking.create', ['company' => 'NYC', 'branch' => 'ATL']))
        ->toThrow(ValidationException::class);
});

it('rejects invalid booking parties, units and containers', function () {
    $base = fn () => Cord::withCompany('NYC')->booking()->create()
        ->branch('ATL')->department('AIR')->transportMode('AIR')->packingMode('LSE')
        ->controllingAgent('TCBNIBFS');

    $errorsFor = function (Closure $configure) use ($base): array {
        try {
            $configure($base())->inspect();
        } catch (ValidationException $e) {
            return array_keys($e->errors());
        }

        return [];
    };

    expect($errorsFor(fn ($c) => $c->addParty(fn ($p) => $p->addressType('NotifyParty')->companyName('ACME'))))
        ->toContain('parties.0.address_line_1', 'parties.0.city', 'parties.0.country')
        ->and($errorsFor(fn ($c) => $c->addParty(fn ($p) => $p->addressType('ControllingAgent')->organizationCode('X'))))
        ->toContain('parties.0.address_type')
        ->and($errorsFor(fn ($c) => $c->totalWeight(10, '')))
        ->toContain('total_weight.unit_code')
        ->and($errorsFor(fn ($c) => $c->addPackLine(fn ($l) => $l->packageType('CTN')->quantity(1)->width(10, 'CM'))))
        ->toContain('packLines.0.width')
        ->and($errorsFor(fn ($c) => $c->addPackLine(fn ($l) => $l->packageType('CTN')->quantity(1)->length(10, 'CM')->width(10, 'IN'))))
        ->toContain('packLines.0.width')
        ->and($errorsFor(fn ($c) => $c->addContainer(fn ($k) => $k->type('20GP')->count(2)->number('MSKU1234567'))))
        ->toContain('containers.0.number');

    expect(fn () => Cord::fromStructured('booking.create', [
        'company' => 'NYC',
        'branch' => 'ATL',
        'department' => 'AIR',
        'transport_mode' => 'AIR',
        'packing_mode' => 'LSE',
        'controlling_agent' => 'TCBNIBFS',
        'total_weight' => ['value' => 10],
    ]))->toThrow(ValidationException::class);
});

it('rejects booking only methods on consol create and vice versa', function () {
    expect(fn () => Cord::withCompany('NYC')->consol()->create()->houseBill('X'))
        ->toThrow(Exception::class, 'houseBill() is not supported for consol create() requests.')
        ->and(fn () => Cord::withCompany('NYC')->booking()->create()->masterBill('X'))
        ->toThrow(Exception::class, 'masterBill() is not supported for booking create() requests.')
        ->and(fn () => Cord::withCompany('NYC')->consol()->create()->totalWeight(1, 'KG'))
        ->toThrow(Exception::class, 'totalWeight() requires booking()->create() context.')
        ->and(fn () => Cord::withCompany('NYC')->houseBill('X'))
        ->toThrow(Exception::class, 'houseBill() requires booking()->create() or consol()->create() context.');
});

it('builds a consol create payload from fluent and structured input', function () {
    $structured = Cord::fromStructured('consol.create', [
        'company' => 'NYC',
        'branch' => 'ATL',
        'department' => 'AIR',
        'transport_mode' => 'AIR',
        'packing_mode' => 'LSE',
        'consol_type' => 'AGT',
        'business_direction' => 'IMP',
        'payment_method' => 'PPD',
        'master_bill' => '160-20067596',
        'carrier' => 'CATAIR_WW',
        'carrier_contract_number' => 'CC-9',
        'port_of_loading' => 'CNWUH',
        'port_of_discharge' => 'USSFO',
        'sending_agent' => 'PIFGLOCAN1',
        'receiving_agent' => 'NTGAIRSAV',
        'transport_legs' => [
            [
                'order' => 1,
                'type' => 'Flight1',
                'mode' => 'AIR',
                'port_of_loading' => 'CNWUH',
                'port_of_discharge' => 'HKHKG',
                'voyage_flight_no' => 'CX937',
                'estimated_departure' => '2026-10-25',
                'carrier' => 'CATAIR_WW',
            ],
            [
                'order' => 2,
                'type' => 'Flight2',
                'mode' => 'AIR',
                'port_of_loading' => 'HKHKG',
                'port_of_discharge' => 'USSFO',
                'estimated_arrival' => '2026-10-26',
            ],
        ],
        'containers' => [['type' => '40HC', 'number' => 'MSKU1234567', 'seal' => 'SEAL1']],
        'shipment_keys' => ['SNTG26092614', 'SNTG26092615'],
    ])->inspect();

    $fluent = Cord::withCompany('NYC')
        ->consol()
        ->create()
        ->branch('ATL')
        ->department('AIR')
        ->transportMode('AIR')
        ->packingMode('LSE')
        ->consolType('AGT')
        ->businessDirection('IMP')
        ->paymentMethod('PPD')
        ->masterBill('160-20067596')
        ->carrier('CATAIR_WW')
        ->carrierContractNumber('CC-9')
        ->portOfLoading('CNWUH')
        ->portOfDischarge('USSFO')
        ->sendingAgent('PIFGLOCAN1')
        ->receivingAgent('NTGAIRSAV')
        ->addTransportLeg(fn ($l) => $l->order(1)->type('Flight1')->mode('AIR')->portOfLoading('CNWUH')->portOfDischarge('HKHKG')->voyageFlightNo('CX937')->estimatedDeparture('2026-10-25')->carrier('CATAIR_WW'))
        ->addTransportLeg(fn ($l) => $l->order(2)->type('Flight2')->mode('AIR')->portOfLoading('HKHKG')->portOfDischarge('USSFO')->estimatedArrival('2026-10-26'))
        ->addContainer(fn ($c) => $c->type('40HC')->number('MSKU1234567')->seal('SEAL1'))
        ->linkShipment('SNTG26092614')
        ->linkShipment('SNTG26092615')
        ->inspect();

    expect($structured)->toBe($fluent)
        ->and($fluent)
        ->toContain('<DataTarget><Type>ForwardingConsol</Type></DataTarget>')
        ->toContain('<ShipmentType><Code>AGT</Code></ShipmentType><TransportBookingDirection><Code>IMP</Code></TransportBookingDirection>')
        ->toContain('<PaymentMethod><Code>PPD</Code></PaymentMethod>')
        ->toContain('<WayBillNumber>160-20067596</WayBillNumber><WayBillType><Code>MWB</Code></WayBillType>')
        ->toContain('<TransportLeg><LegOrder>1</LegOrder><TransportMode>Air</TransportMode><PortOfLoading><Code>CNWUH</Code></PortOfLoading><PortOfDischarge><Code>HKHKG</Code></PortOfDischarge><LegType>Flight1</LegType><VoyageFlightNo>CX937</VoyageFlightNo><EstimatedDeparture>2026-10-25T00:00:00</EstimatedDeparture><Carrier><AddressType>AirlineAddress</AddressType><OrganizationCode>CATAIR_WW</OrganizationCode></Carrier></TransportLeg>')
        ->toContain('<EstimatedArrival>2026-10-26T00:00:00</EstimatedArrival></TransportLeg>')
        ->toContain('<OrganizationAddress><AddressType>SendingForwarderAddress</AddressType><OrganizationCode>PIFGLOCAN1</OrganizationCode></OrganizationAddress>')
        ->toContain('<OrganizationAddress><AddressType>ShippingLineAddress</AddressType><OrganizationCode>CATAIR_WW</OrganizationCode></OrganizationAddress>')
        ->toContain('<Container><ContainerNumber>MSKU1234567</ContainerNumber><ContainerCount>1</ContainerCount><ContainerType><Code>40HC</Code></ContainerType><Seal>SEAL1</Seal></Container>')
        ->toContain('<SubShipment><DataContext><DataTargetCollection><DataTarget><Type>ForwardingShipment</Type><Key>SNTG26092614</Key></DataTarget></DataTargetCollection></DataContext></SubShipment>')
        ->toContain('<Key>SNTG26092615</Key>')
        ->not->toContain('<SenderID>');
});

it('validates required consol create fields and transport legs', function () {
    $errors = [];

    try {
        Cord::withCompany('NYC')->consol()->create()
            ->addTransportLeg(fn ($l) => $l->order(1))
            ->inspect();
    } catch (ValidationException $e) {
        $errors = array_keys($e->errors());
    }

    expect($errors)->toContain('branch', 'department', 'transport_mode', 'packing_mode', 'transport_legs.0.type', 'transport_legs.0.mode', 'transport_legs.0.port_of_loading')
        ->not->toContain('controlling_agent');

    expect(fn () => Cord::fromStructured('consol.create', [
        'company' => 'NYC',
        'branch' => 'ATL',
        'department' => 'AIR',
        'transport_mode' => 'AIR',
        'packing_mode' => 'LSE',
        'transport_legs' => [['order' => 1]],
    ]))->toThrow(ValidationException::class);
});

it('links shipments to an existing consol through update', function () {
    $fluent = Cord::withCompany('NYC')
        ->consol('CUS26074045')
        ->update()
        ->linkShipment('SNTG26094969')
        ->inspect();

    $structured = Cord::fromStructured('consol.update', [
        'company' => 'NYC',
        'key' => 'CUS26074045',
        'shipment_keys' => ['SNTG26094969'],
    ])->inspect();

    expect($structured)->toBe($fluent)
        ->and($fluent)
        ->toContain('<DataTarget><Type>ForwardingConsol</Type><Key>CUS26074045</Key></DataTarget>')
        ->toContain('<SubShipmentCollection><SubShipment><DataContext><DataTargetCollection><DataTarget><Type>ForwardingShipment</Type><Key>SNTG26094969</Key></DataTarget></DataTargetCollection></DataContext></SubShipment></SubShipmentCollection>')
        ->not->toContain('<SenderID>');

    expect(fn () => Cord::withCompany('NYC')->shipment('SNTG26094969')->update()->linkShipment('X'))
        ->toThrow(Exception::class, 'linkShipment() requires consol()->create() or consol(\'KEY\')->update() context.');
});

it('converts a booking to a shipment with an event on the same key', function () {
    $fluent = Cord::withCompany('NYC')->booking('SNTG26094969')->convert()->inspect();
    $structured = Cord::fromStructured('booking.convert', ['company' => 'NYC', 'key' => 'SNTG26094969'])->inspect();

    expect(preg_replace('/<EventTime>[^<]+<\/EventTime>/', '', $fluent))
        ->toBe(preg_replace('/<EventTime>[^<]+<\/EventTime>/', '', $structured))
        ->and($fluent)
        ->toContain('<UniversalEvent><Event><DataContext>')
        ->toContain('<DataTarget><Type>ForwardingShipment</Type><Key>SNTG26094969</Key></DataTarget>')
        ->toContain('<Company><Code>NYC</Code></Company>')
        ->toContain('<EnterpriseID>DEMO1</EnterpriseID>')
        ->toContain('<EventType>Z03</EventType>')
        ->toContain('<IsEstimate>false</IsEstimate>')
        ->toContain('<AdditionalFieldsToUpdate><Type>ForwardingShipment.JS_IsForwardRegistered</Type><Value>true</Value></AdditionalFieldsToUpdate>')
        ->not->toContain('<SenderID>')
        ->not->toContain('ForwardingBooking');

    expect((bool) preg_match('/<EventTime>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z<\/EventTime>/', $fluent))->toBeTrue();
});

it('requires a booking key and company to convert', function () {
    expect(fn () => Cord::withCompany('NYC')->booking()->convert())
        ->toThrow(Exception::class, "convert() requires booking('KEY') context.")
        ->and(fn () => Cord::booking('SNTG26094969')->convert()->inspect())
        ->toThrow(Exception::class, 'Company code must be provided');
});

function universalResponse(string $dataSources, string $status = 'PRS', string $extra = ''): string
{
    return <<<XML
<UniversalResponse>
    <Status>{$status}</Status>
    <Data>
        <UniversalEvent>
            <Event>
                <DataContext>
                    <DataSourceCollection>{$dataSources}</DataSourceCollection>
                </DataContext>
            </Event>
        </UniversalEvent>
    </Data>
    <MessageNumberCollection>
        <MessageNumber>00000000000023108335</MessageNumber>
    </MessageNumberCollection>
    <ProcessingLog>Information - created</ProcessingLog>{$extra}
</UniversalResponse>
XML;
}

it('reads the new key from the DataSource that matches the job type', function () {
    Http::fake([
        '*' => Http::response(universalResponse(
            '<DataSource><Type>ForwardingConsol</Type><Key>CUS26074045</Key></DataSource>'
            .'<DataSource><Type>ForwardingBooking</Type><Key>SNTG26094969</Key></DataSource>'
        ), 200, ['Content-Type' => 'application/xml']),
    ]);

    $result = Cord::fromStructured('booking.create', bookingStructuredPayload())->run();

    expect($result)->toMatchArray([
        'status' => 'PRS',
        'key' => 'SNTG26094969',
        'message_number' => '00000000000023108335',
        'processing_log' => 'Information - created',
    ]);

    $consol = Cord::withCompany('NYC')->consol()->create()
        ->branch('ATL')->department('AIR')->transportMode('AIR')->packingMode('LSE')
        ->run();

    expect($consol['key'])->toBe('CUS26074045');
});

it('falls back to DataTarget and never falls back to a passed key on create', function () {
    $targetOnly = <<<'XML'
<UniversalResponse>
    <Status>PRS</Status>
    <Data>
        <UniversalShipment>
            <Shipment>
                <DataContext>
                    <DataTargetCollection>
                        <DataTarget>
                            <Type>ForwardingBooking</Type>
                            <Key>SNTG26094969</Key>
                        </DataTarget>
                    </DataTargetCollection>
                </DataContext>
            </Shipment>
        </UniversalShipment>
    </Data>
</UniversalResponse>
XML;
    $empty = '<UniversalResponse><Status>PRS</Status><Data /></UniversalResponse>';

    Http::fake([
        '*' => Http::sequence()
            ->push($targetOnly, 200, ['Content-Type' => 'application/xml'])
            ->push($empty, 200, ['Content-Type' => 'application/xml'])
            ->push($empty, 200, ['Content-Type' => 'application/xml']),
    ]);

    expect(Cord::fromStructured('booking.create', bookingStructuredPayload())->run()['key'])->toBe('SNTG26094969');

    $create = Cord::withCompany('NYC')->consol('CUS26074045')->create()
        ->branch('ATL')->department('AIR')->transportMode('AIR')->packingMode('LSE')
        ->run();

    expect($create['key'])->toBeNull()
        ->and($create['message_number'])->toBeNull();

    $convert = Cord::withCompany('NYC')->booking('SNTG26094969')->convert()->run();

    expect($convert['key'])->toBe('SNTG26094969');
});

it('throws a dedicated exception on ERR replies for booking and consol writes even when json is requested', function () {
    Http::fake([
        '*' => Http::response(universalResponse('', 'ERR'), 200, ['Content-Type' => 'application/xml']),
    ]);

    app()->instance('request', Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']));

    foreach ([
        fn () => Cord::fromStructured('booking.create', bookingStructuredPayload())->run(),
        fn () => Cord::withCompany('NYC')->booking('SNTG26094969')->convert()->run(),
    ] as $send) {
        try {
            $send();
            $this->fail('Expected an EadapterRejectedException.');
        } catch (EadapterRejectedException $e) {
            expect($e->getMessage())->toBe('Information - created')
                ->and($e->status)->toBe('ERR')
                ->and($e->processingLog)->toBe('Information - created')
                ->and($e->messageNumber)->toBe('00000000000023108335');
        }
    }
});

it('keeps transport failures distinct from a rejection', function () {
    Http::fake(['*' => Http::response('', 503)]);

    expect(fn () => Cord::fromStructured('booking.create', bookingStructuredPayload())->run())
        ->toThrow(RequestException::class);

    Http::fake(['*' => fn () => throw new ConnectionException('timeout')]);

    expect(fn () => Cord::withCompany('NYC')->booking('SNTG26094969')->convert()->run())
        ->toThrow(ConnectionException::class);
});

it('allows the convert event type to be passed', function () {
    $fluent = Cord::withCompany('NYC')->booking('SNTG26094969')->convert('Z05')->inspect();
    $structured = Cord::fromStructured('booking.convert', ['company' => 'NYC', 'key' => 'SNTG26094969', 'event_type' => 'Z05'])->inspect();

    expect($fluent)->toContain('<EventType>Z05</EventType>')->not->toContain('Z03')
        ->and($structured)->toContain('<EventType>Z05</EventType>')->not->toContain('Z03');

    expect(fn () => Cord::withCompany('NYC')->booking('SNTG26094969')->convert(' '))
        ->toThrow(ValidationException::class);
});

it('exposes booking and consol create and convert schemas', function () {
    $booking = Cord::schema('booking.create');
    $consol = Cord::schema('consol.create');
    $convert = Cord::schema('booking.convert');

    expect($booking['required'])
        ->toContain('company', 'branch', 'department', 'transport_mode', 'packing_mode')
        ->not->toContain('controlling_agent')
        ->and(array_keys($booking['properties']))
        ->toContain('key', 'parties', 'pack_lines', 'containers', 'order_numbers', 'additional_references', 'departure_date', 'house_bill')
        ->not->toContain('master_bill', 'transport_legs')
        ->and($booking['properties']['parties']['items']['required'])->toContain('address_type')
        ->and($booking['properties']['pack_lines']['items']['properties'])->toHaveKeys(['pack_type', 'quantity', 'hs_code', 'marks_and_numbers'])
        ->and($consol['required'])->toContain('company', 'branch', 'department', 'transport_mode', 'packing_mode')
        ->not->toContain('controlling_agent')
        ->and(array_keys($consol['properties']))
        ->toContain('master_bill', 'consol_type', 'business_direction', 'transport_legs', 'shipment_keys', 'containers')
        ->not->toContain('house_bill', 'parties')
        ->and($convert['required'])->toContain('company', 'key')
        ->and(Cord::schema('consol.update')['properties'])->toHaveKey('shipment_keys');
});
