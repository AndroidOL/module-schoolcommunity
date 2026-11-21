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

        public function testGroupMessagesAreEnabledOnlyByExplicitYes()
        {
            $this->assertTrue(\schoolCommunityGroupMessagesAllowed(['groupMessages' => 'Y']));
            $this->assertFalse(\schoolCommunityGroupMessagesAllowed(['groupMessages' => 'N']));
            $this->assertFalse(\schoolCommunityGroupMessagesAllowed([]));
            $this->assertSame('Enabled', \schoolCommunityYesNoLabel('Y'));
            $this->assertSame('Disabled', \schoolCommunityYesNoLabel('N'));
        }

        public function testMessagingRequiresBothSendAndReadAccess()
        {
            $this->assertTrue(\schoolCommunityMessagingAccessAllowed(true, true));
            $this->assertFalse(\schoolCommunityMessagingAccessAllowed(true, false));
            $this->assertFalse(\schoolCommunityMessagingAccessAllowed(false, true));
            $this->assertFalse(\schoolCommunityMessagingAccessAllowed(false, false));
        }

        public function testGroupCategoryOptionsExcludeDisabledAndIneligibleCategories()
        {
            $pdo = new class {
                public function select($query)
                {
                    return new class {
                        public function fetchAll()
                        {
                            return [
                                ['categoryID' => '1', 'categoryName' => 'Staff group', 'accessControl' => 8, 'groupMessages' => 'Y'],
                                ['categoryID' => '2', 'categoryName' => 'Staff direct', 'accessControl' => 8, 'groupMessages' => 'N'],
                                ['categoryID' => '3', 'categoryName' => 'Student group', 'accessControl' => 4, 'groupMessages' => 'Y'],
                            ];
                        }
                    };
                }
            };

            $this->assertSame(
                ['1' => 'Staff group', '2' => 'Staff direct'],
                \schoolCommunityCategoryOptions($pdo, 'Staff')
            );
            $this->assertSame(
                ['1' => 'Staff group'],
                \schoolCommunityCategoryOptions($pdo, 'Staff', false, true)
            );
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

        public function testMessagePreviewHandlesHtmlAndTruncatesPlainText()
        {
            $this->assertSame(
                'First line Second line',
                \schoolCommunityMessagePreview('<p>First line</p><p>Second line</p>')
            );
            $this->assertSame(
                'Legacy content',
                \schoolCommunityMessagePreview(base64_encode('<p>Legacy content</p>'))
            );
            $this->assertSame('12345…', \schoolCommunityMessagePreview('123456', 5));
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

        public function testGroupSourceLabelsDistinguishCustomAndClassGroups()
        {
            $this->assertSame('Custom Group', \schoolCommunityGroupSourceLabel('custom'));
            $this->assertSame('Administrative Class', \schoolCommunityGroupSourceLabel('formGroup'));
            $this->assertSame('Course Class', \schoolCommunityGroupSourceLabel('courseClass'));
            $this->assertSame('Gibbon Group', \schoolCommunityGroupSourceLabel('gibbonGroup'));
            $this->assertSame('Custom Group', \schoolCommunityGroupSourceLabel('unknown'));
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
            $this->assertSame('Private note', $rows[0]['preview']);
            $this->assertSame('direct', $rows[0]['threadType']);
            $this->assertSame('1', $rows[0]['threadID']);
            $this->assertFalse($rows[0]['isArchived']);
            $this->assertFalse($rows[0]['isDeleted']);
            $this->assertSame('Ada Current', $rows[0]['senderName']);
            $this->assertSame('Ada Current', $rows[0]['receiverName']);
        }

        public function testGroupTableCarriesMailboxStateAndPreview()
        {
            $table = \schoolCommunityGroupTable([[
                'groupID' => '22',
                'groupName' => 'Staff notices',
                'ownerID' => '0000000010',
                'ownerTitle' => '',
                'ownerPreferredName' => 'Ada',
                'ownerSurname' => 'Current',
                'ownerRoleCategory' => 'Staff',
                'sourceType' => 'custom',
                'unreadCount' => '2',
                'latestTimestamp' => '2026-11-21 10:00:00',
                'latestBody' => '<p>Latest update</p>',
                'archivedAt' => '2026-11-21 11:00:00',
                'deletedAt' => null,
            ]], '10');

            $rows = $table->toArray();
            $this->assertCount(1, $rows);
            $this->assertSame('Latest update', $rows[0]['latestPreview']);
            $this->assertSame('group', $rows[0]['threadType']);
            $this->assertSame('22', $rows[0]['threadID']);
            $this->assertTrue($rows[0]['isArchived']);
            $this->assertFalse($rows[0]['isDeleted']);
            $this->assertTrue($rows[0]['isOwner']);
        }

        public function testFreshInstallAndMigrationDefineSafeGroupMessageDefaults()
        {
            $moduleTables = [];
            $actionRows = [];
            require dirname(__DIR__).'/manifest.php';

            $categoryTable = implode("\n", array_filter($moduleTables, function ($sql) {
                return str_contains($sql, 'CREATE TABLE `message_category`');
            }));
            $this->assertStringContainsString("`groupMessages` ENUM('Y', 'N') NOT NULL DEFAULT 'Y'", $categoryTable);

            $sendAction = implode("\n", array_map(function ($action) {
                return implode("\n", $action);
            }, $actionRows));
            $this->assertStringContainsString('message_recipientChooser.php', $sendAction);

            $sql = [];
            require dirname(__DIR__).'/CHANGEDB.php';
            $migration = '';
            foreach ($sql as $statement) {
                if (($statement[0] ?? '') === '1.4.0') {
                    $migration = (string) ($statement[1] ?? '');
                }
            }
            $this->assertStringContainsString(
                "ADD COLUMN groupMessages ENUM('Y', 'N') NOT NULL DEFAULT 'Y'",
                $migration
            );
        }

        public function testThreadActionButtonsKeepLocalizedTextAndMachineValuesSeparate()
        {
            foreach (['message_view.php', 'message_groupView.php'] as $page) {
                $source = file_get_contents(dirname(__DIR__).'/'.$page);
                $this->assertStringContainsString("->setAttribute('value',", $source);
                $this->assertStringNotContainsString("->setValue('unread')", $source);
                $this->assertStringNotContainsString("->setValue('restoreDelete'", $source);
            }
        }

        public function testSendFailuresHaveVisibleReturnsAndRetryCategory()
        {
            $sendPage = file_get_contents(dirname(__DIR__).'/message_sending.php');
            $process = file_get_contents(dirname(__DIR__).'/message_sendingProcess.php');

            foreach (['error0', 'error1', 'error2', 'error9'] as $returnCode) {
                $this->assertStringContainsString("'{$returnCode}'", $sendPage);
            }
            $this->assertStringContainsString('$postedCategoryID', $process);
            $this->assertStringContainsString('&categoryID=', $process);
        }

        public function testLinkedSourceLookupSuppressesEverySupportedSourceType()
        {
            $source = file_get_contents(dirname(__DIR__).'/moduleFunctions.php');

            $this->assertStringContainsString(
                "sourceType IN ('formGroup', 'courseClass', 'gibbonGroup')",
                $source
            );
            $this->assertStringNotContainsString("sourceType IN ('formGroup', 'courseClass')\"", $source);
        }
    }
}
