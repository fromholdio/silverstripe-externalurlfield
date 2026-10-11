<?php

namespace Fromholdio\ExternalURLField\Tests\ExternalURLFieldFormTest;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Test-only record edited through {@see TestController}, written to the test
 * database so each submission's effect on the saved url can be read back.
 */
class TestRecord extends DataObject implements TestOnly
{
    private static $table_name = 'ExternalURLFieldFormTest_TestRecord';

    private static $db = [
        'Website' => 'ExternalURL',
    ];
}
