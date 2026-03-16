<?php

namespace Oliverbj\Cord;

use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Oliverbj\Cord\Attributes\OperationField;
use Oliverbj\Cord\Builders\OneOffQuoteAddressBuilder;
use Oliverbj\Cord\Builders\OneOffQuoteAttachedDocumentBuilder;
use Oliverbj\Cord\Builders\OneOffQuoteChargeLineBuilder;
use Oliverbj\Cord\Builders\OrganizationAddressBuilder;
use Oliverbj\Cord\Builders\OrganizationContactBuilder;
use Oliverbj\Cord\Builders\OrganizationEDICommunicationBuilder;
use Oliverbj\Cord\Enums\DataTarget;
use Oliverbj\Cord\Enums\OperationId;
use Oliverbj\Cord\Enums\RequestType;
use Oliverbj\Cord\Interfaces\RequestInterface;
use Oliverbj\Cord\Requests\NativeCompanyRetrieval;
use Oliverbj\Cord\Requests\NativeOrganizationCreation;
use Oliverbj\Cord\Requests\NativeOrganizationRetrieval;
use Oliverbj\Cord\Requests\NativeOrganizationUpdate;
use Oliverbj\Cord\Requests\NativeStaffCreation;
use Oliverbj\Cord\Requests\NativeStaffUpdate;
use Oliverbj\Cord\Requests\UniversalDocumentRequest;
use Oliverbj\Cord\Requests\UniversalEvent;
use Oliverbj\Cord\Requests\UniversalShipmentRequest;
use Oliverbj\Cord\Schema\OperationDefinition;
use Oliverbj\Cord\Schema\OperationRegistry;
use Oliverbj\Cord\Schema\SchemaValidator;

class Cord
{
    public DataTarget $target = DataTarget::Shipment;

    public RequestType $requestType = RequestType::UniversalShipmentRequest;

    public ?string $targetKey = null;

    public ?string $company = null;

    public ?string $server = null;

    public ?string $enterprise = null;

    public ?array $config = null;

    public ?string $senderId = null;

    public string $recipientId = 'Cord';

    public bool $enableCodeMapping = true;

    public array $criteriaGroups = [];

    public array $filters = [];

    public array $event = [];

    public array $document = [];

    public array $address = [];

    public array $contact = [];

    public array $jobRequiredDocument = [];

    public array $ediCommunication = [];

    public array $staff = [];

    public array $oneOffQuote = [];

    public ?OperationId $currentOperation = null;

    public string $configName = 'base';

    protected ?string $staffIntent = null;

    protected array $staffDraft = [];

    protected ?string $oneOffQuoteIntent = null;

    protected array $oneOffQuoteDraft = [];

    protected ?string $organizationIntent = null;

    public array $organizationDraft = [];

    protected ?string $xml = null;

    public bool $asXml = false;

    protected ?PendingRequest $client = null;

    protected array $structuredOverrides = [];

    public function __construct()
    {
        $this->setConnectionConfig(config('cord.base.eadapter_connection'));
    }

    protected function setClient()
    {
        $this->client = Http::withBasicAuth(
            $this->config['username'],
            $this->config['password']
        )->withHeaders([
            'Accept' => 'application/xml',
            'Content-Type' => 'application/xml',
        ]);
    }

    public function withCompany(string $company): self
    {
        $this->company = $company;
        $this->markStructuredField('company');

        return $this;
    }

    public function withServer(string $server): self
    {
        $this->server = $server;
        $this->markStructuredField('server');

        return $this;
    }

    public function withEnterprise(string $enterprise): self
    {
        $this->enterprise = $enterprise;
        $this->markStructuredField('enterprise');

        return $this;
    }

    public function withConfig(string $configName): self
    {
        $this->configName = $configName;
        $this->setConnectionConfig(config('cord.'.$configName.'.eadapter_connection'));
        $this->markStructuredField('config');

        return $this;
    }

    /**
     * @deprecated Native owner-code headers are no longer emitted.
     */
    public function withOwnerCode(string $ownerCode): self
    {
        return $this;
    }

    public function withSenderId(string $senderId): self
    {
        $this->senderId = $senderId;
        $this->markStructuredField('sender_id');

        return $this;
    }

    public function withRecipientId(string $recipientId): self
    {
        $this->recipientId = $recipientId;
        $this->markStructuredField('recipient_id');

        return $this;
    }

    public function withRecepientId(string $recipientId): self
    {
        return $this->withRecipientId($recipientId);
    }

    public function withCodeMapping(bool $enabled): self
    {
        $this->enableCodeMapping = $enabled;
        $this->markStructuredField('code_mapping');

        return $this;
    }

    /**
     * Determine if the request is for a booking.
     */
    public function booking(string $booking): self
    {
        $this->targetKey = $booking;
        $this->target = DataTarget::Booking;
        $this->requestType = RequestType::UniversalShipmentRequest;
        $this->currentOperation = OperationId::BookingGet;
        $this->markStructuredField('key');

        return $this;
    }

    /**
     * Determine if the request is for a receiveable
     * Note: eAdaptor cannot query AR/APs, so this can only be used in junction
     * with the "withDocuments()" method.
     */
    public function receiveable(string $receiveable): self
    {
        $this->targetKey = 'AR INV '.$receiveable;
        $this->target = DataTarget::Receiveable;
        $this->currentOperation = null;
        $this->markStructuredField('key');

        return $this;
    }

    /**
     * Determine if the request is for a receivable.
     */
    public function receivable(string $receivable): self
    {
        return $this->receiveable($receivable);
    }

    /**
     * Determine if the request is for a shipment.
     */
    public function shipment(string $shipment): self
    {
        $this->targetKey = $shipment;
        $this->target = DataTarget::Shipment;
        $this->requestType = RequestType::UniversalShipmentRequest;
        $this->currentOperation = OperationId::ShipmentGet;
        $this->markStructuredField('key');

        return $this;
    }

    /**
     * Select the CargoWise one-off quote resource.
     *
     * Key is optional for create requests.
     *
     * Examples:
     * `->oneOffQuote()`
     * `->oneOffQuote('00001063')`
     */
    public function oneOffQuote(?string $key = null): self
    {
        $this->target = DataTarget::OneOffQuote;
        $this->requestType = RequestType::UniversalShipmentRequest;
        $this->targetKey = $key;
        $this->oneOffQuoteIntent = null;
        $this->oneOffQuoteDraft = [];
        $this->oneOffQuote = [];
        $this->currentOperation = null;

        if ($key !== null) {
            $this->markStructuredField('key');
        }

        return $this;
    }

    /**
     * Determine if the request is for a native request.
     */
    public function organization(?string $code = null): self
    {
        // Reset the criteria group.
        $this->criteriaGroups = [];
        $this->targetKey = null;
        $this->organizationIntent = null;
        $this->organizationDraft = [];

        $this->requestType = RequestType::NativeOrganizationRetrieval;
        $this->target = DataTarget::Organization;
        $this->currentOperation = OperationId::OrganizationQuery;

        if ($code) {
            $this->targetKey = $code;
            $this->markStructuredField('code');
            $this->criteriaGroup([
                [
                    'Entity' => 'OrgHeader',
                    'FieldName' => 'Code',
                    'Value' => $code,
                ],
            ], type: 'Key');
        }

        return $this;
    }

    /**
     * Select the CargoWise staff resource.
     *
     * Optionally pre-select a staff code for update flows.
     *
     * Examples:
     * `->staff()`
     * `->staff('OJ0')`
     */
    public function staff(?string $code = null): self
    {
        $this->target = DataTarget::Staff;
        $this->targetKey = $code;
        $this->staffIntent = null;
        $this->staffDraft = [];
        $this->staff = [];
        $this->currentOperation = null;

        if ($code !== null) {
            $this->markStructuredField('code');
        }

        return $this;
    }

    /**
     * Set the intent to retrieve a resource.
     *
     * Staff retrieval is not implemented yet.
     *
     * Example:
     * `->get()`
     */
    public function get(): self
    {
        if ($this->target === DataTarget::Staff) {
            throw new \Exception('Staff get() is not implemented yet. Use staff() endpoints that already support retrieval.');
        }

        if ($this->target === DataTarget::OneOffQuote) {
            throw new \Exception('OneOffQuote get() is not implemented yet.');
        }

        return $this;
    }

    /**
     * Set the intent to create a new staff member.
     *
     * Call this before using staff payload setters.
     *
     * Example:
     * `->staff()->create()`
     */
    public function create(): self
    {
        if ($this->target === DataTarget::Staff) {
            $this->staffIntent = 'create';
            $this->requestType = RequestType::NativeStaffCreation;
            $this->currentOperation = OperationId::StaffCreate;

            return $this;
        }

        if ($this->target === DataTarget::OneOffQuote) {
            $this->oneOffQuoteIntent = 'create';
            $this->requestType = RequestType::UniversalShipmentRequest;
            $this->targetKey = $this->targetKey ?? '';
            $this->currentOperation = OperationId::OneOffQuoteCreate;

            return $this;
        }

        if ($this->target === DataTarget::Organization) {
            if (! $this->targetKey) {
                throw new \Exception('organization() requires a code for create(). Use organization(\'CODE\')->create().');
            }
            $this->organizationIntent = 'create';
            $this->requestType = RequestType::NativeOrganizationCreation;
            $this->currentOperation = OperationId::OrganizationCreate;

            return $this;
        }

        throw new \Exception('create() is currently implemented for staff, oneOffQuote and organization only.');
    }

    /**
     * Set the intent to update an existing staff member.
     *
     * Call this before using staff payload setters.
     *
     * Example:
     * `->staff('OJ0')->update()`
     */
    public function update(): self
    {
        if ($this->target === DataTarget::Staff) {
            $this->staffIntent = 'update';
            $this->requestType = RequestType::NativeStaffUpdate;
            $this->currentOperation = OperationId::StaffUpdate;

            if ($this->targetKey) {
                $this->staffDraft['code'] = $this->targetKey;
            }

            return $this;
        }

        if ($this->target === DataTarget::Organization) {
            if (! $this->targetKey) {
                throw new \Exception('organization() requires a code for update(). Use organization(\'CODE\')->update().');
            }

            $this->organizationIntent = 'update';
            $this->requestType = RequestType::NativeOrganizationUpdate;

            return $this;
        }

        if ($this->target === DataTarget::OneOffQuote) {
            throw new \Exception('OneOffQuote update() is not implemented yet.');
        }

        throw new \Exception('update() is currently implemented for staff and organization only.');
    }

    /**
     * Set the intent to delete a resource.
     *
     * Staff delete is not implemented yet.
     *
     * Example:
     * `->delete()`
     */
    public function delete(): self
    {
        if ($this->target === DataTarget::Staff) {
            throw new \Exception('Staff delete() is not implemented yet.');
        }

        if ($this->target === DataTarget::OneOffQuote) {
            throw new \Exception('OneOffQuote delete() is not implemented yet.');
        }

        return $this;
    }

    /**
     * Set the intent to upsert a resource.
     *
     * Staff upsert is reserved for a later implementation.
     *
     * Example:
     * `->upsert()`
     */
    public function upsert(): self
    {
        if ($this->target === DataTarget::Staff) {
            throw new \Exception('Staff upsert() is not implemented yet.');
        }

        if ($this->target === DataTarget::OneOffQuote) {
            throw new \Exception('OneOffQuote upsert() is not implemented yet.');
        }

        return $this;
    }

    /**
     * Set the unique CargoWise staff code.
     *
     * Required for create and update staff requests.
     *
     * Example:
     * `->code('OJ0')`
     */
    #[OperationField(OperationId::StaffCreate, required: true)]
    #[OperationField(OperationId::StaffUpdate, required: true)]
    public function code(string $code): self
    {
        return $this->setStaffDraftValue('code', $code, true);
    }

    /**
     * Set the CargoWise login name for the staff member.
     *
     * Required for create requests.
     *
     * Example:
     * `->loginName('oliver.busk')`
     */
    #[OperationField(OperationId::StaffCreate, name: 'login_name', required: true)]
    #[OperationField(OperationId::StaffUpdate, name: 'login_name')]
    public function loginName(string $loginName): self
    {
        return $this->setStaffDraftValue('loginName', $loginName);
    }

    /**
     * Set the staff password.
     *
     * Required for create requests.
     *
     * Example:
     * `->password('secret')`
     */
    #[OperationField(OperationId::StaffCreate, required: true)]
    #[OperationField(OperationId::StaffUpdate)]
    public function password(string $password): self
    {
        $this->setStaffDraftValue('password', $password);

        // Password updates must always force a rotation on next login.
        return $this->setStaffDraftValue('changePasswordAtNextLogin', true);
    }

    /**
     * Set the full display name of the staff member.
     *
     * Required for create requests.
     *
     * Example:
     * `->fullName('Oliver Busk')`
     */
    #[OperationField(OperationId::StaffCreate, name: 'full_name', required: true)]
    #[OperationField(OperationId::StaffUpdate, name: 'full_name')]
    #[OperationField(OperationId::OrganizationCreate, name: 'full_name', required: true)]
    public function fullName(string $fullName): self
    {
        if ($this->target === DataTarget::Organization) {
            return $this->setOrganizationDraftValue('fullName', $fullName);
        }

        return $this->setStaffDraftValue('fullName', $fullName);
    }

