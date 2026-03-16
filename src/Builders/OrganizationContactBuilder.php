<?php

namespace Oliverbj\Cord\Builders;

use Oliverbj\Cord\Attributes\StructuredField;

class OrganizationContactBuilder
{
    protected array $payload = [];

    #[StructuredField(required: true)]
    public function name(string $value): self
    {
        $this->payload['name'] = $value;

        return $this;
    }

    #[StructuredField(required: true)]
    public function email(string $value): self
    {
        $this->payload['email'] = $value;

        return $this;
    }

    #[StructuredField]
    public function active(bool $value): self
    {
        $this->payload['active'] = $value ? 'true' : 'false';

        return $this;
    }

    #[StructuredField(name: 'notify_mode')]
    public function notifyMode(string $value): self
    {
        $this->payload['notifyMode'] = $value;

        return $this;
    }

    #[StructuredField]
    public function title(string $value): self
    {
        $this->payload['title'] = $value;

        return $this;
    }

    #[StructuredField]
    public function gender(string $value): self
    {
        $this->payload['gender'] = $value;

        return $this;
    }

    #[StructuredField]
    public function language(string $code): self
    {
        $this->payload['language'] = $code;

        return $this;
    }

    #[StructuredField]
    public function phone(string $value): self
    {
        $this->payload['phone'] = $value;

        return $this;
    }

    #[StructuredField(name: 'mobile_phone')]
    public function mobilePhone(string $value): self
    {
        $this->payload['mobilePhone'] = $value;

        return $this;
    }

    #[StructuredField(name: 'home_phone')]
    public function homePhone(string $value): self
    {
        $this->payload['homePhone'] = $value;

        return $this;
    }

    #[StructuredField(name: 'attachment_type')]
    public function attachmentType(string $value): self
    {
        $this->payload['attachmentType'] = $value;

        return $this;
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}
