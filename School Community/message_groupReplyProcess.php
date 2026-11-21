<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Domain\System\NotificationGateway;
use Gibbon\Session\TokenHandler;

require_once '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$groupMessageID = is_scalar($_POST['groupMessageID'] ?? null) ? trim((string) $_POST['groupMessageID']) : '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/School Community/message_groupView.php';
$redirect = function (string $return) use (&$URL) {
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

$_POST = $container->get(Validator::class)->sanitize($_POST, ['body' => 'HTML']);
$body = is_scalar($_POST['body'] ?? null) ? trim((string) $_POST['body']) : '';
$currentPersonID = (string) $session->get('gibbonPersonID');
$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');

if ($groupMessageID === '' || $body === '' || mb_strlen(strip_tags($body)) > 5000) {
    $redirect('error1');
}

$message = schoolCommunityAccessibleGroupMessage($pdo, $groupMessageID, $currentPersonID, $roleCategory);
if (empty($message) || !schoolCommunityGroupMessagesAllowed($message)) {
    $redirect('error0');
}
$URL .= '&groupID='.urlencode($message['groupID']);

$inserted = $pdo->insert(
    "INSERT INTO message_group_reply (groupMessageID, senderID, body)
     VALUES (:groupMessageID, :senderID, :body)",
    ['groupMessageID' => $groupMessageID, 'senderID' => $currentPersonID, 'body' => $body]
);
if (empty($inserted)) {
    $redirect('error2');
}

try {
    schoolCommunityRestoreThreadStates($pdo, 'group', $message['groupID'], $currentPersonID);
} catch (\Throwable $e) {
    // The reply is stored; archive cleanup must not lose it.
}

$members = $pdo->select(
    "SELECT recipient.gibbonPersonID
     FROM message_group_message_recipient AS recipient
     WHERE recipient.groupMessageID=:groupMessageID
       AND recipient.gibbonPersonID<>:senderID",
    ['groupMessageID' => $groupMessageID, 'senderID' => $currentPersonID]
)->fetchAll(PDO::FETCH_COLUMN);
$senderName = trim((string) $session->get('preferredName').' '.(string) $session->get('surname'));
$notification = $container->get(NotificationGateway::class);
foreach ($members as $memberID) {
    try {
        $notification->insertNotification([
            'gibbonPersonID' => $memberID,
            'text'           => sprintf(schoolCommunityT('New group reply from %s in %s: %s'), $senderName, $message['groupName'], $message['title']),
            'moduleName'     => 'School Community',
            'actionLink'     => '/index.php?q=/modules/School Community/message_groupView.php&groupID='.urlencode((string) $message['groupID']).'#group-message-'.urlencode((string) $message['groupMessageID']),
        ]);
    } catch (\Throwable $e) {
        // The reply is already stored; notification failure must not lose it.
    }
}

header('Location: '.$URL.'&return=success1#group-message-'.urlencode($groupMessageID));
exit;
