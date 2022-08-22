<?php

Cord::shipment()
    ->find('SNTG99999999');

Cord::custom()
    ->find('SNTG99999999')
    ->company('DK1')
    ->server('ENT')
    ->enterprise('CW');

Cord::shipment()
    ->find('SNTG99999999')
    ->filter('DocumentType', 'ARN')
    ->documents();
