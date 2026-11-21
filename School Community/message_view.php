<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Domain\DataSet;
use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;

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

$page->navigator->addHeaderAction('back', schoolCommunityT('Messages'))
    ->setURL('/modules/School Community/message_reading.php')
    ->setIcon('back')
    ->displayLabel();

$senderName = Format::name($message['senderTitle'], $message['senderPreferredName'], $message['senderSurname'], 'Staff');
$receiverName = Format::name($message['receiverTitle'], $message['receiverPreferredName'], $message['receiverSurname'], 'Staff');
$i18nCode = (string) $session->get(['i18n', 'code']);
$visibilityTag = schoolCommunitySamePersonID($message['senderID'], $currentPersonID)
    && schoolCommunitySamePersonID($message['receiverID'], $currentPersonID)
    ? ' '.schoolCommunityMetadataTag(schoolCommunityT('Visible only to you'), $i18nCode)
    : '';
$priorityTag = schoolCommunityMetadataTag(schoolCommunityPriorityLabel($message['priority']), $i18nCode);
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

$row = $threadActions->addRow();
    $row->addColumn()->addContent(
        '<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">'
        .'<div><div class="text-xs font-semibold uppercase tracking-wide text-gray-500">'.schoolCommunityT('From').'</div>'
        .'<div class="mt-1 text-sm font-semibold text-gray-900">'.htmlPrep($senderName).'</div></div>'
        .'<div><div class="text-xs font-semibold uppercase tracking-wide text-gray-500">'.schoolCommunityT('To').'</div>'
        .'<div class="mt-1 text-sm font-semibold text-gray-900">'.htmlPrep($receiverName).$visibilityTag.'</div></div>'
        .'<div><div class="text-xs font-semibold uppercase tracking-wide text-gray-500">'.schoolCommunityT('Category').'</div>'
        .'<div class="mt-1 text-sm text-gray-900">'.htmlPrep($message['categoryName']).' '.$priorityTag.'</div></div>'
        .'<div><div class="text-xs font-semibold uppercase tracking-wide text-gray-500">'.schoolCommunityT('Sent').'</div>'
        .'<div class="mt-1 text-sm text-gray-900">'.Format::dateTime($message['sendTimestamp']).'</div></div>'
        .'</div>'
    );

if ($isDeleted) {
    $threadActions->addRow()->addAlert(
        schoolCommunityT('This message is in Trash. Restore it before replying.'),
        'warning'
    );
}

$row = $threadActions->addRow();
    $row->addColumn()->addContent(
        '<div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm leading-6 text-gray-900">'
        .'<div class="innerContent">'.schoolCommunityBodyForDisplay($container, $message['body']).'</div>'
        .'</div>'
    );

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
    foreach ($replies as &$reply) {
        $reply['senderName'] = Format::name($reply['title'], $reply['preferredName'], $reply['surname'], 'Staff');
        $reply['bodyHtml'] = schoolCommunityBodyForDisplay($container, $reply['body']);
    }
    unset($reply);

    $replyTable = DataTable::create('schoolCommunityReplies');
    $replyTable->setTitle(schoolCommunityT('Replies'));
    $replyTable->addMetaData('allowHTML', ['senderName', 'bodyHtml']);
    $replyTable->addColumn('senderName', schoolCommunityT('From'))->format(function ($reply) {
        return '<div class="font-semibold">'.htmlPrep((string) $reply['senderName']).'</div>'
            .'<div class="text-xs text-gray-600">'.Format::dateTime($reply['sendTimestamp']).'</div>';
    });
    $replyTable->addColumn('bodyHtml', schoolCommunityT('Reply'))->format(function ($reply) {
        return '<div class="innerContent text-sm leading-6 text-gray-900">'.$reply['bodyHtml'].'</div>';
    });
    $page->write($replyTable->render(new DataSet($replies)));
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
