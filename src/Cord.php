<?php

namespace Oliverbj\Cord;

use Oliverbj\Cord\Enums\DataTarget;

class Cord
{
    protected string $target = DataTarget::Shipment;
    protected string $targetKey;
    protected bool $documents = false;

    public function shipment()
    {
        $this->target = DataTarget::Shipment;

        return $this;
    }

    public function custom()
    {
        $this->target = DataTarget::Custom;

        return $this;
    }

    public function booking()
    {
        $this->target = DataTarget::Booking;

        return $this;
    }

    public function find(string $targetKey)
    {
        $this->targetKey = $targetKey;

        return $this;
    }

    public function documents()
    {
        $this->documents = true;

        return $this;
    }

    protected function fetch()
    {
        $client = new \GuzzleHttp\Client();
        $client->setDefaultOption('headers', [
            'Accept' => 'application/xml',
            'Content-Type' => 'application/xml',
            'Authorization' => 'Basic ' . base64_encode(env('CORD_USERNAME') . ':' . env('CORD_PASSWORD')),
        ]);


    }
}
