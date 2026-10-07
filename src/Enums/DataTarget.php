<?php

namespace Oliverbj\Cord\Enums;

enum DataTarget: string
{
    case Shipment = 'ForwardingShipment';
    case Booking = 'ForwardingBooking';
    case Consol = 'ForwardingConsol';
    case Custom = 'CustomsDeclaration';
    case DocManager = 'DocManager';
    case OneOffQuote = 'OneOffQuote';
    case Receiveable = 'AccountingInvoice';
    case Organization = 'Organization';
    case Container = 'Container';
    case Staff = 'Staff';
}
