<?php

namespace Oliverbj\Cord\Enums;

enum RequestType: string
{
    case UniversalShipmentRequest = 'UniversalShipmentRequest';
    case UniversalDocumentRequest = 'UniversalDocumentRequest';
    case UniversalEvent = 'UniversalEvent';
    case NativeOrganizationRetrieval = 'NativeOrganizationRetrieval';
    case NativeOrganizationUpdate = 'NativeOrganizationUpdate';
    case NativeCompanyRetrieval = 'NativeCompanyRetrieval';
}
