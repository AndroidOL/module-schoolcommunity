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

$page->write('<div class="mb-6">'
    .'<h2 class="mb-3 text-lg font-semibold text-gray-900">'.htmlPrep($message['title']).'</h2>'
    .'<dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm text-gray-600 sm:grid-cols-2 lg:grid-cols-3">'
    .'<div><dt class="font-semibold text-gray-800">'.schoolCommunityT('From').'</dt><dd>'.htmlPrep($senderName).'</dd></div>'
    .'<div><dt class="font-semibold text-gray-800">'.schoolCommunityT('To').'</dt><dd>'.htmlPrep($receiverName).'</dd></div>'
    .'<div><dt class="font-semibold text-gray-800">'.schoolCommunityT('Category').'</dt><dd>'.htmlPrep($message['categoryName']).'</dd></div>'
    .'<div><dt class="font-semibold text-gray-800">'.schoolCommunityT('Priority').'</dt><dd>'.htmlPrep(schoolCommunityPriorityLabel($message['priority'])).'</dd></div>'
    .'<div><dt class="font-semibold text-gray-800">'.schoolCommunityT('Sent').'</dt><dd>'.Format::dateTime($message['sendTimestamp']).'</dd></div>'
    .(schoolCommunitySamePersonID($message['senderID'], $currentPersonID) && schoolCommunitySamePersonID($message['receiverID'], $currentPersonID)
        ? '<div><dt class="font-semibold text-gray-800">'.schoolCommunityT('Visibility').'</dt><dd>'.schoolCommunityT('Visible only to you').'</dd></div>'
        : '')
    .($isDeleted
        ? '<div><dt class="font-semibold text-gray-800">'.schoolCommunityT('Mailbox').'</dt><dd>'.schoolCommunityT('Trash').'</dd></div>'
        : ($isArchived
            ? '<div><dt class="font-semibold text-gray-800">'.schoolCommunityT('Mailbox').'</dt><dd>'.schoolCommunityT('Archived').'</dd></div>'
            : ''))
    .'</dl>'
    .'<div class="schoolCommunityMessageBody mt-5 text-gray-800">'.schoolCommunityBodyForDisplay($container, $message['body']).'</div>'
    .'</div>');

$threadActions = Form::create(
    'schoolCommunityMessageActions',
    $session->get('absoluteURL').'/modules/School Community/message_threadProcess.php',
    'post'
);
$threadActions->setClass('noIntBorder w-full');
$threadActions->addHiddenValue('threadType', 'direct');
$threadActions->addHiddenValue('threadID', $messageID);
$row = $threadActions->addRow();
    $row->addFooter();
    if (!$isDeleted) {
        $row->addSubmit($isArchived ? schoolCommunityT('Restore') : schoolCommunityT('Archive'))
            ->setName('action')
            ->setValue($isArchived ? 'restore' : 'archive')
            ->setColor('gray');
    }
if (!$isDeleted && schoolCommunitySamePersonID($message['receiverID'], $currentPersonID)) {
    $row->addSubmit(schoolCommunityT('Mark Unread'))
        ->setName('action')
        ->setValue('unread')
        ->setColor('gray');
}
$deleteAction = $row->addSubmit($isDeleted ? schoolCommunityT('Restore from Trash') : schoolCommunityT('Delete'))
    ->setName('action')
    ->setValue($isDeleted ? 'restoreDelete' : 'delete')
    ->setColor('gray');
if (!$isDeleted) {
    $deleteAction->setAttribute('onclick', 'return confirm("'.schoolCommunityT('Move this message to Trash?').'")');
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
    $page->write('<h3>'.schoolCommunityT('Replies').'</h3>');
    foreach ($replies as $reply) {
        $replyName = Format::name($reply['title'], $reply['preferredName'], $reply['surname'], 'Staff');
        $page->write('<div class="schoolCommunityReply">'
            .'<div class="schoolCommunityMessageMeta"><strong>'.htmlPrep($replyName).'</strong> '
            .'<span>'.Format::dateTime($reply['sendTimestamp']).'</span></div>'
            .'<div class="innerContent">'.schoolCommunityBodyForDisplay($container, $reply['body']).'</div>'
            .'</div>');
    }
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
        $column->addEditor('body', $guid)->setRows(3)->required();
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(schoolCommunityT('Post Reply'));

    $page->write($form->getOutput());
}
