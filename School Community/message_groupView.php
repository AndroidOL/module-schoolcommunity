<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;

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
    'error0'   => schoolCommunityT('You do not have permission to perform this action.'),
    'error1'   => schoolCommunityT('The submitted data is invalid.'),
    'error2'   => schoolCommunityT('Unable to save the group message.'),
    'error3'   => schoolCommunityT('The recipient list changed. Review the updated list and send again.'),
    'error4'   => schoolCommunityT('A message group already exists for this class.'),
    'error9'   => schoolCommunityT('Your session expired. Refresh the page and try again.'),
]);

$groupID = is_scalar($_GET['groupID'] ?? null) ? trim((string) $_GET['groupID']) : '';
$currentPersonID = (string) $session->get('gibbonPersonID');
$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$group = $groupID !== '' ? schoolCommunityAccessibleGroup($pdo, $groupID, $currentPersonID, $roleCategory) : null;

if (empty($group)) {
    $page->addError(schoolCommunityT('The specified record does not exist.'));
    return;
}

$threadState = schoolCommunityThreadState($pdo, 'group', $groupID, $currentPersonID);
$isArchived = !empty($threadState['archivedAt']);
$archiveForm = Form::create(
    'schoolCommunityArchiveGroup',
    $session->get('absoluteURL').'/modules/School Community/message_threadProcess.php',
    'post'
);
$archiveForm->setClass('noIntBorder w-full');
$archiveForm->addHiddenValue('threadType', 'group');
$archiveForm->addHiddenValue('threadID', $groupID);
$archiveForm->addHiddenValue('action', $isArchived ? 'restore' : 'archive');
$row = $archiveForm->addRow();
    $row->addFooter();
    $row->addSubmit($isArchived ? schoolCommunityT('Restore') : schoolCommunityT('Archive'))->setColor('gray');
$page->write($archiveForm->getOutput());

$unreadForm = Form::create(
    'schoolCommunityUnreadGroup',
    $session->get('absoluteURL').'/modules/School Community/message_threadProcess.php',
    'post'
);
$unreadForm->setClass('noIntBorder w-full');
$unreadForm->addHiddenValue('threadType', 'group');
$unreadForm->addHiddenValue('threadID', $groupID);
$unreadForm->addHiddenValue('action', 'unread');
$row = $unreadForm->addRow();
    $row->addFooter();
    $row->addSubmit(schoolCommunityT('Mark Unread'))->setColor('gray');
$page->write($unreadForm->getOutput());

$isOwner = schoolCommunitySamePersonID($group['ownerID'], $currentPersonID);
if ($isOwner) {
    $actionForm = Form::createBlank('schoolCommunityGroupActions');
    $actionForm->addHeaderAction('members', schoolCommunityT('Manage Members'))
        ->setURL('/modules/School Community/message_groupMembers.php')
        ->addParam('groupID', $groupID)
        ->setIcon('group')
        ->displayLabel();
    $page->write($actionForm->getOutput());
}

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

$ownerName = Format::name(
    $group['ownerTitle'],
    $group['ownerPreferredName'],
    $group['ownerSurname'],
    $group['ownerRoleCategory'] ?? 'Staff',
    true
);
$page->write('<div class="schoolCommunityGroupHeader">'
    .'<h2>'.htmlPrep($group['groupName']).'</h2>'
    .'<div class="schoolCommunityMessageMeta">'
    .'<span><strong>'.schoolCommunityT('Category').':</strong> '.htmlPrep($group['categoryName']).'</span>'
    .'<span><strong>'.schoolCommunityT('Source').':</strong> '.htmlPrep(schoolCommunityGroupSourceLabel($group['sourceType'] ?? 'custom')).'</span>'
    .'<span><strong>'.schoolCommunityT('Owner').':</strong> '.htmlPrep($ownerName).'</span>'
    .'</div>'
    .'</div>');