    /**
     * Set the staff email address.
     *
     * Example:
     * `->email('oliver@example.com')`
     */
    #[OperationField(OperationId::StaffCreate)]
    #[OperationField(OperationId::StaffUpdate)]
    public function email(string $email): self
    {
        return $this->setStaffDraftValue('email', $email);
    }

    /**
     * Set whether the staff member is active.
     *
     * Example:
     * `->isActive(true)`
     */
    #[OperationField(OperationId::StaffCreate, name: 'is_active')]
    #[OperationField(OperationId::StaffUpdate, name: 'is_active')]
    #[OperationField(OperationId::OrganizationCreate, name: 'is_active')]
    public function isActive(bool $isActive): self
    {
        if ($this->target === DataTarget::Organization) {
            return $this->setOrganizationDraftValue('isActive', $isActive);
        }

        return $this->setStaffDraftValue('active', $isActive);
    }

    /**
     * Mark the organization as a consignee.
     *
     * Example:
     * `->isConsignee(true)`
     */
    #[OperationField(OperationId::OrganizationCreate, name: 'is_consignee')]
    public function isConsignee(bool $value): self
    {
        return $this->setOrganizationDraftValue('isConsignee', $value);
    }

    /**
     * Mark the organization as a consignor.
     *
     * Example:
     * `->isConsignor(true)`
     */
    #[OperationField(OperationId::OrganizationCreate, name: 'is_consignor')]
    public function isConsignor(bool $value): self
    {
        return $this->setOrganizationDraftValue('isConsignor', $value);
    }

    /**
     * Mark the organization as a freight forwarder.
     *
     * Example:
     * `->isForwarder(true)`
     */
    #[OperationField(OperationId::OrganizationCreate, name: 'is_forwarder')]
    public function isForwarder(bool $value): self
    {
        return $this->setOrganizationDraftValue('isForwarder', $value);
    }

    /**
     * Mark the organization as an airline.
     *
     * Example:
     * `->isAirLine(true)`
     */
    #[OperationField(OperationId::OrganizationCreate, name: 'is_air_line')]
    public function isAirLine(bool $value): self
    {
        return $this->setOrganizationDraftValue('isAirLine', $value);
    }

    /**
     * Set the closest UNLOCO port code for the organization.
     *
     * Example:
     * `->closestPort('AUSYD')`
     */
    #[OperationField(OperationId::OrganizationCreate, name: 'closest_port')]
    public function closestPort(string $code): self
    {
        return $this->setOrganizationDraftValue('closestPort', $code);
    }

    /**
     * Set the home branch code.
     *
     * Required for create requests.
     *
     * Example:
     * `->branch('CPH')`
     */
    #[OperationField(OperationId::StaffCreate, required: true)]
    #[OperationField(OperationId::StaffUpdate)]
    #[OperationField(OperationId::OneOffQuoteCreate, required: true)]
    public function branch(string $branch): self
    {
        if ($this->target === DataTarget::OneOffQuote) {
            return $this->setOneOffQuoteDraftValue('branch', $branch);
        }

        return $this->setStaffDraftValue('homeBranch', $branch);
    }

    /**
     * Set the home department code.
     *
     * Required for create requests.
     *
     * Example:
     * `->department('OPS')`
     */
    #[OperationField(OperationId::StaffCreate, required: true)]
    #[OperationField(OperationId::StaffUpdate)]
    #[OperationField(OperationId::OneOffQuoteCreate, required: true)]
    public function department(string $department): self
    {
        if ($this->target === DataTarget::OneOffQuote) {
            return $this->setOneOffQuoteDraftValue('department', $department);
        }

        return $this->setStaffDraftValue('homeDepartment', $department);
    }

    /**
     * Set the staff work phone number.
     *
     * Maps to `WorkPhone` in the CargoWise payload.
     *
     * Example:
     * `->phone('+4511223344')`
     */
    #[OperationField(OperationId::StaffCreate)]
    #[OperationField(OperationId::StaffUpdate)]
    public function phone(string $phone): self
    {
        return $this->setStaffDraftValue('workPhone', $phone);
    }

    /**
     * Set the staff country code.
     *
     * Required for create requests.
     *
     * Example:
     * `->country('DK')`
     */
    #[OperationField(OperationId::StaffCreate, required: true)]
    #[OperationField(OperationId::StaffUpdate)]
    public function country(string $country): self
    {
        return $this->setStaffDraftValue('country', $country);
    }

    /**
     * Set the first staff address line.
     *
     * Example:
     * `->addressLine1('Main Street 1')`
     */
    #[OperationField(OperationId::StaffCreate, name: 'address_line_1')]
    #[OperationField(OperationId::StaffUpdate, name: 'address_line_1')]
    public function addressLine1(string $addressLine1): self
    {
        return $this->setStaffDraftValue('addressOne', $addressLine1);
    }

    /**
     * Set one-off quote transport mode.
     */
    #[OperationField(OperationId::OneOffQuoteCreate, name: 'transport_mode', required: true, enum: ['SEA', 'AIR', 'ROA'])]
    public function transportMode(string $code): self
    {
        $this->assertOneOffQuoteBuilderContext('transportMode');

        $this->oneOffQuoteDraft['transportMode'] = [
            'code' => $code,
        ];
        $this->markStructuredField('transport_mode');

        return $this;
    }

    /**
     * Set one-off quote origin port.
     */
    #[OperationField(OperationId::OneOffQuoteCreate, name: 'port_of_origin', required: true)]
    public function portOfOrigin(string $code): self
    {
        $this->assertOneOffQuoteBuilderContext('portOfOrigin');

        $this->oneOffQuoteDraft['portOfOrigin'] = [
            'code' => $code,
        ];
        $this->markStructuredField('port_of_origin');

        return $this;
    }

    /**
     * Set one-off quote destination port.
     */
    #[OperationField(OperationId::OneOffQuoteCreate, name: 'port_of_destination', required: true)]
    public function portOfDestination(string $code): self
    {
        $this->assertOneOffQuoteBuilderContext('portOfDestination');

        $this->oneOffQuoteDraft['portOfDestination'] = [
            'code' => $code,
        ];
        $this->markStructuredField('port_of_destination');

        return $this;
    }

    /**
     * Set one-off quote service level.
     */
    #[OperationField(OperationId::OneOffQuoteCreate, name: 'service_level')]
    public function serviceLevel(string $code): self
    {
        $this->assertOneOffQuoteBuilderContext('serviceLevel');

        $this->oneOffQuoteDraft['serviceLevel'] = [
            'code' => $code,
        ];
        $this->markStructuredField('service_level');

        return $this;
    }

    /**
     * Set one-off quote incoterm.
     */
    #[OperationField(OperationId::OneOffQuoteCreate)]
    public function incoterm(string $code): self
    {
        $this->assertOneOffQuoteBuilderContext('incoterm');

        $this->oneOffQuoteDraft['incoterm'] = [
            'code' => $code,
        ];
        $this->markStructuredField('incoterm');

        return $this;
    }

    /**
     * Set one-off quote total weight.
     */
    #[OperationField(OperationId::OneOffQuoteCreate, name: 'total_weight')]
    public function totalWeight(float|int|string $value, string $unitCode): self
    {
        $this->assertOneOffQuoteBuilderContext('totalWeight');

        $this->oneOffQuoteDraft['totalWeight'] = [
            'value' => $value,
            'unitCode' => $unitCode,
        ];
        $this->markStructuredField('total_weight');

        return $this;
    }

    /**
     * Set one-off quote total volume.
     */
    #[OperationField(OperationId::OneOffQuoteCreate, name: 'total_volume')]
    public function totalVolume(float|int|string $value, string $unitCode): self
    {
        $this->assertOneOffQuoteBuilderContext('totalVolume');

        $this->oneOffQuoteDraft['totalVolume'] = [
            'value' => $value,
            'unitCode' => $unitCode,
        ];
        $this->markStructuredField('total_volume');

        return $this;
    }

    /**
     * Set one-off quote goods value.
     */
    #[OperationField(OperationId::OneOffQuoteCreate, name: 'goods_value')]
    public function goodsValue(float|int|string $amount, string $currencyCode): self
    {
        $this->assertOneOffQuoteBuilderContext('goodsValue');

        $this->oneOffQuoteDraft['goodsValue'] = [
            'amount' => $amount,
            'currencyCode' => $currencyCode,
        ];
        $this->markStructuredField('goods_value');

        return $this;
    }

    /**
     * Set one-off quote additional terms.
     */
    #[OperationField(OperationId::OneOffQuoteCreate, name: 'additional_terms')]
    public function additionalTerms(string $value): self
    {
        return $this->setOneOffQuoteDraftValue('additionalTerms', $value);
    }

    /**
     * Set one-off quote domestic freight flag.
     */
    #[OperationField(OperationId::OneOffQuoteCreate, name: 'is_domestic_freight')]
    public function isDomesticFreight(bool $value): self
    {
        return $this->setOneOffQuoteDraftValue('isDomesticFreight', $value);
    }

    /**
     * Set one-off quote client address.
     */
    #[OperationField(OperationId::OneOffQuoteCreate, name: 'client_address', builder: OneOffQuoteAddressBuilder::class)]
    public function clientAddress(Closure $builder): self
    {
        return $this->setOneOffQuoteTypedAddress('client', $builder);
    }

    /**
     * Set one-off quote pickup address.
     */
    #[OperationField(OperationId::OneOffQuoteCreate, name: 'pickup_address', builder: OneOffQuoteAddressBuilder::class)]
    public function pickupAddress(Closure $builder): self
    {
        return $this->setOneOffQuoteTypedAddress('pickup', $builder);
    }

    /**
     * Set one-off quote delivery address.
     */
    #[OperationField(OperationId::OneOffQuoteCreate, name: 'delivery_address', builder: OneOffQuoteAddressBuilder::class)]
    public function deliveryAddress(Closure $builder): self
    {
        return $this->setOneOffQuoteTypedAddress('delivery', $builder);
    }

    /**
     * Add a single charge line to the one-off quote.
     */
    #[OperationField(OperationId::OneOffQuoteCreate, name: 'charge_lines', repeatable: true, builder: OneOffQuoteChargeLineBuilder::class)]
    public function addChargeLine(Closure $builder): self
    {
        $this->assertOneOffQuoteBuilderContext('addChargeLine');

        $chargeLineBuilder = new OneOffQuoteChargeLineBuilder;
        $builder($chargeLineBuilder);

        if (! isset($this->oneOffQuoteDraft['chargeLines']) || ! is_array($this->oneOffQuoteDraft['chargeLines'])) {
            $this->oneOffQuoteDraft['chargeLines'] = [];
        }

        $this->oneOffQuoteDraft['chargeLines'][] = $chargeLineBuilder->toArray();
        $this->markStructuredField('charge_lines');

        return $this;
    }

    /**
     * Append an attached document to a one-off quote payload.
     *
     * Example:
     * `->addAttachedDocument(fn ($d) => $d->fileName('quote.pdf')->imageData($base64)->type('QUO'))`
     */
    #[OperationField(OperationId::OneOffQuoteCreate, name: 'attached_documents', repeatable: true, builder: OneOffQuoteAttachedDocumentBuilder::class)]
    public function addAttachedDocument(Closure $builder): self
    {
        $this->assertOneOffQuoteBuilderContext('addAttachedDocument');

        $documentBuilder = new OneOffQuoteAttachedDocumentBuilder;
        $builder($documentBuilder);

        if (! isset($this->oneOffQuoteDraft['attachedDocuments']) || ! is_array($this->oneOffQuoteDraft['attachedDocuments'])) {
            $this->oneOffQuoteDraft['attachedDocuments'] = [];
        }

        $this->oneOffQuoteDraft['attachedDocuments'][] = $documentBuilder->toArray();
        $this->markStructuredField('attached_documents');

        return $this;
    }

    /**
     * Add a single group membership to the outgoing payload.
     *
     * This appends to the current group list.
     *
     * Example:
     * `->addGroup('OPS')`
     */
    #[OperationField(OperationId::StaffCreate, name: 'groups_to_add', repeatable: true)]
    #[OperationField(OperationId::StaffUpdate, name: 'groups_to_add', repeatable: true)]
    public function addGroup(string $code): self
    {
        $this->assertStaffBuilderContext('addGroup');

        if (! isset($this->staffDraft['groups']) || ! is_array($this->staffDraft['groups'])) {
            $this->staffDraft['groups'] = [];
        }

        $this->staffDraft['groups'][] = $code;
        $this->markStructuredField('groups_to_add');

        return $this;
    }

