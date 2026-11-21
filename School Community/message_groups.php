<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;

require_once __DIR__.'/moduleFunctions.php';

$page->breadcrumbs
    ->add(schoolCommunityT('Messages'), 'message_reading.php')
    ->add(schoolCommunityT('Message Groups'));

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_reading.php')) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$page->return->addReturns([
    'success0' => schoolCommunityT('Message group created.'),
    'success1' => schoolCommunityT('Group member added.'),
    'success2' => schoolCommunityT('Group member removed.'),
    'success6' => schoolCommunityT('Message group marked unread.'),
    'error0'   => schoolCommunityT('You do not have permission to perform this action.'),
    'error1'   => schoolCommunityT('The submitted data is invalid.'),
    'error2'   => schoolCommunityT('Unable to save the message group.'),
    'error9'   => schoolCommunityT('Your session expired. Refresh the page and try again.'),
]);

$currentPersonID = (string) $session->get('gibbonPersonID');
$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$search = is_scalar($_GET['search'] ?? null) ? trim((string) $_GET['search']) : '';
$archived = ($_GET['archived'] ?? 'N') === 'Y';

$form = Form::create('schoolCommunityGroupSearch', $session->get('absoluteURL').'/index.php', 'get');
$form->setTitle(schoolCommunityT('Search Message Groups'));
$form->setClass('noIntBorder w-full');
$form->addHiddenValue('q', '/modules/School Community/message_groups.php');
$row = $form->addRow();
    $row->addLabel('search', schoolCommunityT('Search'))->description(schoolCommunityT('Group, message or reply text'));
    $row->addTextField('search')->setValue($search)->maxLength(100);
$row = $form->addRow();
    $row->addLabel('archived', schoolCommunityT('Archive View'));
    $row->addCheckbox('archived')->setValue('Y')->checked($archived);
$row = $form->addRow();
    $row->addSearchSubmit($session, schoolCommunityT('Clear search'));
$page->write($form->getOutput());

$groups = $pdo->select(
    "SELECT message_group.*, message_category.categoryName, message_category.accessControl,
            member.gibbonPersonID AS groupMemberPersonID,
            owner.title AS ownerTitle, owner.preferredName AS ownerPreferredName,
            owner.surname AS ownerSurname, ownerRole.category AS ownerRoleCategory,
            (SELECT COUNT(*) FROM message_group_member AS counted
             WHERE counted.groupID=message_group.groupID) AS memberCount,
            (SELECT MAX(latest.sendTimestamp)
             FROM message_group_message AS latest
             JOIN message_group_message_recipient AS latestRecipient
                ON latestRecipient.groupMessageID=latest.groupMessageID
               AND latestRecipient.gibbonPersonID=:currentPersonID
             WHERE latest.groupID=message_group.groupID) AS latestTimestamp,
            (SELECT COUNT(*)
             FROM message_group_message AS unread
             JOIN message_group_message_recipient AS recipient
                ON recipient.groupMessageID=unread.groupMessageID
               AND recipient.gibbonPersonID=:currentPersonID
             LEFT JOIN message_group_read_receipt AS receipt
                ON receipt.groupMessageID=unread.groupMessageID
               AND receipt.gibbonPersonID=:currentPersonID
             WHERE unread.groupID=message_group.groupID
               AND unread.senderID<>:currentPersonID
               AND receipt.groupReadReceiptID IS NULL) AS unreadCount
     FROM message_group
     JOIN message_category ON message_category.categoryID=message_group.categoryID
     LEFT JOIN message_group_member AS member
        ON member.groupID=message_group.groupID
       AND member.gibbonPersonID=:currentPersonID
     JOIN gibbonPerson AS owner ON owner.gibbonPersonID=message_group.ownerID
     JOIN gibbonRole AS ownerRole ON ownerRole.gibbonRoleID=owner.gibbonRoleIDPrimary
     LEFT JOIN message_thread_state AS threadState
        ON threadState.threadType='group'
       AND threadState.threadID=message_group.groupID
       AND threadState.gibbonPersonID=:currentPersonID
     WHERE ((message_group.sourceType='custom' AND member.gibbonPersonID IS NOT NULL)
        OR message_group.sourceType IN ('formGroup', 'courseClass'))
       AND (".($archived ? 'threadState.archivedAt IS NOT NULL' : 'threadState.archivedAt IS NULL').")
       AND (:search='' OR message_group.groupName LIKE :search
            OR EXISTS (
                SELECT 1
                FROM message_group_message AS searchedMessage
                JOIN message_group_message_recipient AS searchedRecipient
                  ON searchedRecipient.groupMessageID=searchedMessage.groupMessageID
                 AND searchedRecipient.gibbonPersonID=:currentPersonID
                WHERE searchedMessage.groupID=message_group.groupID
                  AND (searchedMessage.title LIKE :search OR searchedMessage.body LIKE :search)
            )
            OR EXISTS (
                SELECT 1
                FROM message_group_reply AS searchedReply
                JOIN message_group_message AS replyMessage
                  ON replyMessage.groupMessageID=searchedReply.groupMessageID
                JOIN message_group_message_recipient AS replyRecipient
                  ON replyRecipient.groupMessageID=replyMessage.groupMessageID
                 AND replyRecipient.gibbonPersonID=:currentPersonID
                WHERE replyMessage.groupID=message_group.groupID
                  AND searchedReply.body LIKE :search
            ))
     ORDER BY COALESCE(latestTimestamp, message_group.createdAt) DESC, message_group.groupName",
    ['currentPersonID' => $currentPersonID, 'search' => '%'.$search.'%']
)->fetchAll(PDO::FETCH_ASSOC);

