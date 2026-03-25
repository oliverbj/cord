<?php

namespace Oliverbj\Cord\Enums;

enum OperationId: string
{
    case ShipmentGet = 'shipment.get';
    case BookingGet = 'booking.get';
    case CustomGet = 'custom.get';
    case ShipmentDocumentsGet = 'shipment.documents.get';
    case BookingDocumentsGet = 'booking.documents.get';
    case CustomDocumentsGet = 'custom.documents.get';
    case ReceivableDocumentsGet = 'receivable.documents.get';
    case ShipmentEventAdd = 'shipment.event.add';
    case BookingEventAdd = 'booking.event.add';
    case CustomEventAdd = 'custom.event.add';
    case ShipmentDocumentAdd = 'shipment.document.add';
    case BookingDocumentAdd = 'booking.document.add';
    case CustomDocumentAdd = 'custom.document.add';
    case OrganizationQuery = 'organization.query';
    case CompanyQuery = 'company.query';
    case ContainerQuery = 'container.query';
    case StaffQuery = 'staff.query';
    case OrganizationAddressAdd = 'organization.address.add';
    case OrganizationContactAdd = 'organization.contact.add';
    case OrganizationEdiCommunicationAdd = 'organization.edi_communication.add';
    case OrganizationAddressTransfer = 'organization.address.transfer';
    case OrganizationContactTransfer = 'organization.contact.transfer';
    case OrganizationEdiCommunicationTransfer = 'organization.edi_communication.transfer';
    case OrganizationDocumentTrackingTransfer = 'organization.document_tracking.transfer';
    case OrganizationCreate = 'organization.create';
    case StaffCreate = 'staff.create';
    case StaffUpdate = 'staff.update';
    case OneOffQuoteGet = 'one_off_quote.get';
    case OneOffQuoteCreate = 'one_off_quote.create';
    case OneOffQuoteDocumentAdd = 'one_off_quote.document.add';
}
