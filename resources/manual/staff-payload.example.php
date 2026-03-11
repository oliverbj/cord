<?php

return [
    'company' => 'CPH',
    'code' => 'BVO',
    'loginName' => 'user.test',
    'password' => 'change-me',
    'fullName' => 'User Test',
    'addressLine1' => 'Test address',
    'workPhone' => '+111',
    'email' => 'user.test@test.com',
    'homeBranch' => 'TLS',
    'homeDepartment' => 'FES',
    'country' => 'FR',
    'groups' => ['ORGALL', 'OPSALL'],
    'attributes' => [
        'FriendlyName' => 'User Test',
        'Title' => 'Operations Specialist',
        'GlbWorkTime' => [
            '_attributes' => ['Action' => 'Insert'],
            'MondayWorkingHours' => '*******************',
            'TuesdayWorkingHours' => '*******************',
        ],
    ],
];
