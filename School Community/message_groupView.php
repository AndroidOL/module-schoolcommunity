<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\Action;

require_once __DIR__.'/moduleFunctions.php';

$page->breadcrumbs
    ->add(schoolCommunityT('Message Groups'), 'message_groups.php')
    ->add(schoolCommunityT('View Group'));

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_reading.php')) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$page->return->addReturns([
    'success0' => schoolCommunityT('Group message sent.'),
    'success1' => schoolCommunityT('Group reply posted.'),
    'success3' => schoolCommunityT('Message group created.'),
    'success4' => schoolCommunityT('Message group archived.'),
    'success5' => schoolCommunityT('Message group restored.'),
    'success6' => schoolCommunityT('Message group marked unread.'),
    'success7' => schoolCommunityT('Message group moved to Trash.'),
    'success8' => schoolCommunityT('Message group restored from Trash.'),
    'error0'   => schoolCommunityT('You do not have permission to perform this action.'),
    'error1'   => schoolCommunityT('The submitted data is invalid.'),
    'error2'   => schoolCommunityT('Unable to save the group message.'),
    'error3'   => schoolCommunityT('The recipient list changed. Review the updated list and send again.'),
    'error4'   => schoolCommunityT('A message group already exists for this source.'),
    'error9'   => schoolCommunityT('Your session expired. Refresh the page and try again.'),
]);

$groupID = is_scalar($_GET['groupID'] ?? null) ? trim((string) $_GET['groupID']) : '';
$currentPersonID = (string) $session->get('gibbonPersonID');
$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$canSend = schoolCommunityMessagingAccessAllowed(
    isActionAccessible($guid, $connection2, '/modules/School Community/message_sending.php'),
    isActionAccessible($guid, $connection2, '/modules/School Community/message_reading.php')
);
$group = $groupID !== '' ? schoolCommunityAccessibleGroup($pdo, $groupID, $currentPersonID, $roleCategory) : null;

if (empty($group)) {
    $page->addError(schoolCommunityT('The specified record does not exist.'));
    return;
}

$threadState = schoolCommunityThreadState($pdo, 'group', $groupID, $currentPersonID);
$isArchived = !empty($threadState['archivedAt']);
$isDeleted = !empty($threadState['deletedAt']);
$groupMessagesEnabled = schoolCommunityGroupMessagesAllowed($group);
$threadActions = Form::create(
    'schoolCommunityGroupThread',
    $session->get('absoluteURL').'/modules/School Community/message_threadProcess.php',
    'post'
);
$threadActions->setTitle(htmlPrep($group['groupName']));
$threadActions->setDescription(schoolCommunityT('Group conversation'));
$threadActions->addHiddenValue('threadType', 'group');
$threadActions->addHiddenValue('threadID', $groupID);

$ownerName = Format::name(
    $group['ownerTitle'],
    $group['ownerPreferredName'],
    $group['ownerSurname'],
    $group['ownerRoleCategory'] ?? 'Staff',
    true
);

$row = $threadActions->addRow();
    $i18nCode = (string) $session->get(['i18n', 'code']);
    $labelSeparator = schoolCommunityUsesWideMetadataTags($i18nCode) ? '：' : ': ';
    $column = $row->addColumn();
    $column->addContent(
        '<div class="flex flex-wrap items-center gap-2 text-sm text-gray-800">'
        .'<span><span class="font-semibold">'.schoolCommunityT('Category').$labelSeparator.'</span>'.htmlPrep($group['categoryName']).'</span>'
        .schoolCommunityMetadataTag(
            schoolCommunityT('Group Messages').$labelSeparator.schoolCommunityYesNoLabel($group['groupMessages']),
            $i18nCode
        )
        .'</div>'
        .'<div class="mt-2 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-gray-800">'
        .'<span class="mr-6"><span class="font-semibold">'.schoolCommunityT('Source').$labelSeparator.'</span>'.htmlPrep(schoolCommunityGroupSourceLabel($group['sourceType'] ?? 'custom')).'</span>'
        .'<span><span class="font-semibold">'.schoolCommunityT('Owner').$labelSeparator.'</span>'.htmlPrep($ownerName).'</span>'
        .'</div>'
    );
