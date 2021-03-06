<?php

namespace SilverStripe\ORM\Tests\DataQueryTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class ObjectA extends DataObject implements TestOnly
{
    private static $table_name = 'DataQueryTest_A';

    private static $db = [
        'Name' => 'Varchar',
    ];

    private static $has_one = [
        'TestC' => ObjectC::class,
    ];
}
