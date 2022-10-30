<?php

namespace Oliverbj\Cord\Enums;

enum RequestType: string
{
    case UniversalShipmentRequest = 'UniversalShipmentRequest';
    case UniversalDocumentRequest = 'UniversalDocumentRequest';
    case UniversalEvent = 'UniversalEvent';
}
