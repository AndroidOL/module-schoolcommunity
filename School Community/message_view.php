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

if ((string) $message['receiverID'] === $currentPersonID) {
    $pdo->statement(
        "INSERT INTO message_read_receipt (messageID, gibbonPersonID)
         VALUES (:messageID, :gibbonPersonID)
         ON DUPLICATE KEY UPDATE readTimestamp=readTimestamp",
        ['messageID' => $messageID, 'gibbonPersonID' => $currentPersonID]
    );
}

$senderName = Format::name($message['senderTitle'], $message['senderPreferredName'], $message['senderSurname'], 'Staff');
$receiverName = Format::name($message['receiverTitle'], $message['receiverPreferredName'], $message['receiverSurname'], 'Staff');

$page->write('<div class="schoolCommunityMessage">'
    .'<h2>'.htmlPrep($message['title']).'</h2>'
    .'<div class="schoolCommunityMessageMeta">'
    .'<span><strong>'.schoolCommunityT('From').':</strong> '.htmlPrep($senderName).'</span>'
    .'<span><strong>'.schoolCommunityT('To').':</strong> '.htmlPrep($receiverName).'</span>'
    .'<span><strong>'.schoolCommunityT('Category').':</strong> '.htmlPrep($message['categoryName']).'</span>'
    .'<span><strong>'.schoolCommunityT('Priority').':</strong> '.htmlPrep(schoolCommunityPriorityLabel($message['priority'])).'</span>'
    .'<span><strong>'.schoolCommunityT('Sent').':</strong> '.Format::dateTime($message['sendTimestamp']).'</span>'
    .'</div>'
    .'<div class="schoolCommunityMessageBody innerContent">'.schoolCommunityBodyForDisplay($container, $message['body']).'</div>'
    .'</div>');

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

$form = Form::create('schoolCommunityReply', $session->get('absoluteURL').'/modules/School Community/message_replyProcess.php', 'post');
$form->addHiddenValue('messageID', $messageID);
$form->setTitle(schoolCommunityT('Reply'));
$row = $form->addRow();
    $column = $row->addColumn();
    $column->addLabel('body', schoolCommunityT('Reply'));
    $column->addEditor('body', $guid)->setRows(8)->required();
$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(schoolCommunityT('Post Reply'));

$page->write($form->getOutput());
