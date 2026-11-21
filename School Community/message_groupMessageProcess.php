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

$groupID = is_scalar($_POST['groupID'] ?? null) ? trim((string) $_POST['groupID']) : '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/School Community/message_groupView.php&groupID='.urlencode($groupID);
$redirect = function (string $return) use ($URL) {
    header('Location: '.$URL.'&return='.$return);
    exit;
};

if (!schoolCommunityMessagingAccessAllowed(
    isActionAccessible($guid, $connection2, '/modules/School Community/message_sending.php'),
    isActionAccessible($guid, $connection2, '/modules/School Community/message_reading.php')
)) {
    $redirect('error0');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $redirect('error1');
}
if (!$container->get(TokenHandler::class)->validateCsrfToken()) {
    $redirect('error9');
}

$_POST = $container->get(Validator::class)->sanitize($_POST, ['body' => 'HTML']);
$currentPersonID = (string) $session->get('gibbonPersonID');
$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$title = is_scalar($_POST['title'] ?? null) ? trim((string) $_POST['title']) : '';
$body = is_scalar($_POST['body'] ?? null) ? trim((string) $_POST['body']) : '';
$confirmAudience = ($_POST['confirmAudience'] ?? '') === 'Y';
$expectedRecipientSignature = is_scalar($_POST['expectedRecipientSignature'] ?? null)
    ? (string) $_POST['expectedRecipientSignature']
    : '';
$expectedRecipientCount = is_scalar($_POST['expectedRecipientCount'] ?? null)
    ? (int) $_POST['expectedRecipientCount']
    : -1;

if ($groupID === '' || $title === '' || mb_strlen($title) > 100
    || $body === '' || mb_strlen(strip_tags($body)) > 10000
    || !$confirmAudience || !preg_match('/^[a-f0-9]{64}$/', $expectedRecipientSignature)) {
    $redirect('error1');
}

$group = schoolCommunityAccessibleGroup($pdo, $groupID, $currentPersonID, $roleCategory);
if (empty($group) || !schoolCommunityGroupMessagesAllowed($group)) {
    $redirect('error0');
}

$members = $pdo->select(
    "SELECT member.gibbonPersonID, gibbonPerson.status, gibbonRole.category AS roleCategory
     FROM message_group_member AS member
     JOIN gibbonPerson ON gibbonPerson.gibbonPersonID=member.gibbonPersonID
     JOIN gibbonRole ON gibbonRole.gibbonRoleID=gibbonPerson.gibbonRoleIDPrimary
     WHERE member.groupID=:groupID",
    ['groupID' => $groupID]
)->fetchAll(PDO::FETCH_ASSOC);
$audience = schoolCommunityGroupAudience($members, $group['accessControl']);
$recipientIDs = array_map('strval', array_column($audience['eligible'], 'gibbonPersonID'));
sort($recipientIDs, SORT_STRING);
$actualRecipientSignature = hash('sha256', implode(',', $recipientIDs));

$currentPersonIsRecipient = count(array_filter($recipientIDs, function ($recipientID) use ($currentPersonID) {
    return schoolCommunitySamePersonID($recipientID, $currentPersonID);
})) === 1;
if (!$currentPersonIsRecipient && !schoolCommunityCanSelectAllLinkedSources($currentPersonID)) {
    $redirect('error0');
}
if ($expectedRecipientCount !== count($recipientIDs) || !hash_equals($actualRecipientSignature, $expectedRecipientSignature)) {
    $redirect('error3');
}

try {
    $pdo->beginTransaction();
    $inserted = $pdo->insert(
        "INSERT INTO message_group_message (groupID, senderID, title, body)
         VALUES (:groupID, :senderID, :title, :body)",
        ['groupID' => $groupID, 'senderID' => $currentPersonID, 'title' => $title, 'body' => $body]
    );
    if (empty($inserted)) {
        throw new RuntimeException('Unable to create group message.');
    }
    foreach ($recipientIDs as $recipientID) {
        $pdo->insert(
            "INSERT INTO message_group_message_recipient (groupMessageID, gibbonPersonID)
             VALUES (:groupMessageID, :gibbonPersonID)",
            ['groupMessageID' => $inserted, 'gibbonPersonID' => $recipientID]
        );
    }
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $redirect('error2');
}

try {
    schoolCommunityRestoreThreadStates($pdo, 'group', $groupID, $currentPersonID);
} catch (\Throwable $e) {
    // The message is stored; archive cleanup must not lose it.
}

$senderName = trim((string) $session->get('preferredName').' '.(string) $session->get('surname'));
$notification = $container->get(NotificationGateway::class);
foreach ($recipientIDs as $memberID) {
    if (schoolCommunitySamePersonID($memberID, $currentPersonID)) {
        continue;
    }
    try {
        $notification->insertNotification([
            'gibbonPersonID' => $memberID,
            'text'           => sprintf(schoolCommunityT('New group message from %s in %s: %s'), $senderName, $group['groupName'], $title),
            'moduleName'     => 'School Community',
            'actionLink'     => '/index.php?q=/modules/School Community/message_groupView.php&groupID='.urlencode($groupID).'#group-message-'.urlencode((string) $inserted),
        ]);
    } catch (\Throwable $e) {
        // The message is already stored; notification failure must not lose it.
    }
}

header('Location: '.$URL.'&return=success0#group-message-'.urlencode((string) $inserted));
exit;