$isOwner = schoolCommunitySamePersonID($group['ownerID'], $currentPersonID);
if ($isOwner) {
    $threadActions->addHeaderAction('members', schoolCommunityT('Manage Members'))
        ->setURL('/modules/School Community/message_groupMembers.php')
        ->addParam('groupID', $groupID)
        ->setIcon('users')
        ->displayLabel();
}

$row = $threadActions->addRow();
    $row->addFooter();
    if (!$isDeleted) {
        $row->addSubmit($isArchived ? schoolCommunityT('Restore') : schoolCommunityT('Archive'))
            ->setName('action')
            ->setAttribute('value', $isArchived ? 'restore' : 'archive')
            ->setColor('gray')
            ->setType('quickSubmit')
            ->setIcon('solid', $isArchived ? 'arrow-left-circle' : 'archive')
            ->setSize('sm');
    }
if (!$isDeleted) {
    $row->addSubmit(schoolCommunityT('Mark Unread'))
        ->setName('action')
        ->setAttribute('value', 'unread')
        ->setColor('gray')
        ->setType('quickSubmit')
        ->setIcon('solid', 'refresh')
        ->setSize('sm');
}
$deleteAction = $row->addSubmit($isDeleted ? schoolCommunityT('Restore from Trash') : schoolCommunityT('Delete'))
    ->setName('action')
    ->setAttribute('value', $isDeleted ? 'restoreDelete' : 'delete')
    ->setColor($isDeleted ? 'gray' : 'red')
    ->setType('quickSubmit')
    ->setIcon('solid', $isDeleted ? 'arrow-left-circle' : 'delete')
    ->setSize('sm');
if (!$isDeleted) {
    $deleteAction->setAttribute('onclick', 'return confirm('.json_encode(schoolCommunityT('Move this group to Trash?')).')');
}
$page->write($threadActions->getOutput());

$pdo->statement(
    "INSERT IGNORE INTO message_group_read_receipt (groupMessageID, gibbonPersonID)
     SELECT message_group_message.groupMessageID, :gibbonPersonID
     FROM message_group_message
     JOIN message_group_message_recipient AS recipient
        ON recipient.groupMessageID=message_group_message.groupMessageID
       AND recipient.gibbonPersonID=:gibbonPersonID
     WHERE message_group_message.groupID=:groupID
       AND message_group_message.senderID<>:gibbonPersonID",
    ['gibbonPersonID' => $currentPersonID, 'groupID' => $groupID]
);

$messages = $pdo->select(
    "SELECT message_group_message.*,
            sender.title AS senderTitle, sender.preferredName AS senderPreferredName,
            sender.surname AS senderSurname, senderRole.category AS senderRoleCategory,
            (SELECT COUNT(*) FROM message_group_read_receipt AS receipt
             WHERE receipt.groupMessageID=message_group_message.groupMessageID
               AND receipt.gibbonPersonID<>message_group_message.senderID) AS readCount,
            (SELECT COUNT(*) FROM message_group_message_recipient AS recipient
             WHERE recipient.groupMessageID=message_group_message.groupMessageID
               AND recipient.gibbonPersonID<>message_group_message.senderID) AS memberCount
     FROM message_group_message
         JOIN message_group_message_recipient AS recipient
            ON recipient.groupMessageID=message_group_message.groupMessageID
           AND recipient.gibbonPersonID=:currentPersonID
     JOIN gibbonPerson AS sender ON sender.gibbonPersonID=message_group_message.senderID
     JOIN gibbonRole AS senderRole ON senderRole.gibbonRoleID=sender.gibbonRoleIDPrimary
     WHERE message_group_message.groupID=:groupID
     ORDER BY message_group_message.sendTimestamp DESC, message_group_message.groupMessageID DESC
     LIMIT 200",
    ['groupID' => $groupID, 'currentPersonID' => $currentPersonID]
)->fetchAll(PDO::FETCH_ASSOC);
$messages = array_reverse($messages);

