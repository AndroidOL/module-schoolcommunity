<?php

namespace {
    if (!function_exists('__')) {
        function __($text)
        {
            return $text;
        }
    }
}

namespace Gibbon\Module\SchoolCommunity\Tests {
    use PHPUnit\Framework\TestCase;

    require_once dirname(__DIR__).'/moduleFunctions.php';

    final class ModuleFunctionsTest extends TestCase
    {
        public function testPermissionMaskRoundTrip()
        {
            $value = \schoolCommunityPermissionValue([
                'staff' => 'Y',
                'student' => 'N',
                'parent' => 'Y',
                'other' => 'N',
            ]);

            $this->assertSame(10, $value);
            $this->assertTrue(\schoolCommunityCategoryAllows($value, 'Staff'));
            $this->assertFalse(\schoolCommunityCategoryAllows($value, 'Student'));
            $this->assertTrue(\schoolCommunityCategoryAllows($value, 'Parent'));
            $this->assertFalse(\schoolCommunityCategoryAllows($value, 'Other'));
        }

        public function testPriorityMapping()
        {
            $this->assertSame(1, \schoolCommunityPriorityToDatabase('h'));
            $this->assertNull(\schoolCommunityPriorityToDatabase('m'));
            $this->assertSame(0, \schoolCommunityPriorityToDatabase('l'));
            $this->assertSame('High', \schoolCommunityPriorityLabel(1));
            $this->assertSame('Normal', \schoolCommunityPriorityLabel(null));
            $this->assertSame('Notice', \schoolCommunityPriorityLabel(0));
        }
    }
}
