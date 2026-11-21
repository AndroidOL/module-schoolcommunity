<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;
use Gibbon\Services\Format;

require_once __DIR__.'/moduleFunctions.php';

$page->breadcrumbs
    ->add(schoolCommunityT('Messages'), 'message_reading.php')
    ->add(schoolCommunityT('View Message'));

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_reading.php')) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$page->return->addReturns([
    'success0' => schoolCommunityT('Message sent.'),
    'success1' => schoolCommunityT('Reply posted.'),
    'success4' => schoolCommunityT('Message archived.'),
    'success5' => schoolCommunityT('Message restored.'),
    'success6' => schoolCommunityT('Message marked unread.'),
    'success7' => schoolCommunityT('Message moved to Trash.'),
    'success8' => schoolCommunityT('Message restored from Trash.'),
    'error0'   => schoolCommunityT('You do not have permission to perform this action.'),
    'error1'   => schoolCommunityT('The submitted data is invalid.'),
    'error2'   => schoolCommunityT('Unable to save the message.'),
    'error9'   => schoolCommunityT('Your session expired. Refresh the page and try again.'),
]);

$messageID = is_scalar($_GET['messageID'] ?? null) ? trim((string) $_GET['messageID']) : '';
$currentPersonID = (string) $session->get('gibbonPersonID');
$message = $messageID !== '' ? schoolCommunityAccessibleMessage($pdo, $messageID, $currentPersonID) : null;

if (empty($message)) {
    $page->addError(schoolCommunityT('The specified record does not exist.'));
    return;
}

if (schoolCommunitySamePersonID($message['receiverID'], $currentPersonID)) {
    $pdo->statement(
        "INSERT INTO message_read_receipt (messageID, gibbonPersonID)
         VALUES (:messageID, :gibbonPersonID)
         ON DUPLICATE KEY UPDATE readTimestamp=readTimestamp",
        ['messageID' => $messageID, 'gibbonPersonID' => $currentPersonID]
    );
}

$senderName = Format::name($message['senderTitle'], $message['senderPreferredName'], $message['senderSurname'], 'Staff');
$receiverName = Format::name($message['receiverTitle'], $message['receiverPreferredName'], $message['receiverSurname'], 'Staff');
$threadState = schoolCommunityThreadState($pdo, 'direct', $messageID, $currentPersonID);
$isArchived = !empty($threadState['archivedAt']);
$isDeleted = !empty($threadState['deletedAt']);

$threadActions = Form::create(
    'schoolCommunityMessage',
    $session->get('absoluteURL').'/modules/School Community/message_threadProcess.php',
    'post'
);
$threadActions->setTitle(htmlPrep($message['title']));
$threadActions->setDescription(schoolCommunityT('Direct message'));
$threadActions->addHiddenValue('threadType', 'direct');
$threadActions->addHiddenValue('threadID', $messageID);

$i18nCode = (string) $session->get(['i18n', 'code']);
$labelSeparator = schoolCommunityUsesWideMetadataTags($i18nCode) ? '：' : ': ';
$visibilityTag = schoolCommunitySamePersonID($message['senderID'], $currentPersonID)
    && schoolCommunitySamePersonID($message['receiverID'], $currentPersonID)
    ? ' '.schoolCommunityMetadataTag(schoolCommunityT('Visible only to you'), $i18nCode)
    : '';

$row = $threadActions->addRow();
    $column = $row->addColumn();
    $column->addContent(
        '<div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-gray-800">'
        .'<span class="mr-6"><span class="font-semibold">'.schoolCommunityT('From').$labelSeparator.'</span>'.htmlPrep($senderName).'</span>'
        .'<span><span class="font-semibold">'.schoolCommunityT('To').$labelSeparator.'</span>'.htmlPrep($receiverName).$visibilityTag.'</span>'
        .'</div>'
        .'<div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-gray-800">'
        .'<span class="mr-6"><span class="font-semibold">'.schoolCommunityT('Category').$labelSeparator.'</span>'.htmlPrep($message['categoryName']).'</span>'
        .schoolCommunityMetadataTag(schoolCommunityPriorityLabel($message['priority']), $i18nCode)
        .'<span><span class="font-semibold">'.schoolCommunityT('Sent').$labelSeparator.'</span>'.Format::dateTime($message['sendTimestamp']).'</span>'
        .'</div>'
    );
$row = $threadActions->addRow();
    $column = $row->addColumn();
    $column->addLabel('body', schoolCommunityT('Message'));
    $column->addContent('<div class="innerContent">'
        .schoolCommunityBodyForDisplay($container, $message['body'])
        .'</div>');

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
if (!$isDeleted && schoolCommunitySamePersonID($message['receiverID'], $currentPersonID)) {
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
    $deleteAction->setAttribute('onclick', 'return confirm('.json_encode(schoolCommunityT('Move this message to Trash?')).')');
}
$page->write($threadActions->getOutput());

$replies = $pdo->select(
    "SELECT message_reply.*, gibbonPerson.title, gibbonPerson.preferredName, gibbonPerson.surname
     FROM message_reply
     JOIN gibbonPerson ON gibbonPerson.gibbonPersonID=message_reply.senderID
     WHERE message_reply.messageID=:messageID
     ORDER BY message_reply.sendTimestamp, message_reply.replyID",
    ['messageID' => $messageID]
)->fetchAll(PDO::FETCH_ASSOC);

if (!empty($replies)) {
    $replyList = Form::create('schoolCommunityReplies', '', 'get');
    $replyList->setTitle(schoolCommunityT('Replies'));
    foreach ($replies as $reply) {
        $replyName = Format::name($reply['title'], $reply['preferredName'], $reply['surname'], 'Staff');
        $row = $replyList->addRow();
            $row->addLabel('reply'.$reply['replyID'], $replyName);
            $row->addContent('<div class="text-xs text-gray-600">'.Format::dateTime($reply['sendTimestamp']).'</div>'
                .'<div class="innerContent">'.schoolCommunityBodyForDisplay($container, $reply['body']).'</div>');
    }
    $page->write($replyList->getOutput());
}

if ($isDeleted) {
    $page->write('<p class="text-sm text-gray-600">'.schoolCommunityT('Restore this message from Trash before replying.').'</p>');
} else {
    $form = Form::create('schoolCommunityReply', $session->get('absoluteURL').'/modules/School Community/message_replyProcess.php', 'post');
    $form->addHiddenValue('messageID', $messageID);
    $form->setTitle(schoolCommunityT('Reply'));
    $row = $form->addRow();
        $column = $row->addColumn();
        $column->addLabel('body', schoolCommunityT('Reply'));
        $column->addTextArea('body')->setRows(3)->required()->maxLength(5000);
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(schoolCommunityT('Post Reply'))
            ->setType('quickSubmit')
            ->setIcon('solid', 'chat-bubble-text');

    $page->write($form->getOutput());
}
