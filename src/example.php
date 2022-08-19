<?php

Cord::findShipment('DSV');

Cord::findShipment('SNTG99999999')
    ->company('DK1')
    ->server('ENT')
    ->enterprise('CW');

Cord::findDocuments('SNTG99999999')
    ->typeIsCustoms()
    ->company('DK1')
    ->server('ENT')
    ->enterprise('CW');

Cord::shipments()
    ->find('SNTG99999999')
    ->company('DK1')
    ->server('ENT')
    ->enterprise('CW');

Cord::customs()
    ->find('SNTG99999999')
    ->company('DK1')
    ->server('ENT')
    ->enterprise('CW');