$repliesByMessage = [];
if (!empty($messages)) {
    $placeholders = [];
    $params = [];
    foreach ($messages as $index => $message) {
        $key = 'message'.$index;
        $placeholders[] = ':'.$key;
        $params[$key] = $message['groupMessageID'];
    }
    $replies = $pdo->select(
        "SELECT message_group_reply.*, gibbonPerson.title, gibbonPerson.preferredName,
                gibbonPerson.surname, gibbonRole.category AS roleCategory
         FROM message_group_reply
         JOIN gibbonPerson ON gibbonPerson.gibbonPersonID=message_group_reply.senderID
         JOIN gibbonRole ON gibbonRole.gibbonRoleID=gibbonPerson.gibbonRoleIDPrimary
         WHERE message_group_reply.groupMessageID IN (".implode(', ', $placeholders).")
         ORDER BY message_group_reply.sendTimestamp, message_group_reply.groupReplyID",
        $params
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($replies as $reply) {
        $repliesByMessage[(string) $reply['groupMessageID']][] = $reply;
    }
}

if (empty($messages)) {
    $page->write('<p class="mb-6">'.schoolCommunityT('No group messages yet. Start the conversation below.').'</p>');
}

foreach ($messages as $message) {
    $senderName = Format::name(
        $message['senderTitle'],
        $message['senderPreferredName'],
        $message['senderSurname'],
        $message['senderRoleCategory'],
        true
    );
    $readCount = (int) $message['readCount'];
    $memberCount = (int) $message['memberCount'];
    $readSummary = schoolCommunitySamePersonID($message['senderID'], $currentPersonID)
        ? '<span>'.sprintf(schoolCommunityT('Read by %1$d of %2$d members'), $readCount, $memberCount).'</span>'
        : '';

    $messageForm = Form::create(
        'schoolCommunityGroupMessage'.$message['groupMessageID'],
        '',
        'get'
    );
    $messageForm->setID('group-message-'.htmlPrep($message['groupMessageID']));
    $messageForm->setTitle(htmlPrep($message['title']));
    $messageForm->setDescription(schoolCommunityT('Group message'));
    $row = $messageForm->addRow();
        $row->addLabel('sender', schoolCommunityT('From'));
        $row->addContent(htmlPrep($senderName));
    $row = $messageForm->addRow();
        $row->addLabel('sent', schoolCommunityT('Sent'));
        $row->addContent(Format::dateTime($message['sendTimestamp']));
    if (!empty($readSummary)) {
        $row = $messageForm->addRow();
            $row->addLabel('readReceipts', schoolCommunityT('Read Receipts'));
            $row->addContent($readSummary);
    }
    $row = $messageForm->addRow();
        $column = $row->addColumn();
        $column->addLabel('body', schoolCommunityT('Message'));
        $column->addContent('<div class="innerContent">'
            .schoolCommunityBodyForDisplay($container, $message['body'])
            .'</div>');

    if (!empty($repliesByMessage[(string) $message['groupMessageID']])) {
        foreach ($repliesByMessage[(string) $message['groupMessageID']] as $reply) {
            $replyName = Format::name($reply['title'], $reply['preferredName'], $reply['surname'], $reply['roleCategory'], true);
            $row = $messageForm->addRow();
                $row->addLabel('reply'.$reply['groupReplyID'], $replyName);
                $row->addContent('<div class="text-xs text-gray-600">'.Format::dateTime($reply['sendTimestamp']).'</div>'
                    .'<div class="innerContent">'.schoolCommunityBodyForDisplay($container, $reply['body']).'</div>');
        }
    }
    $page->write($messageForm->getOutput());

    if (!$isDeleted && $groupMessagesEnabled) {
        $replyForm = Form::create(
            'schoolCommunityGroupReply'.$message['groupMessageID'],
            $session->get('absoluteURL').'/modules/School Community/message_groupReplyProcess.php',
            'post'
        );
        $replyForm->addHiddenValue('groupMessageID', $message['groupMessageID']);
        $replyForm->setTitle(schoolCommunityT('Reply'));
        $row = $replyForm->addRow();
            $column = $row->addColumn();
            $column->addLabel('body', schoolCommunityT('Reply'));
            $column->addTextArea('body')->setRows(3)->required()->maxLength(5000);
        $row = $replyForm->addRow();
            $row->addFooter();
            $row->addSubmit(schoolCommunityT('Post Reply'))
                ->setType('quickSubmit')
                ->setIcon('solid', 'chat-bubble-text');
        $page->write($replyForm->getOutput());
    }
}

if ($isDeleted) {
    $page->write('<p class="text-sm text-gray-600">'.schoolCommunityT('Restore this group from Trash before sending another message.').'</p>');
    return;
}
if (!$groupMessagesEnabled) {
    $page->addError(schoolCommunityT('Group messaging is disabled for this category. Existing messages remain available to read.'));
    return;
}
if (!$canSend) {
    $page->addError(schoolCommunityT('You do not have permission to send messages.'));
    return;
}

$groupMembers = $pdo->select(
    "SELECT member.gibbonPersonID, gibbonPerson.title, gibbonPerson.preferredName,
            gibbonPerson.surname, gibbonPerson.status, gibbonRole.category AS roleCategory
     FROM message_group_member AS member
     JOIN gibbonPerson ON gibbonPerson.gibbonPersonID=member.gibbonPersonID
     JOIN gibbonRole ON gibbonRole.gibbonRoleID=gibbonPerson.gibbonRoleIDPrimary
     WHERE member.groupID=:groupID
     ORDER BY gibbonPerson.surname, gibbonPerson.preferredName",
    ['groupID' => $groupID]
)->fetchAll(PDO::FETCH_ASSOC);
$audience = schoolCommunityGroupAudience($groupMembers, $group['accessControl']);
$recipientIDs = array_map('strval', array_column($audience['eligible'], 'gibbonPersonID'));
sort($recipientIDs, SORT_STRING);
$audienceSignature = hash('sha256', implode(',', $recipientIDs));

if (empty($audience['eligible'])) {
    $page->addError(schoolCommunityT('The actual recipient list is empty. No message can be sent.'));
    return;
}

$recipientPreview = (new Action('view', schoolCommunityT('View Recipients')))
    ->setURL('/modules/School Community/message_groupRecipients.php')
    ->addParam('groupID', $groupID)
    ->setIcon('users')
    ->modalWindow(700, 520)
    ->displayLabel();
$form = Form::create('schoolCommunityGroupMessage', $session->get('absoluteURL').'/modules/School Community/message_groupMessageProcess.php', 'post');
$form->addHiddenValue('groupID', $groupID);
$form->addHiddenValue('expectedRecipientSignature', $audienceSignature);
$form->addHiddenValue('expectedRecipientCount', count($audience['eligible']));
$form->setTitle(schoolCommunityT('Send Group Message'));
$form->setDescription(schoolCommunityT('Review the recipient preview before sending. The message will only be visible to the confirmed snapshot, not to people added later.'));

$row = $form->addRow();
    $row->addLabel('audience', schoolCommunityT('Recipients'));
    $row->addContent(sprintf(schoolCommunityT('%d recipients'), count($audience['eligible'])));
$row = $form->addRow();
    $row->addLabel('preview', schoolCommunityT('Recipient Preview'));
    $row->addContent($recipientPreview->getOutput($group));
if (!empty($audience['excluded'])) {
    $row = $form->addRow();
        $row->addAlert(sprintf(
            schoolCommunityT('%d group members will be excluded because their account is inactive or their role is not allowed by this category.'),
            count($audience['excluded'])
        ), 'warning');
}

$row = $form->addRow();
    $row->addLabel('title', schoolCommunityT('Subject'));
    $row->addTextField('title')->required()->maxLength(100);

$row = $form->addRow();
    $column = $row->addColumn();
    $column->addLabel('body', schoolCommunityT('Message'));
    $column->addEditor('body', $guid)->setRows(10)->showMedia()->required();

$row = $form->addRow();
    $row->addLabel('confirmAudience', schoolCommunityT('I have reviewed the recipient list above.'));
    $row->addCheckbox('confirmAudience')->setValue('Y')->required();

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(schoolCommunityT('Send Group Message'));

$page->write($form->getOutput());
