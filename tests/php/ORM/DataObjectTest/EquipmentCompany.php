<?php

namespace SilverStripe\ORM\Tests\DataObjectTest;

use SilverStripe\Dev\TestOnly;

class EquipmentCompany extends Company implements TestOnly
{
    private static $table_name = 'DataObjectTest_EquipmentCompany';

    private static $many_many = [
        'SponsoredTeams' => Team::class,
        'EquipmentCustomers' => Team::class
    ];

    private static $many_many_extraFields = [
        'SponsoredTeams' => [
            'SponsorFee' => 'Int'
        ]
    ];
}
