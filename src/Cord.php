<?php

namespace Oliverbj\Cord;

use Illuminate\Support\Facades\Http;
use Oliverbj\Cord\Enums\DataTarget;
use Oliverbj\Cord\Requests\UniversalDocumentRequest;
use Oliverbj\Cord\Requests\UniversalShipmentRequest;
use Request;

class Cord
{
    public ?DataTarget $target = DataTarget::Shipment;

    public ?string $targetKey = null;

    public ?string $company = null;

    public ?string $server = null;

    public ?string $enterprise = null;

    public bool $documents = false;
<<<<<<< HEAD
=======

    public bool $milestones = false;

>>>>>>> 100bee0b63ee641ea5aa31de49215286328d07fc
    public array $filters = [];

    protected $xml;

    protected $client;

    public function __construct()
    {
        $this->client = Http::withBasicAuth(
            config('cord.eadapter_connection.username'),
            config('cord.eadapter_connection.password')
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

    public function booking(): self
    {
        $this->target = DataTarget::Booking;

        return $this;
    }

    public function shipment(): self
    {
        $this->target = DataTarget::Shipment;

        return $this;
    }

    public function custom(): self
    {
        $this->target = DataTarget::Custom;

        return $this;
    }

    public function find(string $targetKey): self
    {
        $this->targetKey = $targetKey;

        return $this;
    }

    public function documents(): self
    {
        $this->documents = true;

        return $this;
    }

<<<<<<< HEAD
    public function filter($type, $value) : self
=======
    public function milestones(): self
    {
        $this->milestones = true;

        return $this;
    }

    public function filter($type, $value): self
>>>>>>> 100bee0b63ee641ea5aa31de49215286328d07fc
    {
        //Every time this method is called, it will add a new filter to the filters array.
        $this->filters[] = [
            'Type' => $type,
            'Value' => $value,
        ];

        return $this;
    }

    public function get()
    {
        $target = match ($this->target) {
            DataTarget::Shipment, DataTarget::Custom, DataTarget::Booking => new (UniversalShipmentRequest::class)($this),
        };

        if ($this->documents) {
            $target = new (UniversalDocumentRequest::class)($this);
        }

        $this->xml = $target->xml();

        return $this->fetch();
    }

    /**
     * Get the request as XML.
     */
    public function inspect(): string
    {
        $this->get();

        return $this->xml;
    }

    private function checkForErrors()
    {
        if (! $this->targetKey) {
            throw new \Exception('You haven\'t set any target key. Set this using find(). This is usually the shipment number, customs declaration number or booking number.');
        }
    }

    protected function fetch()
    {
        $this->checkForErrors();

        $response = $this->client->send('POST', config('cord.eadapter_connection.url'), [
            'body' => $this->xml,
        ])->body();

        //XML to JSON
        $response = json_decode(json_encode(simplexml_load_string($response)), true);

        //If eAdapter response is not successful, throw exception:
        if ($response['Status'] == 'ERR') {
            if (Request::wantsJson()) {
                abort(response()->json(['error' => $response['ProcessingLog']]), 500);
            } else {
                throw new \Exception('Error from eAdapter: '.$response['ProcessingLog']);
            }
        }

<<<<<<< HEAD
=======
        if ($this->milestones) {
            return $response['Data']['UniversalShipment']['Shipment']['MilestoneCollection'];
        }

>>>>>>> 100bee0b63ee641ea5aa31de49215286328d07fc
        //If eAdapter response is successful, return data:
        return $response['Data'];
    }
}
