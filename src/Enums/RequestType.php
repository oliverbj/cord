<?php

namespace Oliverbj\Cord\Enums;

enum RequestType: string
{
    case RawXml = 'RawXml';
    case UniversalShipmentRequest = 'UniversalShipmentRequest';
    case UniversalDocumentRequest = 'UniversalDocumentRequest';
    case UniversalEvent = 'UniversalEvent';
    case NativeOrganizationRetrieval = 'NativeOrganizationRetrieval';
    case NativeOrganizationUpdate = 'NativeOrganizationUpdate';
    case NativeOrganizationCreation = 'NativeOrganizationCreation';
    case NativeCompanyRetrieval = 'NativeCompanyRetrieval';
    case NativeStaffCreation = 'NativeStaffCreation';
    case NativeStaffUpdate = 'NativeStaffUpdate';
}
