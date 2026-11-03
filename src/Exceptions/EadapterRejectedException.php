<?php

namespace Oliverbj\Cord\Exceptions;

use Exception;

/**
 * eAdapter answered with status ERR: the message was rejected, as opposed to a timeout or transport failure.
 */
class EadapterRejectedException extends Exception
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $processingLog,
        public readonly ?string $messageNumber,
    ) {
        parent::__construct($processingLog ?? "eAdapter returned status {$status}.");
    }
}