$groups = array_values(array_filter($groups, function ($group) use ($pdo, $currentPersonID, $roleCategory) {
    if (($group['sourceType'] ?? 'custom') !== 'custom') {
        $sourceMembers = schoolCommunityLinkedSourceMembers($pdo, $group['sourceType'], $group['sourceID'] ?? '');
        $sourceAllowed = false;
        foreach ($sourceMembers as $sourceMember) {
            if (schoolCommunitySamePersonID($sourceMember['gibbonPersonID'], $currentPersonID)) {
                $sourceAllowed = true;
                break;
            }
        }
        if (!$sourceAllowed) {
            return false;
        }
        $group['groupMemberPersonID'] = $currentPersonID;
        $group['memberCount'] = count($sourceMembers);
    }

    return schoolCommunityGroupAccessAllowed($group, $currentPersonID, $roleCategory);
}));

$table = DataTable::create('schoolCommunityMessageGroups');
$table->setTitle(schoolCommunityT('Message Groups'));
$table->setDescription(schoolCommunityT('Groups you belong to. A group only accepts members whose role is allowed by its category.'));
$table->addMetaData('allowHTML', ['groupName', 'unreadLabel']);

$table->addColumn('groupName', schoolCommunityT('Group'))->format(function ($group) {
    $unread = (int) $group['unreadCount'];
    $label = '<a href="'.htmlPrep($group['url']).'">'.htmlPrep($group['groupName']).'</a>';
    if ($unread > 0) {
        $label .= ' <span class="schoolCommunityUnreadBadge">'.$unread.'</span>';
    }

    return $label;
});
$table->addColumn('categoryName', schoolCommunityT('Category'));
$table->addColumn('sourceLabel', schoolCommunityT('Source'));
$table->addColumn('ownerName', schoolCommunityT('Owner'));
$table->addColumn('memberCount', schoolCommunityT('Members'));
$table->addColumn('latestTimestamp', schoolCommunityT('Latest Message'))->format(function ($group) {
    return empty($group['latestTimestamp']) ? schoolCommunityT('No messages yet') : Format::dateTime($group['latestTimestamp']);
});

if (isActionAccessible($guid, $connection2, '/modules/School Community/message_sending.php')) {
    $table->addHeaderAction('createGroup', schoolCommunityT('Create Group'))
        ->setURL('/modules/School Community/message_groupAdding.php')
        ->setIcon('plus')
        ->displayLabel();
}

$page->write($table->render(schoolCommunityGroupTable($groups, $currentPersonID)));