    /**
     * Replace all group memberships for the staff member.
     *
     * This overwrites existing groups in the outgoing payload.
     *
     * Example:
     * `->replaceGroups(['ADM', 'OPS'])`
     *
     * @param  array<int, string>  $codes
     */
    #[OperationField(OperationId::StaffCreate, name: 'groups', schema: ['type' => 'array', 'items' => ['type' => 'string']])]
    #[OperationField(OperationId::StaffUpdate, name: 'groups', schema: ['type' => 'array', 'items' => ['type' => 'string']])]
    public function replaceGroups(array $codes): self
    {
        $this->assertStaffBuilderContext('replaceGroups');

        foreach ($codes as $index => $code) {
            if (! is_string($code)) {
                throw ValidationException::withMessages([
                    'groups.'.$index => ['Group codes must be strings.'],
                ]);
            }
        }

        $this->staffDraft['groups'] = array_values($codes);
        $this->markStructuredField('groups');

        return $this;
    }

    /**
     * Remove a single group membership from an existing staff member.
     *
     * This emits a `GlbGroupLink` row with `Action="DELETE"` in update payloads.
     *
     * Example:
     * `->removeGroup('OPS')`
     */
    #[OperationField(OperationId::StaffUpdate, name: 'groups_to_remove', repeatable: true)]
    public function removeGroup(string $code): self
    {
        $this->assertStaffBuilderContext('removeGroup');

        if ($this->staffIntent !== 'update') {
            throw new \Exception('removeGroup() is only supported for staff update() requests.');
        }

        if (! isset($this->staffDraft['groupsToRemove']) || ! is_array($this->staffDraft['groupsToRemove'])) {
            $this->staffDraft['groupsToRemove'] = [];
        }

        $this->staffDraft['groupsToRemove'][] = $code;
        $this->markStructuredField('groups_to_remove');

        return $this;
    }

    /**
     * Merge raw CargoWise payload attributes into the staff payload.
     *
     * Use this as an escape hatch for fields that do not have dedicated
     * fluent setter methods yet.
     *
     * Example:
     * `->withPayload(['CustomFieldX' => 'foo'])`
     */
    public function withPayload(array $payload): self
    {
        if ($this->target === DataTarget::Staff && in_array($this->staffIntent, ['create', 'update'], true)) {
            $this->staffDraft['attributes'] = array_replace_recursive(
                $this->staffDraft['attributes'] ?? [],
                $payload
            );

            return $this;
        }

        if ($this->target === DataTarget::OneOffQuote && $this->oneOffQuoteIntent === 'create') {
            $this->oneOffQuoteDraft['attributes'] = array_replace_recursive(
                $this->oneOffQuoteDraft['attributes'] ?? [],
                $payload
            );

            return $this;
        }

        throw new \Exception('withPayload() requires a supported builder context.');
    }

    /**
     * Compile and return the staff payload without sending the request.
     *
     * Useful for validation, logging, and approval workflows.
     *
     * Example:
     * `$payload = Cord::staff()->create()->code('OJ0')->toPayload();`
     */
    public function toPayload(): array
    {
        $this->syncFluentOneOffQuotePayload();
        $this->syncFluentStaffPayload();

        if ($this->target === DataTarget::OneOffQuote) {
            return $this->oneOffQuote;
        }

        return $this->staff;
    }

    public function schema(string|OperationId|null $operation = null): array
    {
        $resolvedOperation = $operation instanceof OperationId
            ? $operation
            : ($operation !== null ? OperationId::from($operation) : $this->operationRegistry()->detectCurrentOperation($this));

        if ($resolvedOperation === null) {
            throw new \Exception('schema() requires an operation id or a fully scoped builder context.');
        }

        return $this->operationRegistry()->schema($resolvedOperation);
    }

    public function fromStructured(string|OperationId $operation, array $payload): self
    {
        $definition = $this->operationRegistry()->definition($operation);
        $payload = $this->filterIgnoredStructuredFields($payload);

        $this->schemaValidator()->validate(
            $this->operationRegistry()->schema($definition->id),
            $payload,
            array_keys($this->structuredOverrides)
        );

        $this->bootstrapStructuredOperation($definition, $payload);

        foreach ($this->operationRegistry()->operationFields($definition->id) as $field) {
            if (! array_key_exists($field['name'], $payload)) {
                continue;
            }

            $this->applyStructuredField($field, $payload[$field['name']]);
        }

        return $this;
    }

    public function describe(): array
    {
        $operation = $this->operationRegistry()->detectCurrentOperation($this);
        if ($operation !== null) {
            return $this->schema($operation);
        }

        $resource = $this->describeResource();
        if ($resource !== null) {
            return [
                'resource' => $resource,
                'operations' => $this->operationRegistry()->operationsForResource($resource),
            ];
        }

        return [
            'resources' => $this->operationRegistry()->groupedOperationList(),
        ];
    }

    #[OperationField(OperationId::OrganizationEdiCommunicationAdd, name: 'edi_communication', required: true, builder: OrganizationEDICommunicationBuilder::class)]
    public function addEDICommunication(Closure $builder): self
    {
        $this->assertOrganizationUpdateContext('addEDICommunication');

        $ediBuilder = new OrganizationEDICommunicationBuilder;
        $builder($ediBuilder);
        $details = $ediBuilder->toArray();

        $requiredFields = ['module', 'purpose', 'direction', 'format', 'destination', 'transport'];
        $errors = [];
        foreach ($requiredFields as $field) {
            if (! isset($details[$field]) || trim((string) $details[$field]) === '') {
                $errors[$field] = ['The '.$field.' field is required.'];
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $this->currentOperation = OperationId::OrganizationEdiCommunicationAdd;
        $this->markStructuredField('edi_communication');

        $this->ediCommunication = [
            '_attributes' => ['Action' => 'INSERT'],
            'Module' => $details['module'],
            'MessagePurpose' => $details['purpose'],
            'CommsDirection' => $details['direction'],
            'CommunicationsTransport' => $details['transport'],
            'Destination' => $details['destination'],
            'FileFormat' => $details['format'],
            'ServerAddressSubject' => $details['subject'] ?? '',
            'PublishInternalMilestones' => $details['publishMilestones'] ?? 'false',
            'LocalPartyVanID' => $details['senderVAN'] ?? '',
            'RelatedPartyVanID' => $details['receiverVAN'] ?? '',
            'Filename' => $details['filename'] ?? '',
        ];

        return $this;
    }

    #[OperationField(OperationId::OrganizationContactAdd, name: 'contact', required: true, builder: OrganizationContactBuilder::class)]
    #[OperationField(OperationId::OrganizationCreate, name: 'contacts', repeatable: true, builder: OrganizationContactBuilder::class)]
    public function addContact(Closure $builder): self
    {
        $this->assertOrganizationWriteContext('addContact');

        $contactBuilder = new OrganizationContactBuilder;
        $builder($contactBuilder);
        $details = $contactBuilder->toArray();

        $errors = [];
        foreach (['name', 'email'] as $field) {
            if (! isset($details[$field]) || trim((string) $details[$field]) === '') {
                $errors[$field] = ['The '.$field.' field is required.'];
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $built = [
            '_attributes' => ['Action' => 'INSERT'],
            'IsActive' => $details['active'] ?? 'true',
            'ContactName' => $details['name'],
            'NotifyMode' => $details['notifyMode'] ?? 'EML',
            'Title' => $details['title'] ?? '',
            'Gender' => $details['gender'] ?? 'N',
            'Email' => $details['email'],
            'Language' => $details['language'] ?? 'EN',
            'Phone' => $details['phone'] ?? '',
            'Mobile' => $details['mobilePhone'] ?? '',
            'HomeWork' => $details['homePhone'] ?? '',
            'AttachmentType' => $details['attachmentType'] ?? 'PDF',
        ];

        if ($this->organizationIntent === 'create') {
            $this->organizationDraft['contacts'][] = $built;
        } else {
            $this->contact = $built;
            $this->currentOperation = OperationId::OrganizationContactAdd;
            $this->markStructuredField('contact');
        }

        return $this;
    }

    /**
     * A method to transfer EDI communication from one organization to another.
     */
    #[OperationField(
        OperationId::OrganizationEdiCommunicationTransfer,
        name: 'source_edi_communication',
        required: true,
        schema: [
            'type' => 'object',
            'properties' => [
                'PK' => ['type' => ['integer', 'string']],
            ],
            'required' => ['PK'],
            'additionalProperties' => true,
        ]
    )]
    public function transferEDICommunication(array $ediCommunication): self
    {
        $this->assertOrganizationUpdateContext('transferEDICommunication');
        $this->currentOperation = OperationId::OrganizationEdiCommunicationTransfer;
        $this->markStructuredField('source_edi_communication');

        // PK is already present in an EDICommunication collection. If it is not, it means that we have received something else...
        if (! isset($ediCommunication['PK'])) {
            throw new \Exception('Invalid EDI communication array proivded. Be sure to provide the array data of the EDICommunicationsMode array.');
        }

        $this->addActionRecursively($ediCommunication);
        $ediCommunication = array_merge(['_attributes' => ['Action' => 'INSERT']], $ediCommunication);

        // CW1 adds an @attributes to some tags. Remove it!
        $this->removeKeyRecursively($ediCommunication, '@attributes');

        // Below we do not want to insert.:
        if (isset($ediCommunication['MessageVAN'])) {
            unset($ediCommunication['MessageVAN']);
        }

        // Remove all values that are an empty array!
        $this->ediCommunication = collect($ediCommunication)->filter(function ($value) {
            return ! empty($value);
        })->all();

        return $this;
    }

    /**
     * A method to transfer a contact from one organization to another.
     */
    #[OperationField(
        OperationId::OrganizationDocumentTrackingTransfer,
        name: 'source_document_tracking',
        required: true,
        schema: [
            'type' => 'object',
            'properties' => [
                'PK' => ['type' => ['integer', 'string']],
            ],
            'required' => ['PK'],
            'additionalProperties' => true,
        ]
    )]
    public function transferDocumentTracking(array $jobRequiredDocument): self
    {
        $this->assertOrganizationUpdateContext('transferDocumentTracking');
        $this->currentOperation = OperationId::OrganizationDocumentTrackingTransfer;
        $this->markStructuredField('source_document_tracking');

        // PK is already present in an JobRequiredDocument collection. If it is not, it means that we have received something else...
        if (! isset($jobRequiredDocument['PK'])) {
            throw new \Exception('Invalid document tracking array proivded. Be sure to provide the array data of the JobRequiredDocument array.');
        }

        $this->addActionRecursively($jobRequiredDocument);
        $jobRequiredDocument = array_merge(['_attributes' => ['Action' => 'INSERT']], $jobRequiredDocument);

        // CW1 adds an @attributes to some tags. Remove it!
        $this->removeKeyRecursively($jobRequiredDocument, '@attributes');

        // Below we do not want to insert.:
        if (isset($jobRequiredDocument['RelatedCountry'])) {
            unset($jobRequiredDocument['RelatedCountry']);
        }

        if (isset($jobRequiredDocument['DocumentOwner'])) {
            unset($jobRequiredDocument['DocumentOwner']);
        }

        // Remove all values that are an empty array!
        $this->jobRequiredDocument = collect($jobRequiredDocument)->filter(function ($value) {
            return ! empty($value);
        })->all();

        return $this;
    }

