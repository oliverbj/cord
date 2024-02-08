<?php

namespace Oliverbj\Cord;

use Illuminate\Support\Facades\Http;
use Oliverbj\Cord\Enums\DataTarget;
use Oliverbj\Cord\Enums\RequestType;
use Oliverbj\Cord\Requests\NativeCompanyRetrieval;
use Oliverbj\Cord\Requests\NativeOrganizationRetrieval;
use Oliverbj\Cord\Requests\UniversalDocumentRequest;
use Oliverbj\Cord\Requests\UniversalEvent;
use Oliverbj\Cord\Requests\UniversalShipmentRequest;
use Request;

class Cord
{
    public ?DataTarget $target = DataTarget::Shipment;

    public ?RequestType $requestType = RequestType::UniversalShipmentRequest;

    public ?string $targetKey = null;

    public ?string $company = null;

    public ?string $server = null;

    public ?string $enterprise = null;

    public $config = null;

    public array $criteriaGroups = [];

    public array $filters = [];

    public array $event = [];

    public $document = [];

    public $address = [];

    protected $xml;

    public bool $asXml = false;

    protected $client;

    public function __construct()
    {
        $this->config = config('cord.base.eadapter_connection');

        //If the url, username and password are not set in the config file, throw an exception.
        if (! $this->config['url'] || ! $this->config['username'] || ! $this->config['password']) {
            throw new \Exception('URL, Username and password must be set in the config file.');
        }

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

        return $this;
    }

    public function withServer(string $server): self
    {
        $this->server = $server;

        return $this;
    }

    public function withEnterprise(string $enterprise): self
    {
        $this->enterprise = $enterprise;

        return $this;
    }

    public function withConfig(string $configName): self
    {
        $this->config = config('cord.'.$configName.'.eadapter_connection');

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

        return $this;
    }

    /**
     * Determine if the request is for a shipment.
     */
    public function shipment(string $shipment): self
    {
        $this->targetKey = $shipment;
        $this->target = DataTarget::Shipment;
        $this->requestType = RequestType::UniversalShipmentRequest;

        return $this;
    }

    /**
     * Determine if the request is for a native request.
     */
    public function organization(?string $code = null): self
    {
        $this->requestType = RequestType::NativeOrganizationRetrieval;
        $this->target = DataTarget::Organization;

        if ($code) {
            $this->targetKey = $code;
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

    public function addAddress(string $code, string $addressOne, ?string $addressTwo, string $country, string $city, ?string $state = null, ?string $postcode = null, ?string $relatedPort = null, ?string $phone = null, ?string $fax = null, ?string $mobile = null, ?string $email = null, array $capabilities = []): self
    {

        if ($this->target !== DataTarget::Organization || $this->requestType !== RequestType::NativeOrganizationRetrieval) {
            throw new \Exception('You must call an organization before adding an address. Use organization(CODEHERE) before calling this method.');
        }

        $this->addEvent(date('c'), 'DIM', 'Address added automatically from XML');

        $formattedCapabilities = [];

        foreach ($capabilities as $capability) {
            // Check for the existence of required keys in each capability
            if (! isset($capability['AddressType']) || ! isset($capability['IsMainAddress'])) {
                throw new \Exception('Missing required keys in capabilities array. Each capability must include "AddressType" and "IsMainAddress".');
            }

            $formattedCapabilities[] = [
                'OrgAddressCapability' => [
                    'AddressType' => $capability['AddressType'],
                    'IsMainAddress' => $capability['IsMainAddress'],
                ],
            ];
        }

        $this->address = [
            'OrgAddressCollection' => [
                'OrgAddress' => [
                    '_Action' => 'INSERT',
                    'Code' => $code,
                    'Address1' => $addressOne,
                    'Address2' => $addressTwo,
                    'CountryCode' => [
                        'Code' => $country,
                    ],
                    'City' => $city,
                    'State' => $state,
                    'PostCode' => $postcode,
                    'RelatedPortCode' => [
                        'Code' => $relatedPort,
                    ],
                    'Phone' => $phone,
                    'Fax' => $fax,
                    'Mobile' => $mobile,
                    'Email' => $email,
                    'SuppressAddressValidationError' => (string) true,
                    'OrgAddressCapabilityCollection' => $this->formatCapabilities($capabilities),
                ],
            ],
        ];

        return $this;
    }

    /**
     * Determine if the request is for a shipment.
     */
    public function company(?string $code = null): self
    {
        $this->requestType = RequestType::NativeCompanyRetrieval;
        $this->target = DataTarget::Organization;

        if ($code) {
            $this->targetKey = $code;
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

        return $this;
    }

    /**
     * Determine if the request should include documents.
     */
    public function withDocuments(): self
    {
        $this->requestType = RequestType::UniversalDocumentRequest;

        return $this;
    }

    public function addDocument(string $file_contents, string $name, string $type, string $description = '', bool $isPublished = false): self
    {
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
                    'IsPublished' => var_export($isPublished, true), //cast to string,
                ],
            ],
        ];

        return $this;
    }

