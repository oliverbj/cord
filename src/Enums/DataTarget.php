<?php

namespace Oliverbj\Cord\Enums;

enum DataTarget: string
{
    case Shipment = 'ForwardingShipment';
    case Booking = 'ForwardingBooking';
    case Custom = 'CustomsDeclaration';
    case Receiveable = 'AccountingInvoice';
    case Organization = 'Organization';
}