    /**
     * A method to transfer a contact from one organization to another.
     */
    #[OperationField(
        OperationId::OrganizationContactTransfer,
        name: 'source_contact',
        required: true,
        schema: [
            'type' => 'object',
            'properties' => [
                'PK' => ['type' => ['integer', 'string']],
            ],
            'required' => ['PK'],
            'additionalProperties' => true,
        ]
    )]
    public function transferContact(array $contact): self
    {
        $this->assertOrganizationUpdateContext('transferContact');
        $this->currentOperation = OperationId::OrganizationContactTransfer;
        $this->markStructuredField('source_contact');

        // PK is already present in an OrgContact collection. If it is not, it means that we have received something else...
        if (! isset($contact['PK'])) {
            throw new \Exception('Invalid contact array proivded. Be sure to provide the array data of the OrgContact array.');
        }

        $this->addActionRecursively($contact);
        $contact = array_merge(['_attributes' => ['Action' => 'INSERT']], $contact);

        // CW1 adds an @attributes to some tags. Remove it!
        $this->removeKeyRecursively($contact, '@attributes');

        // Make sure that "Documents to Deliver" can be transferred (must be a "merge")
        if (isset($contact['OrgDocumentCollection'])) {
            $docsToDeliver = $contact['OrgDocumentCollection']['OrgDocument'] ?? [];
            // Check if $capabilities is an associative array or an array of key-value pairs
            if (! empty($docsToDeliver) && is_array($docsToDeliver) && array_keys($docsToDeliver) !== range(0, count($docsToDeliver) - 1)) {
                $contact['OrgDocumentCollection']['OrgDocument'] = [$contact['OrgDocumentCollection']['OrgDocument']]; // Convert to an array of one element
            }

            foreach ($contact['OrgDocumentCollection']['OrgDocument'] as $key => $docs) {
                if (isset($contact['OrgDocumentCollection']['OrgDocument'][$key]['MenuItem'])) {
                    // Action must be merge.
                    $contact['OrgDocumentCollection']['OrgDocument'][$key]['MenuItem']['_attributes'] = [
                        'Action' => 'MERGE',
                    ];

                    // Remove the StaffCode tag, as the staff codes is company specific.
                    unset($contact['OrgDocumentCollection']['OrgDocument'][$key]['MenuItem']['StaffCode']);
                    if (isset($contact['OrgDocumentCollection']['OrgDocument'][$key]['MenuItem']['StaffCodeExternal'])) {
                        unset($contact['OrgDocumentCollection']['OrgDocument'][$key]['MenuItem']['StaffCodeExternal']);
                    }

                }
            }
        }

        // We are not transferring below from contacts.
        $contact = Arr::except($contact, [
            'GlbGroupOrgContactLinkCollection',
            'OrgSecurityContactsCollection',
            'AddressOverride',
            'Nationality',
            'OrgAddress',
        ]);

        // Remove all values that are an empty array!
        $this->contact = collect($contact)->filter(function ($value) {
            return ! empty($value);
        })->all();

        return $this;
    }

    /**
     * A method to transfer an address from one organization to another.
     */
    #[OperationField(
        OperationId::OrganizationAddressTransfer,
        name: 'source_address',
        required: true,
        schema: [
            'type' => 'object',
            'properties' => [
                'PK' => ['type' => ['integer', 'string']],
            ],
            'required' => ['PK'],
            'additionalProperties' => true,
        ]
    )]
    public function transferAddress(array $address): self
    {
        $this->assertOrganizationUpdateContext('transferAddress');
        $this->currentOperation = OperationId::OrganizationAddressTransfer;
        $this->markStructuredField('source_address');

        // PK is already present in an OrgAddress collection. If it is not, it means that we have received something else...
        if (! isset($address['PK'])) {
            throw new \Exception('Invalid address array proivded. Be sure to provide the array data of the OrgAddress array.');
        }

        $this->addActionRecursively($address);
        $address = array_merge(['_attributes' => ['Action' => 'INSERT']], $address);

        // CW1 adds an @attributes to some tags. Remove it!
        $this->removeKeyRecursively($address, '@attributes');

        // Below are related to another table in CW1, so we need to set the action to "UPDATE":
        $address['RelatedPortCode']['_attributes'] = ['Action' => 'UPDATE'];
        $address['CountryCode']['_attributes'] = ['Action' => 'UPDATE'];

        // Remove all values that are an empty array!
        $this->address = collect($address)->filter(function ($value) {
            return ! empty($value);
        })->all();

        return $this;

    }

    #[OperationField(OperationId::OrganizationAddressAdd, name: 'address', required: true, builder: OrganizationAddressBuilder::class)]
    #[OperationField(OperationId::OrganizationCreate, name: 'addresses', repeatable: true, builder: OrganizationAddressBuilder::class)]
    public function addAddress(Closure $builder): self
    {
        $this->assertOrganizationWriteContext('addAddress');

        $addressBuilder = new OrganizationAddressBuilder;
        $builder($addressBuilder);
        $details = $addressBuilder->toArray();

        $errors = [];
        foreach (['code', 'addressOne', 'country', 'city'] as $field) {
            if (! isset($details[$field]) || trim((string) $details[$field]) === '') {
                $errors[$field] = ['The '.$field.' field is required.'];
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $capabilities = $details['capabilities'] ?? [];

        foreach ($capabilities as $key => $capability) {
            $capabilities[$key] = [
                '_attributes' => ['Action' => 'INSERT'],
                'AddressType' => $capability['AddressType'] ?? '',
                'IsMainAddress' => $capability['IsMainAddress'] ?? '',
            ];
        }

        $built = [
            '_attributes' => ['Action' => 'INSERT'],
            'IsActive' => $details['active'] ?? 'true',
            'Code' => $details['code'],
            'Address1' => $details['addressOne'],
            'Address2' => $details['addressTwo'] ?? '',
            'CountryCode' => [
                'Code' => $details['country'],
            ],
            'City' => $details['city'],
            'State' => $details['state'] ?? null,
            'PostCode' => $details['postcode'] ?? null,
            'RelatedPortCode' => [
                'Code' => $details['relatedPort'] ?? null,
            ],
            'Phone' => $details['phone'] ?? null,
            'Fax' => $details['fax'] ?? null,
            'Mobile' => $details['mobile'] ?? null,
            'Email' => $details['email'] ?? null,
            'FCLEquipmentNeeded' => $details['dropModeFCL'] ?? 'ASK',
            'LCLEquipmentNeeded' => $details['dropModeLCL'] ?? 'ASK',
            'AIREquipmentNeeded' => $details['dropModeAIR'] ?? 'ASK',
            'SuppressAddressValidationError' => 'true',
            'OrgAddressCapabilityCollection' => [
                'OrgAddressCapability' => count($capabilities) === 1 ? $capabilities[0] : $capabilities,
            ],
        ];

        if ($this->organizationIntent === 'create') {
            $this->organizationDraft['addresses'][] = $built;
        } else {
            $this->address = $built;
            $this->currentOperation = OperationId::OrganizationAddressAdd;
            $this->markStructuredField('address');
        }

        return $this;
    }

    /**
     * Determine if the request is for a shipment.
     */
    public function company(?string $code = null): self
    {
        $this->criteriaGroups = [];
        $this->targetKey = null;
        $this->requestType = RequestType::NativeCompanyRetrieval;
        $this->target = DataTarget::Organization;
        $this->currentOperation = OperationId::CompanyQuery;

        if ($code) {
            $this->targetKey = $code;
            $this->markStructuredField('code');
            $this->criteriaGroup([
                [
                    'Entity' => 'GlbCompany',
                    'FieldName' => 'Code',
                    'Value' => $code,
                ],
            ], type: 'Key');
        }

        return $this;
    }

    /**
     * Add criteriaGroup to the native query methods.
     */
    #[OperationField(
        OperationId::OrganizationQuery,
        name: 'criteria_groups',
        repeatable: true,
        schema: [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'criteria' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'entity' => ['type' => 'string'],
                            'field_name' => ['type' => 'string'],
                            'value' => [],
                        ],
                        'required' => ['entity', 'field_name', 'value'],
                    ],
                ],
                'type' => ['type' => 'string', 'enum' => ['Key', 'Partial']],
            ],
            'required' => ['criteria'],
        ]
    )]
    #[OperationField(
        OperationId::CompanyQuery,
        name: 'criteria_groups',
        repeatable: true,
        schema: [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'criteria' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'entity' => ['type' => 'string'],
                            'field_name' => ['type' => 'string'],
                            'value' => [],
                        ],
                        'required' => ['entity', 'field_name', 'value'],
                    ],
                ],
                'type' => ['type' => 'string', 'enum' => ['Key', 'Partial']],
            ],
            'required' => ['criteria'],
        ]
    )]
    public function criteriaGroup(array $criteria, string $type = 'Key'): self
    {

        if ($this->requestType !== RequestType::NativeOrganizationRetrieval && $this->requestType !== RequestType::NativeCompanyRetrieval) {
            throw new \Exception('You must call a native query request method before calling the criteraGroup() method. This could for example be organization() or company()');
        }

        $criteriaGroup = [
            'CriteriaGroup' => [
                '_attributes' => ['Type' => $type],
                'Criteria' => [],
            ],
        ];

        foreach ($criteria as $item) {
            $criteriaGroup['CriteriaGroup']['Criteria'][] = [
                '_attributes' => [
                    'Entity' => $item['Entity'],
                    'FieldName' => $item['FieldName'],
                ],
                '_value' => $item['Value'],
            ];
        }

        array_push($this->criteriaGroups, $criteriaGroup);
        $this->markStructuredField('criteria_groups');

        return $this;
    }

    /**
     * Determine if the request is for a brokerage job.
     */
    public function custom(string $custom): self
    {
        $this->targetKey = $custom;
        $this->target = DataTarget::Custom;
        $this->requestType = RequestType::UniversalShipmentRequest;
        $this->currentOperation = OperationId::CustomGet;
        $this->markStructuredField('key');

        return $this;
    }

    /**
     * Determine if the request should include documents.
     */
    public function withDocuments(): self
    {
        $this->requestType = RequestType::UniversalDocumentRequest;
        $this->currentOperation = match ($this->target) {
            DataTarget::Shipment => OperationId::ShipmentDocumentsGet,
            DataTarget::Booking => OperationId::BookingDocumentsGet,
            DataTarget::Custom => OperationId::CustomDocumentsGet,
            DataTarget::Receiveable => OperationId::ReceivableDocumentsGet,
            default => $this->currentOperation,
        };

        return $this;
    }

    #[OperationField(OperationId::ShipmentDocumentAdd, name: 'document', required: true)]
    #[OperationField(OperationId::BookingDocumentAdd, name: 'document', required: true)]
    #[OperationField(OperationId::CustomDocumentAdd, name: 'document', required: true)]
    public function addDocument(string $file_contents, string $name, string $type, string $description = '', bool $isPublished = false): self
    {
        if ($this->target === DataTarget::OneOffQuote && $this->oneOffQuoteIntent === 'create') {
            if (! isset($this->oneOffQuoteDraft['attachedDocuments']) || ! is_array($this->oneOffQuoteDraft['attachedDocuments'])) {
                $this->oneOffQuoteDraft['attachedDocuments'] = [];
            }

            $document = [
                'fileName' => $name,
                'imageData' => $file_contents,
                'typeCode' => $type,
                'isPublished' => $isPublished,
            ];

            if ($description !== '') {
                $document['attributes'] = [
                    'Type' => [
                        'Description' => $description,
                    ],
                ];
            }

            $this->oneOffQuoteDraft['attachedDocuments'][] = $document;
            $this->markStructuredField('attached_documents');

            return $this;
        }

        $this->requestType = RequestType::UniversalEvent;

        $this->addEvent(date('c'), 'DIM', 'Document imported automatically from XML');

        $this->document = [
            'AttachedDocumentCollection' => [
                'AttachedDocument' => [
                    'FileName' => $name,
                    'ImageData' => $file_contents,
                    'Type' => [
                        'Code' => $type,
                        'Description' => $description,
                    ],
                    'IsPublished' => var_export($isPublished, true), // cast to string,
                ],
            ],
        ];
        $this->currentOperation = match ($this->target) {
            DataTarget::Shipment => OperationId::ShipmentDocumentAdd,
            DataTarget::Booking => OperationId::BookingDocumentAdd,
            DataTarget::Custom => OperationId::CustomDocumentAdd,
            default => $this->currentOperation,
        };
        $this->markStructuredField('document');

        return $this;
    }

    /**
     * Add an event to the request.
     */
    #[OperationField(OperationId::ShipmentEventAdd, name: 'event', required: true)]
    #[OperationField(OperationId::BookingEventAdd, name: 'event', required: true)]
    #[OperationField(OperationId::CustomEventAdd, name: 'event', required: true)]
    public function addEvent(string $date, string $type, string $reference = 'Automatic event from Cord', bool $isEstimate = false): self
    {
        $this->requestType = RequestType::UniversalEvent;

        if (! $date) {
            $date = date('c');
        }
        $date = date('c', strtotime($date));

        $this->event = [
            'EventTime' => $date,
            'EventType' => $type,
            'EventReference' => $reference,
            'IsEstimate' => var_export($isEstimate, true), // cast to string
        ];
        $this->currentOperation = match ($this->target) {
            DataTarget::Shipment => OperationId::ShipmentEventAdd,
            DataTarget::Booking => OperationId::BookingEventAdd,
            DataTarget::Custom => OperationId::CustomEventAdd,
            default => $this->currentOperation,
        };
        $this->markStructuredField('event');

        return $this;
    }

    /**
     * Add filter(s) to the request.
     */
    #[OperationField(
        OperationId::ShipmentDocumentsGet,
        name: 'filters',
        repeatable: true,
        schema: [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string'],
                'value' => [],
            ],
            'required' => ['type', 'value'],
        ]
    )]
    #[OperationField(
        OperationId::BookingDocumentsGet,
        name: 'filters',
        repeatable: true,
        schema: [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string'],
                'value' => [],
            ],
            'required' => ['type', 'value'],
        ]
    )]
    #[OperationField(
        OperationId::CustomDocumentsGet,
        name: 'filters',
        repeatable: true,
        schema: [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string'],
                'value' => [],
            ],
            'required' => ['type', 'value'],
        ]
    )]
    #[OperationField(
        OperationId::ReceivableDocumentsGet,
        name: 'filters',
        repeatable: true,
        schema: [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'type' => ['type' => 'string'],
                'value' => [],
            ],
            'required' => ['type', 'value'],
        ]
    )]
    public function filter(string $type, mixed $value): self
    {
        // Every time this method is called, it will add a new filter to the filters array.
        $this->filters[] = [
            'Type' => $type,
            'Value' => $value,
        ];
        $this->markStructuredField('filters');

        return $this;
    }

    /**
     * Get the XML object.
     */
    public function run(): mixed
    {
        $this->syncFluentOneOffQuotePayload();
        $this->syncFluentStaffPayload();
        $this->xml = $this->buildRequest()->xml();

        return $this->fetch();
    }

    /**
     * Get the request as XML.
     */
    public function inspect(): string
    {
        $this->syncFluentOneOffQuotePayload();
        $this->syncFluentStaffPayload();
        $this->checkForErrors();
        $this->xml = $this->buildRequest()->xml();

        return $this->xml;
    }

    /**
     * Determine if the response should be returned as XML.
     */
    public function toXml(): self
    {
        $this->asXml = true;

        return $this;
    }

    protected function buildRequest(): RequestInterface
    {
        $this->syncFluentOneOffQuotePayload();
        $this->syncFluentStaffPayload();

        return match ($this->requestType) {
            RequestType::UniversalShipmentRequest => new UniversalShipmentRequest($this),
            RequestType::UniversalDocumentRequest => new UniversalDocumentRequest($this),
            RequestType::UniversalEvent => new UniversalEvent($this),
            RequestType::NativeOrganizationRetrieval => new NativeOrganizationRetrieval($this),
            RequestType::NativeOrganizationUpdate => new NativeOrganizationUpdate($this),
            RequestType::NativeOrganizationCreation => new NativeOrganizationCreation($this),
            RequestType::NativeCompanyRetrieval => new NativeCompanyRetrieval($this),
            RequestType::NativeStaffCreation => new NativeStaffCreation($this),
            RequestType::NativeStaffUpdate => new NativeStaffUpdate($this),
        };
    }

    private function checkForErrors()
    {
        $this->syncFluentOneOffQuotePayload();
        $this->syncFluentStaffPayload();

        if ($this->target === DataTarget::OneOffQuote && $this->oneOffQuoteIntent === 'create') {
            return;
        }

        if ($this->target === DataTarget::Organization && $this->organizationIntent === 'create') {
            $this->validateOrganizationCreateDraft();

            return;
        }

        if (! $this->targetKey && ! in_array($this->requestType, [RequestType::NativeOrganizationRetrieval, RequestType::NativeCompanyRetrieval])) {
            throw new \Exception('You haven\'t set any target key. This is usually the shipment number, customs declaration number or booking number.');
        }
    }

    private function validateOrganizationCreateDraft(): void
    {
        $errors = [];

        if (! $this->targetKey || trim($this->targetKey) === '') {
            $errors['code'] = ['The code field is required.'];
        }

        if (! isset($this->organizationDraft['fullName']) || trim($this->organizationDraft['fullName']) === '') {
            $errors['full_name'] = ['The full_name field is required.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function flattenResponse(array $response, string $key)
    {
        return tap($response, function (&$items) use ($key) {
            // Check if there's only one result with the specified key
            if (count($items) === 1 && isset($items[$key])) {
                $items = [$items[$key]][0];
            } else {
                // Process each item for multiple results
                $items = collect($items)
                    ->map(function ($item) use ($key) {
                        return $item[$key] ?? $item;
                    })->all();
            }
        });
    }

    protected function fetch(): mixed
    {
        $this->checkForErrors();
        $this->setClient();

        $response = $this->client->send('POST', $this->config['url'], [
            'body' => $this->xml,
        ])->throw()->body();

        $xmlResponse = $response;

        // XML to JSON
        $response = json_decode(json_encode(simplexml_load_string($response)), true);

        // If eAdapter response is not successful, throw exception:
        if ($response['Status'] == 'ERR') {
            // If client expects json, return json:
            if (RequestFacade::wantsJson()) {
                $status = match ($response['ProcessingLog']) {
                    'Warning - There is no business object matching the criteria.' => 404,
                    default => 500,
                };

                return new JsonResponse(['error' => $response['ProcessingLog']], $status);
            }
            throw new \Exception($response['ProcessingLog']);
        }

        if ($this->asXml) {
            $xmlResponse = simplexml_load_string($xmlResponse);

            // We need to return the first subelement of the Data element, because the Data element is an array.
            return $xmlResponse->Data->children()[0];
        }

        Log::debug('Request Type is', [
            'type' => $this->requestType,
            'targetKey' => $this->targetKey,
            'target' => $this->target,
            'company' => $this->company,
            'response' => $response,
            'XML' => $this->xml,
        ]);

        // If eAdapter response is successful, return data:
        // Handling different request types
        return match ($this->requestType) {
            RequestType::NativeOrganizationRetrieval => $this->flattenResponse($response['Data']['Native']['Body']['Organization'], 'OrgHeader'),
            RequestType::NativeCompanyRetrieval => $this->flattenResponse($response['Data']['Native']['Body']['Company'], 'GlbCompany'),

            // Future implementations for shipment, custom, and booking can be added here
            // RequestType::UniversalShipmentRequest, RequestType::Custom, RequestType::Booking => {
            //     // Implement specific handling here
            // },

            default => $response['Data'],
        };
    }

    private function addActionRecursively(&$arr, $attribute = 'INSERT')
    {
        // Define a function to check if an array is a "holder of arrays"
        $isHolderOfArrays = function ($item) {
            foreach ($item as $value) {
                if (! is_array($value)) {
                    return false; // Found a non-array item, so it's not just a holder of arrays
                }
            }

            return true; // Every item is an array, so it's a holder of arrays
        };

        if (is_array($arr)) {
            foreach ($arr as $key => &$value) {
                // Recursively apply the function to sub-arrays
                if (is_array($value)) {
                    $this->addActionRecursively($value);

                    // After recursion, add '_attributes' only to "real" arrays, not holders
                    if (! $isHolderOfArrays($value)) {
                        $value = array_merge(['_attributes' => ['Action' => $attribute]], $value);
                    }
                }
            }
            unset($value); // Unset the reference to prevent unexpected behavior later
        }
    }

    private function removeKeyRecursively(&$array, $keyToRemove)
    {
        if (is_array($array)) {
            foreach ($array as $key => &$value) {
                if ($key === $keyToRemove) {
                    unset($array[$key]);
                } elseif (is_array($value)) {
                    $this->removeKeyRecursively($value, $keyToRemove);
                }
            }
        }
    }

    private function setConnectionConfig(?array $config): void
    {
        $this->config = $config;

        if (! is_array($this->config)) {
            throw new \Exception('The selected Cord configuration could not be found.');
        }

        $this->senderId = $this->config['sender_id'] ?? null;
        $this->recipientId = $this->config['recipient_id'] ?? 'Cord';
        $this->enableCodeMapping = filter_var($this->config['enable_code_mapping'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;

        $this->assertConnectionConfig();
    }

    public function resolveSenderId(): string
    {
        if ($this->senderId) {
            return $this->senderId;
        }

        $enterpriseId = $this->resolveEnterpriseId();
        $serverId = $this->resolveServerId();

        if (! $enterpriseId || ! $serverId || ! $this->company) {
            throw new \Exception('Sender ID could not be derived. Set withCompany() and use a CargoWise URL like https://demo1trnservices.example.invalid/eAdaptor, or override with withEnterprise(), withServer(), or withSenderId().');
        }

        return strtoupper($enterpriseId.$serverId.$this->company);
    }

    public function resolveRecipientId(): string
    {
        return $this->recipientId ?: 'Cord';
    }

    public function resolveEnterpriseId(): ?string
    {
        if ($this->enterprise) {
            return strtoupper($this->enterprise);
        }

        return $this->inferSystemContextFromUrl()['enterprise'] ?? null;
    }

    public function resolveServerId(): ?string
    {
        if ($this->server) {
            return strtoupper($this->server);
        }

        return $this->inferSystemContextFromUrl()['server'] ?? null;
    }

    public function nativeHeader(): array
    {
        $enterpriseId = $this->resolveEnterpriseId();
        $serverId = $this->resolveServerId();

        if (! $this->company) {
            throw new \Exception('Company code must be provided for native write requests. Call withCompany() before sending the request.');
        }

        if (! $enterpriseId || ! $serverId) {
            throw new \Exception('EnterpriseID and ServerID could not be derived from the configured URL. Use a CargoWise URL like https://demo1trnservices.example.invalid/eAdaptor or override with withEnterprise() and withServer().');
        }

        return [
            'DataContext' => [
                'CodesMappedToTarget' => $this->enableCodeMapping ? 'true' : 'false',
                'Company' => [
                    'Code' => $this->company,
                ],
                'EnterpriseID' => $enterpriseId,
                'ServerID' => $serverId,
            ],
        ];
    }

    private function buildCompleteStaffPayload(array $staffDetails): array
    {
        $payload = [
            '_attributes' => ['Action' => $staffDetails['action'] ?? 'Insert'],
            'Code' => $staffDetails['code'],
            'IsActive' => $this->normalizeBoolean($staffDetails['active'] ?? true),
            'LoginName' => $staffDetails['loginName'],
            'Password' => $staffDetails['password'],
            'IsSalesRep' => $this->normalizeBoolean($staffDetails['isSalesRep'] ?? false),
            'IsController' => $this->normalizeBoolean($staffDetails['isController'] ?? false),
            'IsSystemAccount' => $this->normalizeBoolean($staffDetails['isSystemAccount'] ?? false),
            'IsDeveloper' => $this->normalizeBoolean($staffDetails['isDeveloper'] ?? false),
            'IsBackupOperator' => $this->normalizeBoolean($staffDetails['isBackupOperator'] ?? false),
            'IsReadOnlyDBUser' => $this->normalizeBoolean($staffDetails['isReadOnlyDbUser'] ?? false),
            'EmploymentBasis' => $staffDetails['employmentBasis'] ?? '',
            'NameTitle' => $staffDetails['nameTitle'] ?? '',
            'FullName' => $staffDetails['fullName'],
            'NameSuffix' => $staffDetails['nameSuffix'] ?? '',
            'FriendlyName' => $staffDetails['friendlyName'] ?? $staffDetails['fullName'],
            'UserAddress1' => $staffDetails['addressOne'] ?? '',
            'UserAddress2' => $staffDetails['addressTwo'] ?? '',
            'City' => $staffDetails['city'] ?? '',
            'Postcode' => $staffDetails['postcode'] ?? '',
            'Title' => $staffDetails['title'] ?? '',
            'WorkPhone' => $staffDetails['workPhone'] ?? '',
            'PublishWorkPhone' => $this->normalizeBoolean($staffDetails['publishWorkPhone'] ?? true),
            'Pager' => $staffDetails['pager'] ?? '',
            'FaxNum' => $staffDetails['faxNumber'] ?? '',
            'PublishFaxNum' => $this->normalizeBoolean($staffDetails['publishFaxNumber'] ?? true),
            'EmailAddress' => $staffDetails['email'] ?? '',
            'PublishEmailAddress' => $this->normalizeBoolean($staffDetails['publishEmailAddress'] ?? true),
            'GlbWorkTime' => $this->normalizeStaffWorkingHours(
                $staffDetails['workingHours'] ?? [],
                $staffDetails['workTimeAction'] ?? 'Insert',
            ),
            'EftWages' => $this->normalizeBoolean($staffDetails['eftWages'] ?? false),
            'WagesBankName' => $staffDetails['wagesBankName'] ?? '',
            'WagesBankAccount' => $staffDetails['wagesBankAccount'] ?? '',
            'WagesBankBsb' => $staffDetails['wagesBankBsb'] ?? '',
            'WagesBankSwift' => $staffDetails['wagesBankSwift'] ?? '',
            'UserSignature' => $staffDetails['signature'] ?? '',
            'DueBack' => $staffDetails['dueBack'] ?? '',
            'OutOnTask' => $staffDetails['outOnTask'] ?? '',
            'PersonalEDIMailBox' => $staffDetails['personalEdiMailBox'] ?? '',
            'BrokerID' => $staffDetails['brokerId'] ?? '',
            'BrokerPassword' => $staffDetails['brokerPassword'] ?? '',
            'BrokerWorkingPassword' => $staffDetails['brokerWorkingPassword'] ?? '',
            'BrokerPasswordStatus' => $staffDetails['brokerPasswordStatus'] ?? '',
            'NationalityCode' => $staffDetails['nationalityCode'] ?? '',
            'Passport' => $staffDetails['passport'] ?? '',
            'IsInTrainingMode' => $this->normalizeBoolean($staffDetails['isInTrainingMode'] ?? false),
            'ChangePasswordAtNextLogin' => $this->normalizeBoolean($staffDetails['changePasswordAtNextLogin'] ?? true),
            'LockoutDateTime' => $staffDetails['lockoutDateTime'] ?? '',
            'PasswordNeverChanges' => $this->normalizeBoolean($staffDetails['passwordNeverChanges'] ?? false),
            'NextReviewDate' => $staffDetails['nextReviewDate'] ?? '',
            'IsResource' => $this->normalizeBoolean($staffDetails['isResource'] ?? false),
            'ResourceType' => $staffDetails['resourceType'] ?? '',
            'SecurityCardNumber' => $staffDetails['securityCardNumber'] ?? '',
            'EnterpriseCertificationID' => $staffDetails['enterpriseCertificationId'] ?? '',
            'LastActivityDate' => $staffDetails['lastActivityDate'] ?? '',
            'CommissionBasis' => $staffDetails['commissionBasis'] ?? '',
            'NewClientCommissionRate' => $staffDetails['newClientCommissionRate'] ?? '0.0',
            'EstablishedClientCommissionRate' => $staffDetails['establishedClientCommissionRate'] ?? '0.0000',
            'CommissionMinimumEarning' => $staffDetails['commissionMinimumEarning'] ?? '0.0000',
            'NextOfKinRelationship' => $staffDetails['nextOfKinRelationship'] ?? '',
            'EmergencyContactRelationship' => $staffDetails['emergencyContactRelationship'] ?? '',
            'LastPasswordAttemptDateTime_UTC' => $staffDetails['lastPasswordAttemptDateTimeUtc'] ?? '',
            'LockoutDateTime_UTC' => $staffDetails['lockoutDateTimeUtc'] ?? '',
            'ProfilePhoto' => $staffDetails['profilePhoto'] ?? '',
            'IsActivityLogged' => $this->normalizeBoolean($staffDetails['isActivityLogged'] ?? false),
            'ActiveDirectoryObjectGuid' => $staffDetails['activeDirectoryObjectGuid'] ?? '',
            'HomeBranch' => $this->normalizeStaffReference($staffDetails['homeBranch'], 'GlbBranch'),
            'HomeDepartment' => $this->normalizeStaffReference($staffDetails['homeDepartment'], 'GlbDepartment'),
            'CountryCode' => $this->normalizeStaffReference($staffDetails['country'], 'RefCountry'),
        ];

        $groups = $this->normalizeStaffGroups($staffDetails['groups'] ?? []);
        if (! empty($groups)) {
            $payload['GlbGroupLinkCollection'] = [
                'GlbGroupLink' => count($groups) === 1 ? $groups[0] : $groups,
            ];
        }

        $payload = array_replace_recursive($payload, $staffDetails['attributes'] ?? []);
        $payload['IsOperational'] = 'true';

        return $payload;
    }

    private function buildSparseStaffPayload(array $staffDetails, string $code): array
    {
        $payload = [
            '_attributes' => ['Action' => $staffDetails['action'] ?? 'UPDATE'],
            'Code' => $code,
        ];

        $this->setStaffValueIfProvided($payload, $staffDetails, 'active', 'IsActive', fn ($value) => $this->normalizeBoolean($value));
        $this->setStaffValueIfProvided($payload, $staffDetails, 'loginName', 'LoginName');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'password', 'Password');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'isSalesRep', 'IsSalesRep', fn ($value) => $this->normalizeBoolean($value));
        $this->setStaffValueIfProvided($payload, $staffDetails, 'isController', 'IsController', fn ($value) => $this->normalizeBoolean($value));
        $this->setStaffValueIfProvided($payload, $staffDetails, 'isSystemAccount', 'IsSystemAccount', fn ($value) => $this->normalizeBoolean($value));
        $this->setStaffValueIfProvided($payload, $staffDetails, 'isDeveloper', 'IsDeveloper', fn ($value) => $this->normalizeBoolean($value));
        $this->setStaffValueIfProvided($payload, $staffDetails, 'isBackupOperator', 'IsBackupOperator', fn ($value) => $this->normalizeBoolean($value));
        $this->setStaffValueIfProvided($payload, $staffDetails, 'isReadOnlyDbUser', 'IsReadOnlyDBUser', fn ($value) => $this->normalizeBoolean($value));
        $this->setStaffValueIfProvided($payload, $staffDetails, 'employmentBasis', 'EmploymentBasis');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'nameTitle', 'NameTitle');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'fullName', 'FullName');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'nameSuffix', 'NameSuffix');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'friendlyName', 'FriendlyName');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'addressOne', 'UserAddress1');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'addressTwo', 'UserAddress2');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'city', 'City');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'postcode', 'Postcode');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'title', 'Title');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'workPhone', 'WorkPhone');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'publishWorkPhone', 'PublishWorkPhone', fn ($value) => $this->normalizeBoolean($value));
        $this->setStaffValueIfProvided($payload, $staffDetails, 'pager', 'Pager');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'faxNumber', 'FaxNum');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'publishFaxNumber', 'PublishFaxNum', fn ($value) => $this->normalizeBoolean($value));
        $this->setStaffValueIfProvided($payload, $staffDetails, 'email', 'EmailAddress');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'publishEmailAddress', 'PublishEmailAddress', fn ($value) => $this->normalizeBoolean($value));
        $this->setStaffValueIfProvided($payload, $staffDetails, 'eftWages', 'EftWages', fn ($value) => $this->normalizeBoolean($value));
        $this->setStaffValueIfProvided($payload, $staffDetails, 'wagesBankName', 'WagesBankName');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'wagesBankAccount', 'WagesBankAccount');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'wagesBankBsb', 'WagesBankBsb');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'wagesBankSwift', 'WagesBankSwift');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'signature', 'UserSignature');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'dueBack', 'DueBack');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'outOnTask', 'OutOnTask');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'personalEdiMailBox', 'PersonalEDIMailBox');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'brokerId', 'BrokerID');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'brokerPassword', 'BrokerPassword');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'brokerWorkingPassword', 'BrokerWorkingPassword');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'brokerPasswordStatus', 'BrokerPasswordStatus');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'nationalityCode', 'NationalityCode');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'passport', 'Passport');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'isInTrainingMode', 'IsInTrainingMode', fn ($value) => $this->normalizeBoolean($value));
        $this->setStaffValueIfProvided($payload, $staffDetails, 'changePasswordAtNextLogin', 'ChangePasswordAtNextLogin', fn ($value) => $this->normalizeBoolean($value));
        $this->setStaffValueIfProvided($payload, $staffDetails, 'lockoutDateTime', 'LockoutDateTime');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'passwordNeverChanges', 'PasswordNeverChanges', fn ($value) => $this->normalizeBoolean($value));
        $this->setStaffValueIfProvided($payload, $staffDetails, 'nextReviewDate', 'NextReviewDate');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'isResource', 'IsResource', fn ($value) => $this->normalizeBoolean($value));
        $this->setStaffValueIfProvided($payload, $staffDetails, 'resourceType', 'ResourceType');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'securityCardNumber', 'SecurityCardNumber');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'enterpriseCertificationId', 'EnterpriseCertificationID');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'lastActivityDate', 'LastActivityDate');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'commissionBasis', 'CommissionBasis');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'newClientCommissionRate', 'NewClientCommissionRate');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'establishedClientCommissionRate', 'EstablishedClientCommissionRate');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'commissionMinimumEarning', 'CommissionMinimumEarning');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'nextOfKinRelationship', 'NextOfKinRelationship');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'emergencyContactRelationship', 'EmergencyContactRelationship');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'lastPasswordAttemptDateTimeUtc', 'LastPasswordAttemptDateTime_UTC');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'lockoutDateTimeUtc', 'LockoutDateTime_UTC');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'profilePhoto', 'ProfilePhoto');
        $this->setStaffValueIfProvided($payload, $staffDetails, 'isActivityLogged', 'IsActivityLogged', fn ($value) => $this->normalizeBoolean($value));
        $this->setStaffValueIfProvided($payload, $staffDetails, 'activeDirectoryObjectGuid', 'ActiveDirectoryObjectGuid');

        if (array_key_exists('workingHours', $staffDetails)) {
            $payload['GlbWorkTime'] = $this->normalizeStaffWorkingHours(
                $staffDetails['workingHours'] ?? [],
                $staffDetails['workTimeAction'] ?? 'Update',
            );
        }

        if (array_key_exists('homeBranch', $staffDetails)) {
            $payload['HomeBranch'] = $this->normalizeStaffReference($staffDetails['homeBranch'], 'GlbBranch');
        }

        if (array_key_exists('homeDepartment', $staffDetails)) {
            $payload['HomeDepartment'] = $this->normalizeStaffReference($staffDetails['homeDepartment'], 'GlbDepartment');
        }

        if (array_key_exists('country', $staffDetails)) {
            $payload['CountryCode'] = $this->normalizeStaffReference($staffDetails['country'], 'RefCountry');
        }

        $groupsToMerge = [];
        if (array_key_exists('groups', $staffDetails)) {
            $groupsToMerge = $this->normalizeStaffGroups($staffDetails['groups'] ?? []);
        }

        $groupsToDelete = [];
        if (array_key_exists('groupsToRemove', $staffDetails)) {
            $groupsToDelete = $this->normalizeStaffGroups($staffDetails['groupsToRemove'] ?? [], 'DELETE');
        }

        $groups = array_values(array_merge($groupsToMerge, $groupsToDelete));
        if ($groups !== []) {
            $payload['GlbGroupLinkCollection'] = [
                'GlbGroupLink' => count($groups) === 1 ? $groups[0] : $groups,
            ];
        }

        return array_replace_recursive($payload, $staffDetails['attributes'] ?? []);
    }

    private function setOneOffQuoteTypedAddress(string $type, Closure $builder): self
    {
        $this->assertOneOffQuoteBuilderContext($type.'Address');

        $addressBuilder = new OneOffQuoteAddressBuilder;
        $builder($addressBuilder);

        if (! isset($this->oneOffQuoteDraft['addresses']) || ! is_array($this->oneOffQuoteDraft['addresses'])) {
            $this->oneOffQuoteDraft['addresses'] = [];
        }

        $this->oneOffQuoteDraft['addresses'][$type] = $addressBuilder->toArray();
        $this->markStructuredField(match ($type) {
            'client' => 'client_address',
            'pickup' => 'pickup_address',
            'delivery' => 'delivery_address',
            default => $type.'_address',
        });

        return $this;
    }

    private function syncFluentOneOffQuotePayload(): void
    {
        if ($this->target !== DataTarget::OneOffQuote || ! $this->oneOffQuoteIntent) {
            return;
        }

        if ($this->oneOffQuoteIntent !== 'create') {
            return;
        }

        $this->validateFluentOneOffQuoteCreatePayload($this->oneOffQuoteDraft);

        $this->requestType = RequestType::UniversalShipmentRequest;
        $this->target = DataTarget::OneOffQuote;
        $this->targetKey = $this->targetKey ?? '';
        $this->oneOffQuote = $this->buildOneOffQuotePayload($this->oneOffQuoteDraft);
    }

    private function validateFluentOneOffQuoteCreatePayload(array $payload): void
    {
        $errors = [];

        if (! is_string($this->company) || trim($this->company) === '') {
            $errors['company'] = ['The company field is required.'];
        }

        foreach (['branch', 'department'] as $requiredField) {
            $value = $payload[$requiredField] ?? null;

            if (! is_string($value) || trim($value) === '') {
                $errors[$requiredField] = ['The '.$requiredField.' field is required.'];
            }
        }

        foreach (['transportMode', 'portOfOrigin', 'portOfDestination'] as $requiredField) {
            $value = $payload[$requiredField]['code'] ?? null;

            if (! is_string($value) || trim($value) === '') {
                $errors[$requiredField] = ['The '.$requiredField.' field is required.'];
            }
        }

        $addressLabels = [
            'client' => 'addresses.client',
            'pickup' => 'addresses.pickup',
            'delivery' => 'addresses.delivery',
        ];

        foreach ($addressLabels as $addressKey => $errorPrefix) {
            if (! isset($payload['addresses'][$addressKey]) || ! is_array($payload['addresses'][$addressKey])) {
                continue;
            }

            $address = $payload['addresses'][$addressKey];
            foreach (['address1', 'city', 'countryCode'] as $requiredField) {
                $value = $address[$requiredField] ?? null;
                if (! is_string($value) || trim($value) === '') {
                    $errors[$errorPrefix.'.'.$requiredField] = ['The '.$requiredField.' field is required.'];
                }
            }
        }

        if (isset($payload['chargeLines']) && is_array($payload['chargeLines'])) {
            foreach ($payload['chargeLines'] as $index => $chargeLine) {
                foreach (['chargeCode', 'description'] as $requiredField) {
                    $value = $chargeLine[$requiredField] ?? null;
                    if (! is_string($value) || trim($value) === '') {
                        $errors['chargeLines.'.$index.'.'.$requiredField] = ['The '.$requiredField.' field is required.'];
                    }
                }
            }
        }

        if (isset($payload['attachedDocuments']) && is_array($payload['attachedDocuments'])) {
            foreach ($payload['attachedDocuments'] as $index => $document) {
                foreach (['fileName', 'imageData', 'typeCode'] as $requiredField) {
                    $value = $document[$requiredField] ?? null;
                    if (! is_string($value) || trim($value) === '') {
                        $errors['attachedDocuments.'.$index.'.'.$requiredField] = ['The '.$requiredField.' field is required.'];
                    }
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function buildOneOffQuotePayload(array $quoteDetails): array
    {
        $payload = [];

        if (isset($quoteDetails['transportMode'])) {
            $payload['TransportMode'] = [
                'Code' => $quoteDetails['transportMode']['code'],
            ];
        }

        if (isset($quoteDetails['portOfOrigin'])) {
            $payload['PortOfOrigin'] = [
                'Code' => $quoteDetails['portOfOrigin']['code'],
            ];
        }

        if (isset($quoteDetails['portOfDestination'])) {
            $payload['PortOfDestination'] = [
                'Code' => $quoteDetails['portOfDestination']['code'],
            ];
        }

        if (isset($quoteDetails['serviceLevel'])) {
            $payload['ServiceLevel'] = [
                'Code' => $quoteDetails['serviceLevel']['code'],
            ];
        }

        if (isset($quoteDetails['incoterm'])) {
            $payload['ShipmentIncoTerm'] = [
                'Code' => $quoteDetails['incoterm']['code'],
            ];
        }

        if (isset($quoteDetails['totalWeight'])) {
            $payload['TotalWeight'] = (string) $quoteDetails['totalWeight']['value'];
            $payload['TotalWeightUnit'] = [
                'Code' => $quoteDetails['totalWeight']['unitCode'],
            ];
        }

        if (isset($quoteDetails['totalVolume'])) {
            $payload['TotalVolume'] = (string) $quoteDetails['totalVolume']['value'];
            $payload['TotalVolumeUnit'] = [
                'Code' => $quoteDetails['totalVolume']['unitCode'],
            ];
        }

        if (isset($quoteDetails['goodsValue'])) {
            $payload['GoodsValue'] = (string) $quoteDetails['goodsValue']['amount'];
            $payload['GoodsValueCurrency'] = [
                'Code' => $quoteDetails['goodsValue']['currencyCode'],
            ];
        }

        if (array_key_exists('additionalTerms', $quoteDetails)) {
            $payload['AdditionalTerms'] = $quoteDetails['additionalTerms'];
        }

        if (array_key_exists('isDomesticFreight', $quoteDetails)) {
            $payload['IsDomesticFreight'] = $this->normalizeBoolean((bool) $quoteDetails['isDomesticFreight']);
        }

        if (isset($quoteDetails['branch'])) {
            $payload['JobCosting']['Branch'] = [
                'Code' => $quoteDetails['branch'],
            ];
        }

        if (isset($quoteDetails['department'])) {
            $payload['JobCosting']['Department'] = [
                'Code' => $quoteDetails['department'],
            ];
        }

        if (isset($quoteDetails['chargeLines']) && is_array($quoteDetails['chargeLines']) && $quoteDetails['chargeLines'] !== []) {
            $lineDefaults = [
                'branch' => $quoteDetails['branch'] ?? null,
                'department' => $quoteDetails['department'] ?? null,
                'currencyCode' => $quoteDetails['goodsValue']['currencyCode'] ?? null,
            ];

            $chargeLines = array_map(
                fn (array $line) => $this->buildOneOffQuoteChargeLinePayload($line, $lineDefaults),
                $quoteDetails['chargeLines']
            );

            $payload['JobCosting']['ChargeLineCollection'] = [
                'ChargeLine' => count($chargeLines) === 1 ? $chargeLines[0] : $chargeLines,
            ];
        }

        if (isset($quoteDetails['attachedDocuments']) && is_array($quoteDetails['attachedDocuments']) && $quoteDetails['attachedDocuments'] !== []) {
            $documents = array_map(
                fn (array $document) => $this->buildOneOffQuoteAttachedDocumentPayload($document),
                $quoteDetails['attachedDocuments']
            );

            $payload['AttachedDocumentCollection'] = [
                'AttachedDocument' => count($documents) === 1 ? $documents[0] : $documents,
            ];
        }

        $addressTypeMap = [
            'client' => 'QuotationClientAddress',
            'pickup' => 'OneOffQuotePickupAddress',
            'delivery' => 'OneOffQuoteDeliveryAddress',
        ];

        $addresses = [];
        foreach ($addressTypeMap as $addressKey => $addressType) {
            if (! isset($quoteDetails['addresses'][$addressKey]) || ! is_array($quoteDetails['addresses'][$addressKey])) {
                continue;
            }

            $addresses[] = $this->buildOneOffQuoteAddressPayload($addressType, $quoteDetails['addresses'][$addressKey]);
        }

        if ($addresses !== []) {
            $payload['OrganizationAddressCollection'] = [
                'OrganizationAddress' => count($addresses) === 1 ? $addresses[0] : $addresses,
            ];
        }

        return array_replace_recursive($payload, $quoteDetails['attributes'] ?? []);
    }

    private function buildOneOffQuoteAddressPayload(string $addressType, array $address): array
    {
        $payload = [
            'AddressType' => $addressType,
            'Address1' => $address['address1'],
            'City' => $address['city'],
            'Country' => [
                'Code' => $address['countryCode'],
            ],
            'AddressOverride' => $this->normalizeBoolean((bool) ($address['addressOverride'] ?? false)),
        ];

        foreach ([
            'address2' => 'Address2',
            'addressShortCode' => 'AddressShortCode',
            'companyName' => 'CompanyName',
            'email' => 'Email',
            'fax' => 'Fax',
            'govRegNum' => 'GovRegNum',
            'organizationCode' => 'OrganizationCode',
            'phone' => 'Phone',
            'postcode' => 'Postcode',
        ] as $source => $target) {
            if (isset($address[$source])) {
                $payload[$target] = $address[$source];
            }
        }

        if (is_string($address['govRegNumTypeCode'] ?? null) && $address['govRegNumTypeCode'] !== '') {
            $payload['GovRegNumType'] = [
                'Code' => $address['govRegNumTypeCode'],
            ];

            if (is_string($address['govRegNumTypeDescription'] ?? null) && $address['govRegNumTypeDescription'] !== '') {
                $payload['GovRegNumType']['Description'] = $address['govRegNumTypeDescription'];
            }
        }

        if (is_string($address['portCode'] ?? null) && $address['portCode'] !== '') {
            $payload['Port'] = [
                'Code' => $address['portCode'],
            ];

            if (is_string($address['portName'] ?? null) && $address['portName'] !== '') {
                $payload['Port']['Name'] = $address['portName'];
            }
        }

        if (is_string($address['screeningStatusCode'] ?? null) && $address['screeningStatusCode'] !== '') {
            $payload['ScreeningStatus'] = [
                'Code' => $address['screeningStatusCode'],
            ];

            if (is_string($address['screeningStatusDescription'] ?? null) && $address['screeningStatusDescription'] !== '') {
                $payload['ScreeningStatus']['Description'] = $address['screeningStatusDescription'];
            }
        }

        if (is_string($address['stateCode'] ?? null) && $address['stateCode'] !== '') {
            if (is_string($address['stateDescription'] ?? null) && $address['stateDescription'] !== '') {
                $payload['State'] = [
                    '_attributes' => ['Description' => $address['stateDescription']],
                    '_value' => $address['stateCode'],
                ];
            } else {
                $payload['State'] = $address['stateCode'];
            }
        }

        return array_replace_recursive($payload, $address['attributes'] ?? []);
    }

    private function buildOneOffQuoteAttachedDocumentPayload(array $document): array
    {
        $payload = [
            'FileName' => $document['fileName'],
            'ImageData' => $document['imageData'],
            'Type' => [
                'Code' => $document['typeCode'],
            ],
            'IsPublished' => $this->normalizeBoolean((bool) ($document['isPublished'] ?? false)),
        ];

        return array_replace_recursive($payload, $document['attributes'] ?? []);
    }

    private function buildOneOffQuoteChargeLinePayload(array $chargeLine, array $lineDefaults): array
    {
        $costLocalAmount = (string) ($chargeLine['costAmount']['value'] ?? '0.0000');
        $sellLocalAmount = (string) ($chargeLine['sellAmount']['value'] ?? '0.0000');

        $payload = [
            'APCashAdvanceRequired' => 'false',
            'ARCashAdvanceRequired' => 'false',
            'CostExchangeRate' => '1.000000000',
            'CostIsPosted' => 'false',
            'CostLocalAmount' => $costLocalAmount,
            'CostOSAmount' => $costLocalAmount,
            'CostOSGSTVATAmount' => '0',
            'CostRatingBehaviour' => [
                'Code' => 'NEW',
                'Description' => 'Create new Charge during AutoRating',
            ],
            'Description' => $chargeLine['description'],
            'SellExchangeRate' => '1.000000000',
            'SellInvoiceType' => 'FIN',
            'SellIsPosted' => 'false',
            'SellLocalAmount' => $sellLocalAmount,
            'SellOSAmount' => $sellLocalAmount,
            'SellOSGSTVATAmount' => '0',
            'SellRatingBehaviour' => [
                'Code' => 'NEW',
                'Description' => 'Create new Charge during AutoRating',
            ],
            'ChargeCode' => [
                'Code' => $chargeLine['chargeCode'],
            ],
        ];

        if (is_string($chargeLine['chargeCodeGroup'] ?? null) && $chargeLine['chargeCodeGroup'] !== '') {
            $payload['ChargeCodeGroup'] = [
                'Code' => $chargeLine['chargeCodeGroup'],
            ];

            if (is_string($chargeLine['chargeCodeGroupDescription'] ?? null) && $chargeLine['chargeCodeGroupDescription'] !== '') {
                $payload['ChargeCodeGroup']['Description'] = $chargeLine['chargeCodeGroupDescription'];
            }
        }

        $branchCode = $chargeLine['branchCode'] ?? $lineDefaults['branch'] ?? null;
        if (is_string($branchCode) && $branchCode !== '') {
            $payload['Branch'] = [
                'Code' => $branchCode,
            ];

            if (is_string($chargeLine['branchName'] ?? null) && $chargeLine['branchName'] !== '') {
                $payload['Branch']['Name'] = $chargeLine['branchName'];
            }
        }

        $departmentCode = $chargeLine['departmentCode'] ?? $lineDefaults['department'] ?? null;
        if (is_string($departmentCode) && $departmentCode !== '') {
            $payload['Department'] = [
                'Code' => $departmentCode,
            ];

            if (is_string($chargeLine['departmentName'] ?? null) && $chargeLine['departmentName'] !== '') {
                $payload['Department']['Name'] = $chargeLine['departmentName'];
            }
        }

        if (is_string($chargeLine['debtorKey'] ?? null) && $chargeLine['debtorKey'] !== '') {
            $payload['Debtor'] = [
                'Type' => $chargeLine['debtorType'] ?? 'Organization',
                'Key' => $chargeLine['debtorKey'],
            ];
        }

        if (array_key_exists('displaySequence', $chargeLine)) {
            $payload['DisplaySequence'] = (string) $chargeLine['displaySequence'];
        }

        $costCurrencyCode = $chargeLine['costAmount']['currencyCode'] ?? $lineDefaults['currencyCode'] ?? null;
        if (is_string($costCurrencyCode) && $costCurrencyCode !== '') {
            $payload['CostOSCurrency'] = [
                'Code' => $costCurrencyCode,
            ];
        }

        $sellCurrencyCode = $chargeLine['sellAmount']['currencyCode'] ?? $costCurrencyCode ?? null;
        if (is_string($sellCurrencyCode) && $sellCurrencyCode !== '') {
            $payload['SellOSCurrency'] = [
                'Code' => $sellCurrencyCode,
            ];
        }

        return array_replace_recursive($payload, $chargeLine['attributes'] ?? []);
    }

    private function syncFluentStaffPayload(): void
    {
        if ($this->target !== DataTarget::Staff || ! $this->staffIntent) {
            return;
        }

        if ($this->staffIntent === 'create') {
            $this->validateFluentStaffCreatePayload($this->staffDraft);
            $this->requestType = RequestType::NativeStaffCreation;
            $this->target = DataTarget::Staff;

            if (isset($this->staffDraft['company']) && ! $this->company) {
                $this->company = (string) $this->staffDraft['company'];
            }

            $this->targetKey = (string) $this->staffDraft['code'];
            $this->staff = $this->buildCompleteStaffPayload($this->staffDraft);

            return;
        }

        if ($this->staffIntent === 'update') {
            $this->validateFluentStaffUpdatePayload($this->staffDraft);
            $this->requestType = RequestType::NativeStaffUpdate;
            $this->target = DataTarget::Staff;

            if (isset($this->staffDraft['company']) && ! $this->company) {
                $this->company = (string) $this->staffDraft['company'];
            }

            $code = (string) ($this->staffDraft['code'] ?? $this->targetKey ?? '');
            $this->targetKey = $code;
            $this->staff = $this->buildSparseStaffPayload($this->staffDraft, $code);
        }
    }

    private function validateFluentStaffCreatePayload(array $payload): void
    {
        $errors = [];

        foreach (['code', 'loginName', 'password', 'fullName', 'homeBranch', 'homeDepartment', 'country'] as $requiredField) {
            $value = $payload[$requiredField] ?? null;

            if (! is_string($value) || trim($value) === '') {
                $errors[$requiredField] = ['The '.$requiredField.' field is required.'];
            }
        }

        if (array_key_exists('groups', $payload) && is_array($payload['groups'])) {
            foreach ($payload['groups'] as $index => $groupCode) {
                if (! is_string($groupCode)) {
                    $errors['groups.'.$index] = ['Group codes must be strings.'];
                }
            }
        }

        if (array_key_exists('groupsToRemove', $payload) && is_array($payload['groupsToRemove'])) {
            foreach ($payload['groupsToRemove'] as $index => $groupCode) {
                if (! is_string($groupCode)) {
                    $errors['groupsToRemove.'.$index] = ['Group codes must be strings.'];
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function validateFluentStaffUpdatePayload(array $payload): void
    {
        $errors = [];

        $value = $payload['code'] ?? $this->targetKey;

        if (! is_string($value) || trim($value) === '') {
            $errors['code'] = ['The code field is required.'];
        }

        if (array_key_exists('groups', $payload) && is_array($payload['groups'])) {
            foreach ($payload['groups'] as $index => $groupCode) {
                if (! is_string($groupCode)) {
                    $errors['groups.'.$index] = ['Group codes must be strings.'];
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function assertStaffBuilderContext(string $method): void
    {
        if ($this->target !== DataTarget::Staff || ! in_array($this->staffIntent, ['create', 'update'], true)) {
            throw new \Exception("{$method}() requires a staff builder intent. Call staff()->create() or staff()->update() first.");
        }
    }

    private function setStaffDraftValue(string $field, mixed $value, bool $isCode = false): self
    {
        $this->assertStaffBuilderContext($field);

        $this->staffDraft[$field] = $value;
        $this->markStructuredField(match ($field) {
            'loginName' => 'login_name',
            'fullName' => 'full_name',
            'active' => 'is_active',
            'homeBranch' => 'branch',
            'homeDepartment' => 'department',
            'workPhone' => 'phone',
            'addressOne' => 'address_line_1',
            default => Str::snake($field),
        });

        if ($isCode) {
            $this->targetKey = is_string($value) ? $value : null;
        }

        return $this;
    }

    private function assertOneOffQuoteBuilderContext(string $method): void
    {
        if ($this->target !== DataTarget::OneOffQuote || $this->oneOffQuoteIntent !== 'create') {
            throw new \Exception("{$method}() requires oneOffQuote()->create() context.");
        }
    }

    private function assertOrganizationUpdateContext(string $method): void
    {
        if ($this->target !== DataTarget::Organization || $this->organizationIntent !== 'update') {
            throw new \Exception("{$method}() requires organization('CODE')->update() context.");
        }
    }

    private function assertOrganizationCreateContext(string $method): void
    {
        if ($this->target !== DataTarget::Organization || $this->organizationIntent !== 'create') {
            throw new \Exception("{$method}() requires organization('CODE')->create() context.");
        }
    }

    private function assertOrganizationWriteContext(string $method): void
    {
        if ($this->target !== DataTarget::Organization || ! in_array($this->organizationIntent, ['create', 'update'], true)) {
            throw new \Exception("{$method}() requires organization('CODE')->create() or ->update() context.");
        }
    }

    private function setOrganizationDraftValue(string $field, mixed $value): self
    {
        $this->assertOrganizationCreateContext($field);
        $this->organizationDraft[$field] = $value;
        $this->markStructuredField(Str::snake($field));

        return $this;
    }

    private function setOneOffQuoteDraftValue(string $field, mixed $value): self
    {
        $this->assertOneOffQuoteBuilderContext($field);
        $this->oneOffQuoteDraft[$field] = $value;
        $this->markStructuredField(match ($field) {
            'additionalTerms' => 'additional_terms',
            'isDomesticFreight' => 'is_domestic_freight',
            default => Str::snake($field),
        });

        return $this;
    }

    private function assertConnectionConfig(): void
    {
        // If the url, username and password are not set in the config file, throw an exception.
        if (! $this->config['url'] || ! $this->config['username'] || ! $this->config['password']) {
            throw new \Exception('URL, Username and password must be set in the config file.');
        }
    }

    private function inferSystemContextFromUrl(): array
    {
        $host = parse_url((string) ($this->config['url'] ?? ''), PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return [];
        }

        $subdomain = strtoupper((string) strtok($host, '.'));

        if (! preg_match('/^([A-Z0-9]+?)(PRD|TRN|TST|UAT|DEV|QA|SIT)(?:SERVICES)?$/', $subdomain, $matches)) {
            return [];
        }

        return [
            'enterprise' => $matches[1],
            'server' => $matches[2],
        ];
    }

    private function setStaffValueIfProvided(array &$payload, array $staffDetails, string $inputKey, string $outputKey, ?callable $transform = null): void
    {
        if (! array_key_exists($inputKey, $staffDetails)) {
            return;
        }

        $value = $staffDetails[$inputKey];
        $payload[$outputKey] = $transform ? $transform($value) : $value;
    }

    private function normalizeStaffWorkingHours(array $workingHours, string $action = 'Insert'): array
    {
        return [
            '_attributes' => ['Action' => $action],
            'SundayWorkingHours' => $workingHours['sunday'] ?? '',
            'MondayWorkingHours' => $workingHours['monday'] ?? '',
            'TuesdayWorkingHours' => $workingHours['tuesday'] ?? '',
            'WednesdayWorkingHours' => $workingHours['wednesday'] ?? '',
            'ThursdayWorkingHours' => $workingHours['thursday'] ?? '',
            'FridayWorkingHours' => $workingHours['friday'] ?? '',
            'SaturdayWorkingHours' => $workingHours['saturday'] ?? '',
        ];
    }

    private function normalizeStaffGroups(array $groups, string $defaultAction = 'MERGE'): array
    {
        if ($groups !== [] && array_keys($groups) !== range(0, count($groups) - 1)) {
            $groups = [$groups];
        }

        return array_map(function ($group) use ($defaultAction) {
            if (is_string($group)) {
                $group = ['code' => $group];
            }

            return [
                '_attributes' => ['Action' => $group['action'] ?? $defaultAction],
                'MembershipType' => $group['membershipType'] ?? 'UDF',
                'SkillLevel' => (string) ($group['skillLevel'] ?? 0),
                'GlbGroup' => [
                    'Code' => $group['code'] ?? $group['Code'] ?? '',
                ],
            ];
        }, $groups);
    }

    private function normalizeStaffReference(string|array $value, string $tableName): array
    {
        if (is_string($value)) {
            return [
                '_attributes' => ['TableName' => $tableName],
                'Code' => $value,
            ];
        }

        return [
            '_attributes' => ['TableName' => $value['tableName'] ?? $tableName],
            'Code' => $value['code'] ?? $value['Code'] ?? '',
        ];
    }

    public function activeStaffIntent(): ?string
    {
        return $this->staffIntent;
    }

    public function activeOneOffQuoteIntent(): ?string
    {
        return $this->oneOffQuoteIntent;
    }

    public function activeOrganizationIntent(): ?string
    {
        return $this->organizationIntent;
    }

    private function operationRegistry(): OperationRegistry
    {
        static $registry;

        return $registry ??= new OperationRegistry;
    }

    private function schemaValidator(): SchemaValidator
    {
        static $validator;

        return $validator ??= new SchemaValidator;
    }

    private function describeResource(): ?string
    {
        return match (true) {
            $this->requestType === RequestType::NativeCompanyRetrieval => 'company',
            $this->target === DataTarget::Organization => 'organization',
            $this->target === DataTarget::Staff => 'staff',
            $this->target === DataTarget::OneOffQuote => 'one_off_quote',
            $this->target === DataTarget::Receiveable => 'receivable',
            default => null,
        };
    }

    private function filterIgnoredStructuredFields(array $payload): array
    {
        foreach (array_keys($this->structuredOverrides) as $field) {
            unset($payload[$field]);
        }

        return $payload;
    }

    private function bootstrapStructuredOperation(OperationDefinition $definition, array $payload): void
    {
        foreach ($definition->contextFields as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            $this->applyStructuredContext($field, $payload[$field]);
        }

        $selector = $definition->selector;
        if ($selector !== null) {
            $field = $selector['field'];
            $method = $selector['method'];

            if (array_key_exists($field, $payload)) {
                $this->{$method}($payload[$field]);
            } elseif (! $this->resourceMatches($definition->resource) && (($selector['required'] ?? false) === false)) {
                $this->{$method}();
            }
        } else {
            $resourceMethod = match ($definition->resource) {
                'staff' => 'staff',
                'one_off_quote' => 'oneOffQuote',
                'organization' => 'organization',
                default => null,
            };

            if ($resourceMethod !== null && ! $this->resourceMatches($definition->resource)) {
                $this->{$resourceMethod}();
            }
        }

        foreach ($definition->bootstrapMethods as $method) {
            $this->{$method}();
        }

        $this->currentOperation = $definition->id;
    }

    private function applyStructuredContext(string $field, mixed $value): void
    {
        match ($field) {
            'config' => $this->withConfig((string) $value),
            'company' => $this->withCompany((string) $value),
            'enterprise' => $this->withEnterprise((string) $value),
            'server' => $this->withServer((string) $value),
            'sender_id' => $this->withSenderId((string) $value),
            'recipient_id' => $this->withRecipientId((string) $value),
            'code_mapping' => $this->withCodeMapping((bool) $value),
            default => null,
        };
    }

    private function applyStructuredField(array $field, mixed $value): void
    {
        if ($field['repeatable']) {
            foreach (array_values((array) $value) as $item) {
                $this->invokeStructuredMethod($field['method'], $item, $field['builder']);
            }

            return;
        }

        $this->invokeStructuredMethod($field['method'], $value, $field['builder']);
    }

    private function invokeStructuredMethod(string $method, mixed $value, ?string $builderClass = null): void
    {
        $arguments = $this->buildStructuredMethodArguments($method, $value, $builderClass);

        $this->{$method}(...$arguments);
    }

    private function buildStructuredMethodArguments(string $method, mixed $value, ?string $builderClass = null): array
    {
        $reflection = new \ReflectionMethod($this, $method);
        $value = $this->transformStructuredValueForMethod($method, $value);

        if ($builderClass !== null) {
            return [$this->buildStructuredClosure($builderClass, is_array($value) ? $value : [])];
        }

        if (count($reflection->getParameters()) === 1) {
            return [$value];
        }

        $value = is_array($value) ? $value : [];
        $arguments = [];

        foreach ($reflection->getParameters() as $parameter) {
            $key = Str::snake($parameter->getName());

            if (array_key_exists($key, $value)) {
                $arguments[] = $value[$key];

                continue;
            }

            $arguments[] = $parameter->isDefaultValueAvailable()
                ? $parameter->getDefaultValue()
                : null;
        }

        return $arguments;
    }

    private function buildStructuredClosure(string $builderClass, array $payload): Closure
    {
        return function ($builder) use ($builderClass, $payload) {
            foreach ($this->operationRegistry()->builderFields($builderClass) as $field) {
                if (! array_key_exists($field['name'], $payload)) {
                    continue;
                }

                $value = $this->transformBuilderStructuredValue($builderClass, $field['method'], $payload[$field['name']]);
                $reflection = new \ReflectionMethod($builderClass, $field['method']);

                if (count($reflection->getParameters()) === 1) {
                    $builder->{$field['method']}($value);

                    continue;
                }

                $arguments = [];
                $value = is_array($value) ? $value : [];

                foreach ($reflection->getParameters() as $parameter) {
                    $key = Str::snake($parameter->getName());

                    if (array_key_exists($key, $value)) {
                        $arguments[] = $value[$key];

                        continue;
                    }

                    $arguments[] = $parameter->isDefaultValueAvailable()
                        ? $parameter->getDefaultValue()
                        : null;
                }

                $builder->{$field['method']}(...$arguments);
            }
        };
    }

    private function transformStructuredValueForMethod(string $method, mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return match ($method) {
            'criteriaGroup' => [
                'criteria' => array_map(fn (array $item) => [
                    'Entity' => $item['entity'] ?? '',
                    'FieldName' => $item['field_name'] ?? '',
                    'Value' => $item['value'] ?? null,
                ], $value['criteria'] ?? []),
                'type' => $value['type'] ?? 'Key',
            ],
            'addAddress' => $value,
            'addContact' => $value,
            'addEDICommunication' => $value,
            default => $value,
        };
    }

    private function transformBuilderStructuredValue(string $builderClass, string $method, mixed $value): mixed
    {
        return match ($builderClass) {
            OneOffQuoteAddressBuilder::class,
            OneOffQuoteChargeLineBuilder::class,
            OneOffQuoteAttachedDocumentBuilder::class,
            OrganizationAddressBuilder::class,
            OrganizationContactBuilder::class,
            OrganizationEDICommunicationBuilder::class => $value,
            default => $value,
        };
    }

    private function normalizeStructuredBooleanString(mixed $value): mixed
    {
        if (is_bool($value) || is_string($value)) {
            return $this->normalizeBoolean($value);
        }

        return $value;
    }

    private function resourceMatches(string $resource): bool
    {
        return match ($resource) {
            'shipment' => $this->target === DataTarget::Shipment,
            'booking' => $this->target === DataTarget::Booking,
            'custom' => $this->target === DataTarget::Custom,
            'receivable' => $this->target === DataTarget::Receiveable,
            'one_off_quote' => $this->target === DataTarget::OneOffQuote,
            'staff' => $this->target === DataTarget::Staff,
            'organization' => $this->target === DataTarget::Organization && $this->requestType !== RequestType::NativeCompanyRetrieval,
            'company' => $this->requestType === RequestType::NativeCompanyRetrieval,
            default => false,
        };
    }

    private function markStructuredField(string $field): void
    {
        $this->structuredOverrides[$field] = true;
    }

    private function normalizeBoolean(bool|string $value): string
    {
        if (is_string($value)) {
            return strtolower($value);
        }

        return $value ? 'true' : 'false';
    }
}
