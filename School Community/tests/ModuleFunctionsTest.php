<?php

namespace {
    if (!function_exists('__')) {
        function __($text)
        {
            return $text;
        }
    }
    if (!function_exists('htmlPrep')) {
        function htmlPrep($text)
        {
            return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

        public function testMetadataTagsFollowTheCurrentLocale()
        {
            $this->assertTrue(\schoolCommunityUsesWideMetadataTags('zh_CN'));
            $this->assertFalse(\schoolCommunityUsesWideMetadataTags('en_GB'));
            $this->assertSame(
                '<span class="tag message" title="">【仅您可见】</span>',
                \schoolCommunityMetadataTag('仅您可见', 'zh_CN')
            );
            $this->assertSame(
                '<span class="tag message" title="">[Visible only to you]</span>',
                \schoolCommunityMetadataTag('Visible only to you', 'en_GB')
            );
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
            $this->assertSame('1.4.6', $version);

            $categoryTable = implode("\n", array_filter($moduleTables, function ($sql) {
                return str_contains($sql, 'CREATE TABLE `message_category`');
            }));
            $this->assertStringContainsString("`groupMessages` ENUM('Y', 'N') NOT NULL DEFAULT 'Y'", $categoryTable);

            $sendAction = implode("\n", array_map(function ($action) {
                return implode("\n", $action);
            }, $actionRows));
            $this->assertStringNotContainsString('message_recipientChooser.php', $sendAction);
            $manifestUrlLists = array_column($actionRows, 'URLList', 'name');

            $sql = [];
            require dirname(__DIR__).'/CHANGEDB.php';
            $migrations = [];
            foreach ($sql as $statement) {
                $migrations[(string) ($statement[0] ?? '')] = (string) ($statement[1] ?? '');
            }
            $this->assertStringContainsString(
                'ADD COLUMN groupMessages',
                $migrations['1.4.0']
            );
            foreach (['1.4.0', '1.4.1', '1.4.2'] as $version) {
                $this->assertStringContainsString(
                    "='message_sending.php'",
                    $migrations[$version]
                );
                $this->assertStringContainsString(
                    "='message_reading.php'",
                    $migrations[$version]
                );
            }
            $this->assertStringContainsString('message_groupCompose.php', $migrations['1.4.5']);
            $this->assertStringContainsString('message_groupManage.php', $migrations['1.4.5']);
            $this->assertStringContainsString('message_freeRecipients.php', $migrations['1.4.6']);
            $this->assertStringContainsString('message_freeRecipientsProcess.php', $migrations['1.4.6']);
            $this->assertStringNotContainsString('message_groupTargetProcess.php', $migrations['1.4.6']);
            $this->assertStringContainsString('INSERT INTO gibbonAction', $migrations['1.4.6']);
            $this->assertStringContainsString("'Manage Message Groups'", $migrations['1.4.6']);
            $this->assertStringContainsString('INSERT INTO gibbonPermission', $migrations['1.4.6']);
            $this->assertStringContainsString("entryURL='message_categorySetting.php'", $migrations['1.4.6']);
            $this->assertStringContainsString(
                'information_schema.COLUMNS',
                $migrations['1.4.1']
            );
            $this->assertStringContainsString(
                'PREPARE schoolCommunityAddGroupMessagesStmt',
                $migrations['1.4.1']
            );
            $this->assertStringContainsString(
                "WHERE `entryURL`='message_sending.php'",
                $migrations['1.4.1']
            );
            $this->assertStringContainsString(
                "WHERE `entryURL`='message_reading.php'",
                $migrations['1.4.1']
            );
        }

        public function testThreadActionsUseGibbonModalConfirmations()
        {
            foreach (['message_view.php', 'message_groupView.php'] as $page) {
                $source = file_get_contents(dirname(__DIR__).'/'.$page);
                $this->assertStringContainsString('message_threadConfirm.php', $source);
                $this->assertStringContainsString('->modalWindow()', $source);
                $this->assertStringNotContainsString('confirm(', $source);
            }
        }

        public function testDeleteConfirmationsUseOfficialDeleteForm()
        {
            foreach (['message_categoryDelete.php', 'message_threadConfirm.php'] as $page) {
                $source = file_get_contents(dirname(__DIR__).'/'.$page);
                $this->assertStringContainsString('DeleteForm::createForm', $source);
                $this->assertStringNotContainsString('confirm(', $source);
            }
        }

        public function testSendFailuresHaveVisibleReturnsAndRetryCategory()
        {
            $sendPage = file_get_contents(dirname(__DIR__).'/message_sending.php');
            $process = file_get_contents(dirname(__DIR__).'/message_sendingProcess.php');
            $script = file_get_contents(dirname(__DIR__).'/js/module.js');

            foreach (['error0', 'error1', 'error2', 'error9'] as $returnCode) {
                $this->assertStringContainsString("'{$returnCode}'", $sendPage);
            }
            $this->assertStringContainsString('$postedCategoryID', $process);
            $this->assertStringContainsString('&categoryID=', $process);
            $this->assertStringContainsString("addSelectPerson('recipientID')", $sendPage);
            $this->assertStringNotContainsString('chooseRecipient', $sendPage);
            $this->assertStringContainsString("addToggle('groupMessage')->setYesNo()", $sendPage);
            $this->assertStringNotContainsString('addYesNo(\'groupMessage\')', $sendPage);
            $this->assertStringContainsString("addSelect('targetType')", $sendPage);
            $this->assertStringContainsString("'formGroup', 'courseClass', 'existing'", $sendPage);
            $this->assertStringContainsString("addSearchSelect('groupTarget'.ucfirst(\$type))", $sendPage);
            $this->assertStringContainsString('message_groupCompose.php', file_get_contents(dirname(__DIR__).'/message_sending.php'));
            $compose = file_get_contents(dirname(__DIR__).'/message_groupCompose.php');
            $this->assertStringContainsString('message_freeRecipients.php', $compose);
            $this->assertStringContainsString('modalWindow(700, 520)', $compose);
            $this->assertStringNotContainsString('selectMultiple()', $compose);
            $this->assertStringContainsString('schoolCommunityUpdateFreeRecipientCount', $script);
            $this->assertStringContainsString('schoolCommunitySetMessageMode', $script);
            $this->assertStringContainsString('schoolCommunitySetGroupTargetType', $script);
            $this->assertStringContainsString("event.target.id === 'groupMessage'", $script);
            $this->assertStringNotContainsString('.submit(', $script);
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

        public function testTranslationMetadataMatchesInstalledModuleVersion()
        {
            $po = file_get_contents(dirname(__DIR__).'/i18n/zh_CN/LC_MESSAGES/School Community.po');

            $this->assertStringContainsString('Project-Id-Version: School Community 1.4.6', $po);
            $this->assertStringNotContainsString('Project-Id-Version: School Community 1.4.0', $po);
        }

        public function testDetailPagesUseOfficialGibbonComponents()
        {
            $view = file_get_contents(dirname(__DIR__).'/message_view.php');
            $groupView = file_get_contents(dirname(__DIR__).'/message_groupView.php');

            // Message detail presents the subject and body as the focus, not a cramped metadata table.
            $this->assertStringContainsString('<section class="w-full', $view);
            $this->assertStringContainsString('schoolCommunityBodyForDisplay(', $view);
            $this->assertStringContainsString('Format::tag(', $view);
            $this->assertStringNotContainsString("addColumn('senderName'", $view);
            $this->assertStringNotContainsString('grid grid-cols-1', $view);
            $this->assertStringContainsString('schoolCommunityMetadataTag(', $groupView);
        }

        public function testManageMessageGroupsIsASiblingSettingsAction()
        {
            $actionRows = [];
            require dirname(__DIR__).'/manifest.php';

            $settings = array_values(array_filter($actionRows, function ($action) {
                return $action['category'] === 'Settings';
            }));
            $names = array_column($settings, 'name');
            $this->assertContains('Manage Message Categories', $names);
            $this->assertContains('Manage Message Groups', $names);

            $categoriesIndex = array_search('Manage Message Categories', $names, true);
            $groupsIndex = array_search('Manage Message Groups', $names, true);
            // Menu orders Settings actions alphabetically by name, so Groups sits below Categories.
            $this->assertGreaterThan($categoriesIndex, $groupsIndex);

            $groups = $settings[$groupsIndex];
            $this->assertSame('message_groupManage.php', $groups['entryURL']);
            $this->assertSame('Y', $groups['menuShow']);
            $this->assertStringContainsString('message_groupMembers.php', $groups['URLList']);

            $categories = $settings[$categoriesIndex];
            $this->assertStringNotContainsString('message_groupManage.php', $categories['URLList']);
            $this->assertStringNotContainsString('message_groupMembers.php', $categories['URLList']);

            // Categories page no longer exposes group management as a sub-page button.
            $this->assertStringNotContainsString(
                'message_groupManage.php',
                file_get_contents(dirname(__DIR__).'/message_categorySetting.php')
            );
        }

        public function testLinkedGroupMembershipIsNeverSynchronizedFromSource()
        {
            $functions = file_get_contents(dirname(__DIR__).'/moduleFunctions.php');
            $this->assertStringNotContainsString('SyncLinkedGroupMembers', $functions);
            $this->assertStringNotContainsString('LinkedSourceAllowsPerson', $functions);
            $this->assertStringNotContainsString('DELETE FROM message_group_member', $functions);
            $this->assertStringContainsString('function schoolCommunityAddLinkedGroupMembers', $functions);

            $process = file_get_contents(dirname(__DIR__).'/message_groupProcess.php');
            $this->assertStringContainsString('schoolCommunityAddLinkedGroupMembers', $process);
            $this->assertStringNotContainsString("(\$group['sourceType'] ?? 'custom') !== 'custom'", $process);

            $members = file_get_contents(dirname(__DIR__).'/message_groupMembers.php');
            $this->assertStringNotContainsString('isLinkedGroup', $members);
            $this->assertStringNotContainsString('synchronized', $members);

            $manage = file_get_contents(dirname(__DIR__).'/message_groupManage.php');
            $this->assertStringNotContainsString('synchronize membership from their source', $manage);

            $groupsList = file_get_contents(dirname(__DIR__).'/message_groups.php');
            $this->assertStringNotContainsString('linkedMemberCache', $groupsList);
            $this->assertStringNotContainsString('LinkedSourceMembers', $groupsList);
            $this->assertStringContainsString('WHERE member.gibbonPersonID IS NOT NULL', $groupsList);
        }

        public function testClassTargetLabelsCarryNoAdministrativeOrCoursePrefix()
        {
            $functions = file_get_contents(dirname(__DIR__).'/moduleFunctions.php');
            $this->assertStringNotContainsString('Administrative Class: %s', $functions);
            $this->assertStringNotContainsString('Course Class: %s.%s', $functions);
            $this->assertStringContainsString("\$options['formGroup:'.\$formGroup['gibbonFormGroupID']] = \$formGroup['name'];", $functions);
        }
    }
}
