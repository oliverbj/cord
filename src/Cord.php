<?php

namespace Oliverbj\Cord;

use Oliverbj\Cord\Enums\DataTarget;
use Illuminate\Support\Facades\Http;
use Request;
use SimpleXMLElement;

class Cord
{
    protected ?DataTarget $target = null;
    protected ?string $targetKey = null;
    protected ?string $company = null;
    protected ?string $server = null;
    protected ?string $enterprise = null;
    protected bool $documents = false;
    protected bool $milestones = false;
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

    //create three methods: company, server, enterprise
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

    public function shipment() : self
    {
        $this->target = DataTarget::Shipment;

        return $this;
    }

    public function custom() : self
    {
        $this->target = DataTarget::Custom;

        return $this;
    }

    public function booking() : self
    {
        $this->target = DataTarget::Booking;

        return $this;
    }

    public function find(string $targetKey) : self
    {
        $this->targetKey = $targetKey;

        return $this;
    }

    public function documents() : self
    {
        $this->documents = true;

        return $this;
    }

    public function milestones() : self
    {
        $this->milestones = true;
        return $this;
    }

    public function get()
    {
        $xml = match($this->target) {
            DataTarget::Shipment, DataTarget::Custom, DataTarget::Booking => file_get_contents(__DIR__ . '/Requests/UniversalShipmentRequest.xml'),
        };

        $xml = str_replace(['{{target}}', '{{targetKey}}'], [$this->target->value, $this->targetKey], $xml);

        $xml = new SimpleXMLElement($xml);

        if($this->enterprise){
            $xml->ShipmentRequest->DataContext->addChild('EnterpriseID', $this->enterprise);
        }

        if($this->server){
            $xml->ShipmentRequest->DataContext->addChild('ServerID', $this->server);
        }

        if($this->company){
            $company = $xml->ShipmentRequest->DataContext->addChild('Company');
            $company->addChild('Code', $this->company);
        }



        return $this->fetch($xml);
    }

    private function checkForErrors()
    {
        if(! $this->target){
            throw new \Exception('You haven\'t set any target module. Set this using the booking(), shipment() or custom() method.');
        }

        if(! $this->targetKey){
            throw new \Exception('You haven\'t set any target key. Set this using find()');
        }
    }

    protected function fetch(SimpleXMLElement $xml)
    {

        $this->checkForErrors();
        $xml = $xml->asXML();
        $xml = substr($xml, strpos($xml, '?'.'>') + 2);

        $response = $this->client->send('POST', config('cord.eadapter_connection.url'), [
            "body" => $xml
        ])->body();

        //XML to JSON
        $response = json_decode(json_encode(simplexml_load_string($response)), true);

        //If eAdapter response is not successful, throw exception:
        if($response['Status'] == "ERR"){
            if(Request::wantsJson())
                abort(response()->json(['error' => $response['ProcessingLog']]), 500);

            else throw new \Exception('Error from eAdapter: ' . $response['ProcessingLog']);
        }

        if($this->milestones)
            return $response['Data']['UniversalShipment']['Shipment']['MilestoneCollection'];

        #if($this->documents)
        #    return $response['Data']['UniversalShipment']['Shipment']['DocumentCollection'];

        //If eAdapter response is successful, return data:
        return $response['Data'];

    }
}
