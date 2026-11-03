<?php

namespace Oliverbj\Cord\Builders;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Oliverbj\Cord\Attributes\StructuredField;

class TransportLegBuilder
{
    protected array $payload = [];

    #[StructuredField(required: true)]
    public function order(int $value): self
    {
        $this->payload['order'] = $value;

        return $this;
    }

    #[StructuredField(required: true)]
    public function type(string $value): self
    {
        $this->payload['type'] = $value;

        return $this;
    }

    #[StructuredField(required: true)]
    public function mode(string $value): self
    {
        $this->payload['mode'] = $value;

        return $this;
    }

    #[StructuredField(name: 'port_of_loading', required: true)]
    public function portOfLoading(string $code): self
    {
        $this->payload['portOfLoading'] = $code;

        return $this;
    }

    #[StructuredField(name: 'port_of_discharge', required: true)]
    public function portOfDischarge(string $code): self
    {
        $this->payload['portOfDischarge'] = $code;

        return $this;
    }

    #[StructuredField(name: 'estimated_departure')]
    public function estimatedDeparture(string $date): self
    {
        $this->payload['estimatedDeparture'] = $this->normalizeDate('estimated_departure', $date);

        return $this;
    }

    #[StructuredField(name: 'estimated_arrival')]
    public function estimatedArrival(string $date): self
    {
        $this->payload['estimatedArrival'] = $this->normalizeDate('estimated_arrival', $date);

        return $this;
    }

    #[StructuredField]
    public function vessel(string $name): self
    {
        $this->payload['vessel'] = $name;

        return $this;
    }

    #[StructuredField(name: 'voyage_flight_no')]
    public function voyageFlightNo(string $number): self
    {
        $this->payload['voyageFlightNo'] = $number;

        return $this;
    }

    #[StructuredField]
    public function carrier(string $organizationCode): self
    {
        $this->payload['carrier'] = $organizationCode;

        return $this;
    }

    public function toArray(): array
    {
        return $this->payload;
    }

    private function normalizeDate(string $name, string $value): string
    {
        try {
            return Carbon::parse($value)->format('Y-m-d\TH:i:s');
        } catch (\Throwable) {
            throw ValidationException::withMessages([$name => ["The {$name} field must be a valid date."]]);
        }
    }
}