    /**
     * Add an event to the request.
     */
    public function addEvent(string $date, string $type, string $reference = 'Automatic event from Cord', bool $isEstimate = false): self
    {
        $this->requestType = RequestType::UniversalEvent;

        if ($this->event) {
            throw new \Exception('Only one event can be added to a request');
        }

        if (! $date) {
            $date = date('c');
        }
        $date = date('c', strtotime($date));

        $this->event = [
            'EventTime' => $date,
            'EventType' => $type,
            'EventReference' => $reference,
            'IsEstimate' => var_export($isEstimate, true), //cast to string
        ];

        return $this;
    }

    /**
     * Add filter(s) to the request.
     */
    public function filter($type, $value): self
    {
        //Every time this method is called, it will add a new filter to the filters array.
        $this->filters[] = [
            'Type' => $type,
            'Value' => $value,
        ];

        return $this;
    }

    /**
     * Get the XML object.
     */
    public function run()
    {
        $requestType = match ($this->requestType) {
            RequestType::UniversalShipmentRequest => new UniversalShipmentRequest($this),
            RequestType::UniversalDocumentRequest => new UniversalDocumentRequest($this),
            RequestType::UniversalEvent => new UniversalEvent($this),
            RequestType::NativeOrganizationRetrieval => new NativeOrganizationRetrieval($this),
            RequestType::NativeCompanyRetrieval => new NativeCompanyRetrieval($this),
        };

        $this->xml = $requestType->xml();

        return $this->fetch();
    }

    /**
     * Get the request as XML.
     */
    public function inspect(): string
    {
        $this->run();

        return $this->xml;
    }

    /**
     * Determine if the response should be returned as XML.
     */
    public function toXml()
    {
        $this->asXml = true;

        return $this;
    }

    private function checkForErrors()
    {

        if (! $this->targetKey && ! in_array($this->requestType, [RequestType::NativeOrganizationRetrieval, RequestType::NativeCompanyRetrieval])) {
            throw new \Exception('You haven\'t set any target key. This is usually the shipment number, customs declaration number or booking number.');
        }
    }

    protected function flattenResponse(array $response, string $key)
    {
        $response = $response ?? [];

        return tap($response, function (&$items) use ($key) {
            // Check if there's only one result with the specified key
            if (is_array($items) && count($items) === 1 && isset($items[$key])) {
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

    protected function fetch()
    {
        $this->checkForErrors();
        $this->setClient();

        $response = $this->client->send('POST', $this->config['url'], [
            'body' => $this->xml,
        ])->throw()->body();

        $xmlResponse = $response;

        //XML to JSON
        $response = json_decode(json_encode(simplexml_load_string($response)), true);

        //If eAdapter response is not successful, throw exception:
        if ($response['Status'] == 'ERR') {
            //If client expects json, return json:
            if (Request::wantsJson()) {
                $status = match ($response['ProcessingLog']) {
                    'Warning - There is no business object matching the criteria.' => 404,
                    default => 500,
                };

                return response()->json(['error' => $response['ProcessingLog']], $status);
            }
            throw new \Exception($response['ProcessingLog']);
        }

        if ($this->asXml) {
            $xmlResponse = simplexml_load_string($xmlResponse);

            //We need to return the first subelement of the Data element, because the Data element is an array.
            return $xmlResponse->Data->children()[0];
        }

        //If eAdapter response is successful, return data:
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
}