if (empty($messages)) {
    $page->write('<p class="schoolCommunityEmpty">'.schoolCommunityT('No group messages yet. Start the conversation below.').'</p>');
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

    $page->write('<article class="schoolCommunityMessage" id="group-message-'.htmlPrep($message['groupMessageID']).'">'
        .'<h3>'.htmlPrep($message['title']).'</h3>'
        .'<div class="schoolCommunityMessageMeta">'
        .'<strong>'.htmlPrep($senderName).'</strong>'
        .'<span>'.Format::dateTime($message['sendTimestamp']).'</span>'
        .$readSummary
        .'</div>'
        .'<div class="schoolCommunityMessageBody innerContent">'.schoolCommunityBodyForDisplay($container, $message['body']).'</div>');

    if (!empty($repliesByMessage[(string) $message['groupMessageID']])) {
    $page->write('<div class="schoolCommunityGroupReplies"><h4>'.schoolCommunityT('Replies').'</h4>');
        foreach ($repliesByMessage[(string) $message['groupMessageID']] as $reply) {
            $replyName = Format::name($reply['title'], $reply['preferredName'], $reply['surname'], $reply['roleCategory'], true);
            $page->write('<div class="schoolCommunityReply">'
                .'<div class="schoolCommunityMessageMeta"><strong>'.htmlPrep($replyName).'</strong> '
                .'<span>'.Format::dateTime($reply['sendTimestamp']).'</span></div>'
                .'<div class="innerContent">'.schoolCommunityBodyForDisplay($container, $reply['body']).'</div>'
                .'</div>');
        }
        $page->write('</div>');
    }

    $replyForm = Form::create(
        'schoolCommunityGroupReply'.$message['groupMessageID'],
        $session->get('absoluteURL').'/modules/School Community/message_groupReplyProcess.php',
        'post'
    );
    $replyForm->addHiddenValue('groupMessageID', $message['groupMessageID']);
    $row = $replyForm->addRow();
        $column = $row->addColumn();
        $column->addLabel('body', schoolCommunityT('Reply'));
        $column->addTextArea('body')->setRows(4)->required()->maxLength(5000);
    $row = $replyForm->addRow();
        $row->addFooter();
        $row->addSubmit(schoolCommunityT('Post Reply'));
    $page->write('<details class="schoolCommunityReplyComposer"><summary>'.schoolCommunityT('Reply').'</summary>'.$replyForm->getOutput().'</details>');
    $page->write('</article>');
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
$recipientNames = array_map(function ($member) {
    return Format::name($member['title'], $member['preferredName'], $member['surname'], $member['roleCategory'], true);
}, $audience['eligible']);
$recipientIDs = array_map('strval', array_column($audience['eligible'], 'gibbonPersonID'));
sort($recipientIDs, SORT_STRING);
$audienceSignature = hash('sha256', implode(',', $recipientIDs));

$audienceSummary = '<strong>'.sprintf(schoolCommunityT('%d recipients'), count($audience['eligible'])).'</strong>';
if (!empty($recipientNames)) {
    $audienceSummary .= '<br>'.htmlPrep(implode(', ', $recipientNames));
}
$audienceSummary .= '<br>'.sprintf(
    schoolCommunityT('%d group members will be excluded because their account is inactive or their role is not allowed by this category.'),
    count($audience['excluded'])
);

$form = Form::create('schoolCommunityGroupMessage', $session->get('absoluteURL').'/modules/School Community/message_groupMessageProcess.php', 'post');
$form->addHiddenValue('groupID', $groupID);
$form->addHiddenValue('expectedRecipientSignature', $audienceSignature);
$form->addHiddenValue('expectedRecipientCount', count($audience['eligible']));
$form->setTitle(schoolCommunityT('Send Group Message'));
$form->setDescription(schoolCommunityT('Review this send-time recipient list. The message will only be visible to the confirmed snapshot, not to people added later.'));

$row = $form->addRow();
    $row->addLabel('audience', schoolCommunityT('Recipients'));
    $row->addContent($audienceSummary);

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
