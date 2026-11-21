<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Session\TokenHandler;

require_once '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$currentPersonID = (string) $session->get('gibbonPersonID');
$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$threadType = is_scalar($_POST['threadType'] ?? null) ? (string) $_POST['threadType'] : '';
$threadID = is_scalar($_POST['threadID'] ?? null) ? trim((string) $_POST['threadID']) : '';
$action = is_scalar($_POST['action'] ?? null) ? (string) $_POST['action'] : '';

$viewPath = $threadType === 'group' ? 'message_groupView.php' : 'message_view.php';
$idParam = $threadType === 'group' ? 'groupID' : 'messageID';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/School Community/'.$viewPath
    .'&'.$idParam.'='.urlencode($threadID);
$redirect = function (string $return) use ($URL) {
    header('Location: '.$URL.'&return='.$return);
    exit;
};

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_reading.php')) {
    $redirect('error0');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $redirect('error1');
}
if (!$container->get(TokenHandler::class)->validateCsrfToken()) {
    $redirect('error9');
}
if (!in_array($threadType, ['direct', 'group'], true)
    || !in_array($action, ['archive', 'restore', 'unread'], true)
    || $threadID === '') {
    $redirect('error1');
}

if ($threadType === 'direct') {
    $message = schoolCommunityAccessibleMessage($pdo, $threadID, $currentPersonID);
    if (empty($message)) {
        $redirect('error0');
    }
    if ($action === 'unread') {
        if (!schoolCommunitySamePersonID($message['receiverID'], $currentPersonID)) {
            $redirect('error0');
        }
        $pdo->delete(
            "DELETE FROM message_read_receipt
             WHERE messageID=:messageID AND gibbonPersonID=:gibbonPersonID",
            ['messageID' => $threadID, 'gibbonPersonID' => $currentPersonID]
        );
        header('Location: '.$session->get('absoluteURL').'/index.php?q=/modules/School Community/message_reading.php&return=success6');
        exit;
    }
    $saved = schoolCommunitySetThreadArchived(
        $pdo,
        'direct',
        $threadID,
        $currentPersonID,
        $action === 'archive'
    );
    $redirect($saved ? ($action === 'archive' ? 'success4' : 'success5') : 'error2');
}

$group = schoolCommunityAccessibleGroup($pdo, $threadID, $currentPersonID, $roleCategory);
if (empty($group)) {
    $redirect('error0');
}
if ($action === 'unread') {
    $pdo->delete(
        "DELETE receipt FROM message_group_read_receipt AS receipt
         JOIN message_group_message AS message
           ON message.groupMessageID=receipt.groupMessageID
         JOIN message_group_message_recipient AS recipient
           ON recipient.groupMessageID=message.groupMessageID
          AND recipient.gibbonPersonID=:gibbonPersonID
         WHERE message.groupID=:groupID
           AND receipt.gibbonPersonID=:gibbonPersonID",
        ['gibbonPersonID' => $currentPersonID, 'groupID' => $threadID]
    );
    header('Location: '.$session->get('absoluteURL').'/index.php?q=/modules/School Community/message_groups.php&return=success6');
    exit;
}

$saved = schoolCommunitySetThreadArchived(
    $pdo,
    'group',
    $threadID,
    $currentPersonID,
    $action === 'archive'
);
$redirect($saved ? ($action === 'archive' ? 'success4' : 'success5') : 'error2');
