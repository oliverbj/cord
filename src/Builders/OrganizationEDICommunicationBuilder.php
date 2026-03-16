<?php

namespace Oliverbj\Cord\Builders;

use Oliverbj\Cord\Attributes\StructuredField;

class OrganizationEDICommunicationBuilder
{
    protected array $payload = [];

    #[StructuredField(required: true)]
    public function module(string $value): self
    {
        $this->payload['module'] = $value;

        return $this;
    }

    #[StructuredField(required: true)]
    public function purpose(string $value): self
    {
        $this->payload['purpose'] = $value;

        return $this;
    }

    #[StructuredField(required: true)]
    public function direction(string $value): self
    {
        $this->payload['direction'] = $value;

        return $this;
    }

    #[StructuredField(required: true)]
    public function transport(string $value): self
    {
        $this->payload['transport'] = $value;

        return $this;
    }

    #[StructuredField(required: true)]
    public function destination(string $value): self
    {
        $this->payload['destination'] = $value;

        return $this;
    }

    #[StructuredField(required: true)]
    public function format(string $value): self
    {
        $this->payload['format'] = $value;

        return $this;
    }

    #[StructuredField]
    public function subject(string $value): self
    {
        $this->payload['subject'] = $value;

        return $this;
    }

    #[StructuredField(name: 'publish_milestones')]
    public function publishMilestones(bool $value): self
    {
        $this->payload['publishMilestones'] = $value ? 'true' : 'false';

        return $this;
    }

    #[StructuredField(name: 'sender_van')]
    public function senderVAN(string $value): self
    {
        $this->payload['senderVAN'] = $value;

        return $this;
    }

    #[StructuredField(name: 'receiver_van')]
    public function receiverVAN(string $value): self
    {
        $this->payload['receiverVAN'] = $value;

        return $this;
    }

    #[StructuredField]
    public function filename(string $value): self
    {
        $this->payload['filename'] = $value;

        return $this;
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}
