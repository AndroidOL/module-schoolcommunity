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
    use Gibbon\Data\Validator;

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

        public function testLegacyAndCurrentMessageBodiesAreSanitizedForDisplay()
        {
            $container = new class {
                public function get($class)
                {
                    return new Validator('', '', '');
                }
            };

            $body = 'Hello<script>alert(1)</script>';
            $legacy = base64_encode('<p>Legacy</p><script>alert(1)</script>');

            $this->assertSame('Helloalert(1)', \schoolCommunityBodyForDisplay($container, $body));
            $this->assertSame('Legacyalert(1)', \schoolCommunityBodyForDisplay($container, $legacy));
        }

        public function testSelfMessagingCanIncludeCurrentUserWithoutLosingAudienceFiltering()
        {
            $pdo = new class {
                public function select($query)
                {
                    return new class {
                        public function fetchAll()
                        {
                            return [
                                ['gibbonPersonID' => '10', 'title' => 'Ms', 'preferredName' => 'Ada', 'surname' => 'Current', 'username' => 'ada', 'roleCategory' => 'Staff'],
                                ['gibbonPersonID' => '11', 'title' => 'Mr', 'preferredName' => 'Ben', 'surname' => 'Staff', 'username' => 'ben', 'roleCategory' => 'Staff'],
                                ['gibbonPersonID' => '12', 'title' => 'Mrs', 'preferredName' => 'Cara', 'surname' => 'Parent', 'username' => 'cara', 'roleCategory' => 'Parent'],
                                ['gibbonPersonID' => '13', 'title' => 'Sam', 'preferredName' => 'Drew', 'surname' => 'Student', 'username' => 'drew', 'roleCategory' => 'Student'],
                            ];
                        }
                    };
                }
            };

            $withoutSelf = \schoolCommunityPersonOptions($pdo, '10', 10, false);
            $withSelf = \schoolCommunityPersonOptions($pdo, '10', 10, true);

            $this->assertArrayNotHasKey('10', $withoutSelf);
            $this->assertArrayHasKey('11', $withoutSelf);
            $this->assertArrayHasKey('12', $withoutSelf);
            $this->assertArrayNotHasKey('13', $withoutSelf);
            $this->assertArrayHasKey('10', $withSelf);
            $this->assertStringContainsString('Self (visible only to you)', $withSelf['10']);
        }

        public function testGroupAccessRequiresCurrentMembershipAudienceAndOwnerWhenRequested()
        {
            $group = [
                'groupMemberPersonID' => '0000000010',
                'ownerID' => '0000000010',
                'accessControl' => 10,
            ];

            $this->assertTrue(\schoolCommunityGroupAccessAllowed($group, '10', 'Staff'));
            $this->assertTrue(\schoolCommunityGroupAccessAllowed($group, '10', 'Staff', true));
            $this->assertFalse(\schoolCommunityGroupAccessAllowed($group, '10', 'Student'));
            $this->assertFalse(\schoolCommunityGroupAccessAllowed($group, '11', 'Staff'));

            $group['ownerID'] = '11';
            $this->assertFalse(\schoolCommunityGroupAccessAllowed($group, '10', 'Staff', true));
        }

        public function testZeroPaddedDatabaseIDsMatchSessionIDs()
        {
            $this->assertTrue(\schoolCommunitySamePersonID('0000000010', '10'));
            $this->assertTrue(\schoolCommunitySamePersonID(10, '0000000010'));
            $this->assertFalse(\schoolCommunitySamePersonID('0000000010', '11'));
            $this->assertFalse(\schoolCommunitySamePersonID('student', '10'));
        }

        public function testGroupAudienceExcludesInactiveAndIneligibleMembers()
        {
            $audience = \schoolCommunityGroupAudience([
                ['gibbonPersonID' => '10', 'status' => 'Full', 'roleCategory' => 'Staff'],
                ['gibbonPersonID' => '11', 'status' => 'Full', 'roleCategory' => 'Parent'],
                ['gibbonPersonID' => '12', 'status' => 'Full', 'roleCategory' => 'Student'],
                ['gibbonPersonID' => '13', 'status' => 'Left', 'roleCategory' => 'Staff'],
            ], 10);

            $this->assertSame(['10', '11'], array_column($audience['eligible'], 'gibbonPersonID'));
            $this->assertSame(['12', '13'], array_column($audience['excluded'], 'gibbonPersonID'));
        }

        public function testSelfMessageAppearsOnceAndRemainsUnreadUntilOpened()
        {
            $table = \schoolCommunityMessageTable([[
                'messageID' => '1',
                'senderID' => '0000000010',
                'receiverID' => '0000000010',
                'senderTitle' => '',
                'senderPreferredName' => 'Ada',
                'senderSurname' => 'Current',
                'receiverTitle' => '',
                'receiverPreferredName' => 'Ada',
                'receiverSurname' => 'Current',
                'priority' => null,
                'title' => 'Reminder',
                'body' => 'Private note',
                'readTimestamp' => null,
            ]], '10');

            $rows = $table->toArray();
            $this->assertCount(1, $rows);
            $this->assertSame('Sent / Received', $rows[0]['direction']);
            $this->assertTrue($rows[0]['isUnread']);
            $this->assertSame('Ada Current', $rows[0]['otherPerson']);
        }
    }
}
