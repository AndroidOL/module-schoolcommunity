<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;

require_once __DIR__.'/moduleFunctions.php';

$page->breadcrumbs->add(schoolCommunityT('Messages'), 'message_reading.php');

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_reading.php')) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$threadType = is_scalar($_GET['threadType'] ?? null) ? (string) $_GET['threadType'] : '';
$threadID = is_scalar($_GET['threadID'] ?? null) ? trim((string) $_GET['threadID']) : '';
$action = is_scalar($_GET['action'] ?? null) ? (string) $_GET['action'] : '';
$currentPersonID = (string) $session->get('gibbonPersonID');
$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');

if (!in_array($threadType, ['direct', 'group'], true)
    || !in_array($action, ['archive', 'restore', 'unread', 'delete', 'restoreDelete'], true)
    || $threadID === '') {
    $page->addError(schoolCommunityT('The submitted data is invalid.'));
    return;
}

$target = null;
$sourceURL = '/index.php?q=/modules/School Community/message_reading.php';
if ($threadType === 'direct') {
    $target = schoolCommunityAccessibleMessage($pdo, $threadID, $currentPersonID);
    $sourceURL = '/index.php?q=/modules/School Community/message_view.php&messageID='.urlencode($threadID);
} else {
    $target = schoolCommunityAccessibleGroup($pdo, $threadID, $currentPersonID, $roleCategory);
    $sourceURL = '/index.php?q=/modules/School Community/message_groupView.php&groupID='.urlencode($threadID);
}

if (empty($target)) {
    $page->addError(schoolCommunityT('The specified record does not exist.'));
    return;
}

$targetName = $threadType === 'direct' ? $target['title'] : $target['groupName'];
$actionConfig = [
    'archive' => [
        'title' => schoolCommunityT('Archive Conversation'),
        'description' => schoolCommunityT('Archive this conversation? You can restore it later from the Archived mailbox.'),
        'button' => schoolCommunityT('Archive'),
        'color' => 'gray',
    ],
    'restore' => [
        'title' => schoolCommunityT('Restore Conversation'),
        'description' => schoolCommunityT('Restore this conversation to the Active mailbox?'),
        'button' => schoolCommunityT('Restore'),
        'color' => 'gray',
    ],
    'unread' => [
        'title' => schoolCommunityT('Mark Conversation Unread'),
        'description' => schoolCommunityT('Mark this conversation as unread? It will return to your Unread mailbox.'),
        'button' => schoolCommunityT('Mark Unread'),
        'color' => 'gray',
    ],
    'delete' => [
        'title' => schoolCommunityT('Move Conversation to Trash'),
        'description' => schoolCommunityT('Move this conversation to Trash? The shared messages remain available to the other participants and you can restore your copy later.'),
        'button' => schoolCommunityT('Move to Trash'),
        'color' => 'red',
    ],
    'restoreDelete' => [
        'title' => schoolCommunityT('Restore from Trash'),
        'description' => schoolCommunityT('Restore this conversation from Trash to the Active mailbox?'),
        'button' => schoolCommunityT('Restore from Trash'),
        'color' => 'gray',
    ],
][$action];

$form = Form::create(
    'schoolCommunityThreadConfirm',
    $session->get('absoluteURL').'/modules/School Community/message_threadProcess.php',
    'post'
);
$form->addHiddenValue('threadType', $threadType);
$form->addHiddenValue('threadID', $threadID);
$form->addHiddenValue('action', $action);
$form->setTitle($actionConfig['title']);
$form->setDescription($actionConfig['description']);

$row = $form->addRow();
    $row->addLabel('conversation', schoolCommunityT('Conversation'));
    $row->addContent(htmlPrep($targetName));

$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit($actionConfig['button'])->setColor($actionConfig['color']);
    $row->addButton(schoolCommunityT('Cancel'))
        ->onClick('window.location.href='.json_encode($session->get('absoluteURL').$sourceURL));

$page->write($form->getOutput());
