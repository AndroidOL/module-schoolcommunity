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

$metaTags = [Format::tag(schoolCommunityPriorityLabel($message['priority']), 'message')];
if ($privateNote !== '') {
    $metaTags[] = Format::tag($privateNote, 'message');
}

$senderProfile = schoolCommunityPersonProfileURL($message['senderID'], $message['senderRoleCategory'] ?? '');
$receiverProfile = schoolCommunityPersonProfileURL($message['receiverID'], $message['receiverRoleCategory'] ?? '');
$senderHTML = $senderProfile !== ''
    ? '<a href="'.htmlPrep($senderProfile).'" class="text-blue-600 hover:text-blue-800">'.htmlPrep($senderName).'</a>'
    : htmlPrep($senderName);
$receiverHTML = $receiverProfile !== ''
    ? '<a href="'.htmlPrep($receiverProfile).'" class="text-blue-600 hover:text-blue-800">'.htmlPrep($receiverName).'</a>'
    : htmlPrep($receiverName);

// The subject and body are the focus; metadata is a single compact line beneath the subject.
$page->write(
    '<section class="w-full p-4 bg-white border border-gray-200 rounded-md">'
    .'<h1 class="text-2xl font-semibold text-gray-900 leading-tight">'.htmlPrep((string) $message['title']).'</h1>'
    .'<div class="flex flex-wrap items-center gap-2 mt-2 text-sm text-gray-600">'
    .'<span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">'.htmlPrep((string) $message['categoryName']).'</span>'
    .'<span class="font-medium text-gray-900">'.$senderHTML.'</span>'
    .'<span class="text-gray-400">→</span>'
    .'<span>'.$receiverHTML.'</span>'
    .'<span class="text-gray-400">·</span>'
    .'<span>'.Format::dateTime($message['sendTimestamp']).'</span>'
    .implode('', $metaTags)
    .'</div>'
    .'<div class="border-b border-gray-200 mt-4 mb-4"></div>'
    .'<div class="text-sm leading-6 text-gray-800">'
    .schoolCommunityBodyForDisplay($container, $message['body'])
    .'</div>'
    .'</section>'
);

$replies = $pdo->select(
    "SELECT message_reply.*, gibbonPerson.title, gibbonPerson.preferredName, gibbonPerson.surname
     FROM message_reply
     JOIN gibbonPerson ON gibbonPerson.gibbonPersonID=message_reply.senderID
     WHERE message_reply.messageID=:messageID
     ORDER BY message_reply.sendTimestamp, message_reply.replyID",
    ['messageID' => $messageID]
)->fetchAll(PDO::FETCH_ASSOC);

if (!empty($replies)) {
    $replyHTML = '<section class="w-full mt-4">'
        .'<h2 class="text-lg font-semibold text-gray-900 mb-2">'.schoolCommunityT('Replies').'</h2>';
    foreach ($replies as $reply) {
        $replySender = Format::name($reply['title'], $reply['preferredName'], $reply['surname'], 'Staff', true);
        $replyHTML .= '<div class="border border-gray-200 rounded-md bg-white p-3 mb-2">'
            .'<div class="flex flex-wrap items-center justify-between gap-2 mb-2">'
            .'<span class="font-medium text-gray-900">'.htmlPrep($replySender).'</span>'
            .'<span class="text-xs text-gray-500">'.Format::dateTime($reply['sendTimestamp']).'</span>'
            .'</div>'
            .'<div class="text-sm leading-6 text-gray-800">'
            .schoolCommunityBodyForDisplay($container, $reply['body'])
            .'</div>'
            .'</div>';
    }
    $replyHTML .= '</section>';
    $page->write($replyHTML);
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
