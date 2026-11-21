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

$senderName = Format::name($message['senderTitle'], $message['senderPreferredName'], $message['senderSurname'], 'Staff', true);
$receiverName = Format::name($message['receiverTitle'], $message['receiverPreferredName'], $message['receiverSurname'], 'Staff', true);
$threadState = schoolCommunityThreadState($pdo, 'direct', $messageID, $currentPersonID);
$isArchived = !empty($threadState['archivedAt']);
$isDeleted = !empty($threadState['deletedAt']);

$threadAction = function (string $name, string $label, string $action, string $icon) use ($page, $messageID) {
    return $page->navigator->addHeaderAction($name, $label)
        ->setURL('/modules/School Community/message_threadConfirm.php')
        ->addParam('threadType', 'direct')
        ->addParam('threadID', $messageID)
        ->addParam('action', $action)
        ->setIcon($icon)
        ->modalWindow()
        ->displayLabel();
};

$page->navigator->addHeaderAction('back', schoolCommunityT('Messages'))
    ->setURL('/modules/School Community/message_reading.php')
    ->setIcon('back')
    ->displayLabel();

if (!$isDeleted) {
    $threadAction(
        'archive',
        $isArchived ? schoolCommunityT('Restore') : schoolCommunityT('Archive'),
        $isArchived ? 'restore' : 'archive',
        $isArchived ? 'arrow-left-circle' : 'archive'
    );
    if (schoolCommunitySamePersonID($message['receiverID'], $currentPersonID)) {
        $threadAction('unread', schoolCommunityT('Mark Unread'), 'unread', 'refresh');
    }
}
$threadAction(
    'delete',
    $isDeleted ? schoolCommunityT('Restore from Trash') : schoolCommunityT('Delete'),
    $isDeleted ? 'restoreDelete' : 'delete',
    $isDeleted ? 'arrow-left-circle' : 'delete'
);

if ($isDeleted) {
    $page->addAlert(
        schoolCommunityT('This message is in Trash. Restore it before replying.'),
        'warning'
    );
}

$privateNote = schoolCommunitySamePersonID($message['senderID'], $currentPersonID)
    && schoolCommunitySamePersonID($message['receiverID'], $currentPersonID)
    ? schoolCommunityT('Visible only to you')
    : '';

$messageTable = DataTable::create('schoolCommunityMessageView');
$messageTable->setTitle(htmlPrep($message['title']));
$messageTable->setDescription(
    schoolCommunityT('Direct message')
    .($privateNote !== '' ? ' · '.$privateNote : '')
);
$messageTable->addMetaData('allowHTML', ['body']);
$messageTable->addColumn('senderName', schoolCommunityT('From'))
    ->context('primary')
    ->format(function ($message) {
        return htmlPrep((string) $message['senderName']);
    });
$messageTable->addColumn('receiverName', schoolCommunityT('To'))
    ->context('primary')
    ->format(function ($message) {
        return htmlPrep((string) $message['receiverName']);
    });
$messageTable->addColumn('categoryName', schoolCommunityT('Category'))
    ->context('secondary')
    ->format(function ($message) {
        return htmlPrep((string) $message['categoryName']);
    });
$messageTable->addColumn('priority', schoolCommunityT('Priority'))
    ->context('secondary')
    ->format(function ($message) {
        return Format::tag(schoolCommunityPriorityLabel($message['priority']), 'message');
    });
$messageTable->addColumn('sendTimestamp', schoolCommunityT('Sent'))
    ->context('secondary')
    ->format(function ($message) {
        return Format::dateTime($message['sendTimestamp']);
    });
$messageTable->addColumn('body', schoolCommunityT('Message'))
    ->context('primary')
    ->format(function ($message) use ($container) {
        return schoolCommunityBodyForDisplay($container, $message['body']);
    });

$page->write($messageTable->render(new DataSet([$message + [
    'senderName' => $senderName,
    'receiverName' => $receiverName,
]])));

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
        $reply['senderName'] = Format::name($reply['title'], $reply['preferredName'], $reply['surname'], 'Staff', true);
    }
    unset($reply);

    $replyTable = DataTable::create('schoolCommunityMessageReplies');
    $replyTable->setTitle(schoolCommunityT('Replies'));
    $replyTable->addMetaData('allowHTML', ['body']);
    $replyTable->addColumn('senderName', schoolCommunityT('From'))
        ->context('primary')
        ->format(function ($reply) {
            return htmlPrep((string) $reply['senderName']).'<div class="text-xs text-gray-600">'
                .Format::dateTime($reply['sendTimestamp']).'</div>';
        });
    $replyTable->addColumn('body', schoolCommunityT('Reply'))
        ->context('primary')
        ->format(function ($reply) use ($container) {
            return schoolCommunityBodyForDisplay($container, $reply['body']);
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
