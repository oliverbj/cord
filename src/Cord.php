<?php

namespace Oliverbj\Cord;

use Illuminate\Support\Facades\Http;
use Oliverbj\Cord\Enums\DataTarget;
use Oliverbj\Cord\Enums\RequestType;
use Oliverbj\Cord\Requests\NativeCompanyRetrieval;
use Oliverbj\Cord\Requests\NativeOrganizationRetrieval;
use Oliverbj\Cord\Requests\NativeOrganizationUpdate;
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

    public $contact = [];

    public $jobRequiredDocument = [];

    public $ediCommunication = [];

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
        //Reset the criteria group.
        $this->criteriaGroups = [];

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

    public function addEDICommunication(array $ediCommunicationDetails): self
    {
        $this->requestType = RequestType::NativeOrganizationUpdate;

        if ($this->target !== DataTarget::Organization) {
            throw new \Exception('You must call an organization before adding an EDI communication mode. Use organization() method before calling this method.');
        }

        // Validate required fields in $addressDetails array
        $requiredFields = ['module', 'purpose', 'direction', 'format', 'destination', 'transport'];
        foreach ($requiredFields as $field) {
            if (! isset($ediCommunicationDetails[$field])) {
                throw new \Exception("Missing required field '{$field}' in contact details.");
            }
        }

        $this->ediCommunication = [
            '_attributes' => ['Action' => 'INSERT'],
            'Module' => $ediCommunicationDetails['module'],
            'MessagePurpose' => $ediCommunicationDetails['purpose'],
            'CommsDirection' => $ediCommunicationDetails['direction'],
            'CommunicationsTransport' => $ediCommunicationDetails['transport'],
            'Destination' => $ediCommunicationDetails['destination'],
            'FileFormat' => $ediCommunicationDetails['format'],
            'ServerAddressSubject' => $ediCommunicationDetails['subject'] ?? '',
            'PublishInternalMilestones' => $ediCommunicationDetails['publishMilestones'] ?? 'false',
            'LocalPartyVanID' => $ediCommunicationDetails['senderVAN'] ?? '',
            'RelatedPartyVanID' => $ediCommunicationDetails['receiverVAN'] ?? '',
            'Filename' => $ediCommunicationDetails['filename'] ?? '',
        ];

        return $this;

    }

    public function addContact(array $contactDetails): self
    {
        $this->requestType = RequestType::NativeOrganizationUpdate;

        if ($this->target !== DataTarget::Organization) {
            throw new \Exception('You must call an organization before adding a contact person. Use organization() method before calling this method.');
        }

        // Validate required fields in $addressDetails array
        $requiredFields = ['name', 'email'];
        foreach ($requiredFields as $field) {
            if (! isset($contactDetails[$field])) {
                throw new \Exception("Missing required field '{$field}' in contact details.");
            }
        }

        $docsToDeliver = $contactDetails['documentsToDeliver']['OrgDocument'];

        // Check if $ediCommunications is an associative array or an array of key-value pairs
        if (! empty($docsToDeliver) && is_array($docsToDeliver) && array_keys($docsToDeliver) !== range(0, count($docsToDeliver) - 1)) {
            $docsToDeliver = [$docsToDeliver]; // Convert to an array of one element
        }

        $documents = [];
        foreach ($docsToDeliver as $document) {

            $documents[] = [
                '_attributes' => [
                    'Action' => 'INSERT',
                ],
                'DocumentGroup' => $document['DocumentGroup'] ?? '',
                'DefaultContact' => $document['DefaultContact'] ?? 'false',
                'AttachmentType' => $document['AttachmentType'] ?? null,
                'DeliverBy' => $document['DeliverBy'] ?? '',
                'MenuItem' => isset($document['MenuItem']['BusinessContext']) ? [
                    'MenuName' => $document['MenuItem']['MenuName'] ?? '',
                    'BusinessContext' => $document['MenuItem']['BusinessContext'] ?? '',
                    'MenuPath' => $document['MenuItem']['MenuPath'] ?? '',
                    'IsClientSpecific' => $document['MenuItem']['IsClientSpecific'] ?? 'false',
                    'IsSystemDefined' => $document['MenuItem']['IsSystemDefined'] ?? 'false',
                    'FilterList' => $document['MenuItem']['FilterList'] ?? '',
                ] : null,
                'FilterShipmentMode' => $document['FilterShipmentMode'] ?? 'ALL',
                'FilterDirection' => $document['FilterDirection'] ?? 'ALL',
                'EmailSubjectMacro' => $document['EmailSubjectMacro'] ?? '',
            ];
        }

        $this->contact = [
            '_attributes' => ['Action' => 'INSERT'],
            'IsActive' => $contactDetails['active'] ?? 'true',
            'ContactName' => $contactDetails['name'],
            'NotifyMode' => $contactDetails['notifyMode'] ?? 'EML',
            'Title' => $contactDetails['title'] ?? '',
            'Gender' => $contactDetails['gender'] ?? 'N',
            'Email' => $contactDetails['email'],
            'Language' => $contactDetails['language'] ?? 'EN',
            'Phone' => $contactDetails['phone'] ?? '',
            'Mobile' => $contactDetails['mobilePhone'] ?? '',
            'HomeWork' => $contactDetails['homePhone'] ?? '',
            'AttachmentType' => $contactDetails['attachmentType'] ?? 'PDF',
            'OrgDocumentCollection' => [
                'OrgDocument' => count($documents) === 1 ? $documents[0] : $documents,
            ],
        ];

        return $this;
    }

    /**
     * A method to transfer EDI communication from one organization to another.
     */
    public function transferEDICommunication(array $ediCommunication)
    {
        $this->requestType = RequestType::NativeOrganizationUpdate;

        if ($this->target !== DataTarget::Organization) {
            throw new \Exception('You must call an organization before transferring an EDI communication. Use organization() method before calling this method.');
        }

        //PK is already present in an EDICommunication collection. If it is not, it means that we have received something else...
        if (! isset($ediCommunication['PK'])) {
            throw new \Exception('Invalid EDI communication array proivded. Be sure to provide the array data of the EDICommunicationsMode array.');
        }

        $this->addActionRecursively($ediCommunication);
        $ediCommunication = array_merge(['_attributes' => ['Action' => 'INSERT']], $ediCommunication);

        //CW1 adds an @attributes to some tags. Remove it!
        $this->removeKeyRecursively($ediCommunication, '@attributes');

        //Below we do not want to insert.:
        if (isset($ediCommunication['MessageVAN'])) {
            unset($ediCommunication['MessageVAN']);
        }

        //Remove all values that are an empty array!
        $this->ediCommunication = collect($ediCommunication)->filter(function ($value) {
            return ! empty($value);
        })->all();

        return $this;
    }

    /**
     * A method to transfer a contact from one organization to another.
     */
    public function transferDocumentTracking(array $jobRequiredDocument)
    {
        $this->requestType = RequestType::NativeOrganizationUpdate;

        if ($this->target !== DataTarget::Organization) {
            throw new \Exception('You must call an organization before transferring a document tracking. Use organization() method before calling this method.');
        }

        //PK is already present in an JobRequiredDocument collection. If it is not, it means that we have received something else...
        if (! isset($jobRequiredDocument['PK'])) {
            throw new \Exception('Invalid document tracking array proivded. Be sure to provide the array data of the JobRequiredDocument array.');
        }

        $this->addActionRecursively($jobRequiredDocument);
        $jobRequiredDocument = array_merge(['_attributes' => ['Action' => 'INSERT']], $jobRequiredDocument);

        //CW1 adds an @attributes to some tags. Remove it!
        $this->removeKeyRecursively($jobRequiredDocument, '@attributes');

        //Below we do not want to insert.:
        if (isset($jobRequiredDocument['RelatedCountry'])) {
            unset($jobRequiredDocument['RelatedCountry']);
        }

        if (isset($jobRequiredDocument['DocumentOwner'])) {
            unset($jobRequiredDocument['DocumentOwner']);
        }

        //Remove all values that are an empty array!
        $this->jobRequiredDocument = collect($jobRequiredDocument)->filter(function ($value) {
            return ! empty($value);
        })->all();

        return $this;
    }

    /**
     * A method to transfer a contact from one organization to another.
     */
    public function transferContact(array $contact)
    {
        $this->requestType = RequestType::NativeOrganizationUpdate;

        if ($this->target !== DataTarget::Organization) {
            throw new \Exception('You must call an organization before transferring a contact. Use organization() method before calling this method.');
        }

        //PK is already present in an OrgContact collection. If it is not, it means that we have received something else...
        if (! isset($contact['PK'])) {
            throw new \Exception('Invalid contact array proivded. Be sure to provide the array data of the OrgContact array.');
        }

        $this->addActionRecursively($contact);
        $contact = array_merge(['_attributes' => ['Action' => 'INSERT']], $contact);

        //CW1 adds an @attributes to some tags. Remove it!
        $this->removeKeyRecursively($contact, '@attributes');

        //Make sure that "Documents to Deliver" can be transferred (must be a "merge")
        if (isset($contact['OrgDocumentCollection'])) {
            $docsToDeliver = $contact['OrgDocumentCollection']['OrgDocument'] ?? [];
            // Check if $capabilities is an associative array or an array of key-value pairs
            if (! empty($docsToDeliver) && is_array($docsToDeliver) && array_keys($docsToDeliver) !== range(0, count($docsToDeliver) - 1)) {
                $contact['OrgDocumentCollection']['OrgDocument'] = [$contact['OrgDocumentCollection']['OrgDocument']]; // Convert to an array of one element
            }

            foreach ($contact['OrgDocumentCollection']['OrgDocument'] as $key => $docs) {
                if (isset($contact['OrgDocumentCollection']['OrgDocument'][$key]['MenuItem'])) {
                    $contact['OrgDocumentCollection']['OrgDocument'][$key]['MenuItem']['_attributes'] = [
                        'Action' => 'MERGE',
                    ];
                }
            }
        }

        //We are not transferring web security groups.
        if (isset($contact['GlbGroupOrgContactLinkCollection'])) {
            unset($contact['GlbGroupOrgContactLinkCollection']);
        }

        //Remove all values that are an empty array!
        $this->contact = collect($contact)->filter(function ($value) {
            return ! empty($value);
        })->all();

        return $this;
    }

    /**
     * A method to transfer an address from one organization to another.
     */
    public function transferAddress(array $address)
    {
        $this->requestType = RequestType::NativeOrganizationUpdate;

        if ($this->target !== DataTarget::Organization) {
            throw new \Exception('You must call an organization before transferring an address. Use organization() method before calling this method.');
        }

        //PK is already present in an OrgAddress collection. If it is not, it means that we have received something else...
        if (! isset($address['PK'])) {
            throw new \Exception('Invalid address array proivded. Be sure to provide the array data of the OrgAddress array.');
        }

        $this->addActionRecursively($address);
        $address = array_merge(['_attributes' => ['Action' => 'INSERT']], $address);

        //CW1 adds an @attributes to some tags. Remove it!
        $this->removeKeyRecursively($address, '@attributes');

        //Below are related to another table in CW1, so we need to set the action to "UPDATE":
        $address['RelatedPortCode']['_attributes'] = ['Action' => 'UPDATE'];
        $address['CountryCode']['_attributes'] = ['Action' => 'UPDATE'];

        //Remove all values that are an empty array!
        $this->address = collect($address)->filter(function ($value) {
            return ! empty($value);
        })->all();

        return $this;

    }

    /**
     * Todo: WIP - not stable!
     */
    public function addAddress(array $addressDetails): self
    {
        $this->requestType = RequestType::NativeOrganizationUpdate;

        if ($this->target !== DataTarget::Organization) {
            throw new \Exception('You must call an organization before adding an address. Use organization() method before calling this method.');
        }

        // Validate required fields in $addressDetails array
        $requiredFields = ['code', 'addressOne', 'country', 'city'];
        foreach ($requiredFields as $field) {
            if (! isset($addressDetails[$field])) {
                throw new \Exception("Missing required field '{$field}' in address details.");
            }
        }

        $capabilities = $addressDetails['capabilities']['OrgAddressCapability'] ?? [];

        // Check if $capabilities is an associative array or an array of key-value pairs
        if (! empty($capabilities) && is_array($capabilities) && array_keys($capabilities) !== range(0, count($capabilities) - 1)) {
            $capabilities = [$capabilities]; // Convert to an array of one element
        }

        foreach ($capabilities as $key => $capability) {
            $capabilities[$key] = [
                '_attributes' => ['Action' => 'INSERT'],
                'AddressType' => $capability['AddressType'] ?? '',
                'IsMainAddress' => $capability['IsMainAddress'] ?? '',
            ];
        }

        $this->address = [
            '_attributes' => ['Action' => 'INSERT'],
            'IsActive' => $addressDetails['active'] ?? 'true',
            'Code' => $addressDetails['code'],
            'Address1' => $addressDetails['addressOne'],
            'Address2' => $addressDetails['addressTwo'] ?? '',
            'CountryCode' => [
                'Code' => $addressDetails['country'],
            ],
            'City' => $addressDetails['city'],
            'State' => $addressDetails['state'] ?? null,
            'PostCode' => $addressDetails['postcode'] ?? null,
            'RelatedPortCode' => [
                'Code' => $addressDetails['relatedPort'] ?? null,
            ],
            'Phone' => $addressDetails['phone'] ?? null,
            'Fax' => $addressDetails['fax'] ?? null,
            'Mobile' => $addressDetails['mobile'] ?? null,
            'Email' => $addressDetails['email'] ?? null,
            'FCLEquipmentNeeded' => $addressDetails['dropModeFCL'] ?? 'ASK',
            'LCLEquipmentNeeded' => $addressDetails['dropModeLCL'] ?? 'ASK',
            'AIREquipmentNeeded' => $addressDetails['dropModeAIR'] ?? 'ASK',
            'SuppressAddressValidationError' => 'true',
            'OrgAddressCapabilityCollection' => [
                'OrgAddressCapability' => count($capabilities) === 1 ? $capabilities[0] : $capabilities,
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
            RequestType::NativeOrganizationUpdate => new NativeOrganizationUpdate($this),
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

        \Log::debug('Request Type is', [
            'type' => $this->requestType,
            'targetKey' => $this->targetKey,
            'target' => $this->target,
            'company' => $this->company,
            'response' => $response,
            'XML' => $this->xml,
        ]);

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
}
