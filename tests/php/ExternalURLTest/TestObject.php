<?php

namespace Fromholdio\ExternalURLField\Tests\ExternalURLTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Test-only record mirroring the README usage: an `ExternalURL` field declared
 * through `$db`, so templates and `obj()` cast it through the ORM schema.
 * Records are only ever held in memory; nothing is written to the database.
 */
class TestObject extends DataObject implements TestOnly
{
    private static $table_name = 'ExternalURLTest_TestObject';

    private static $db = [
        'Website' => 'ExternalURL',
    ];
}
