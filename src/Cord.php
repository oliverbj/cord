<?php

namespace Oliverbj\Cord;

use Illuminate\Support\Facades\Http;
use Oliverbj\Cord\Enums\DataTarget;
use Oliverbj\Cord\Enums\RequestType;
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

    //public bool $documents = false;

    public array $filters = [];

    public array $event = [];

    public $document = [];

    protected $xml;

    public ?string $xmlResponse;

    protected $client;

    public function __construct()
    {
        $this->config = config('cord.base.eadapter_connection');

        //If the company, server and enterprise are not set in the config file, throw an exception.

        if (! $this->config['url'] || ! $this->config['username'] || ! $this->config['password']) {
            throw new \Exception('Company, server and enterprise must be set in the config file.');
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

    public function company(string $company): self
    {
        $this->company = $company;

        return $this;
    }

    public function server(string $server): self
    {
        $this->server = $server;

        return $this;
    }

    public function enterprise(string $enterprise): self
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
        //$this->documents = true;
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

    private function checkForErrors()
    {
        if (! $this->targetKey) {
            throw new \Exception('You haven\'t set any target key. This is usually the shipment number, customs declaration number or booking number.');
        }
    }

    protected function fetch()
    {
        $this->checkForErrors();
        $this->setClient();

        $response = $this->client->send('POST', $this->config['url'], [
            'body' => $this->xml,
        ])->throw()->body();

        $this->xmlResponse = $response;

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

        //If eAdapter response is successful, return data:
        return $response['Data'];
    }
}
