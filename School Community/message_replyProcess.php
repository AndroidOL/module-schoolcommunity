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

$messageID = is_scalar($_POST['messageID'] ?? null) ? trim((string) $_POST['messageID']) : '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/School Community/message_view.php&messageID='.urlencode($messageID);
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

$_POST = $container->get(Validator::class)->sanitize($_POST, ['body' => 'HTML']);
$body = is_scalar($_POST['body'] ?? null) ? trim((string) $_POST['body']) : '';
$currentPersonID = (string) $session->get('gibbonPersonID');

$message = $messageID !== '' ? schoolCommunityAccessibleMessage($pdo, $messageID, $currentPersonID) : null;
if ($messageID === '' || $body === '' || mb_strlen(strip_tags($body)) > 5000 || empty($message)) {
    $redirect('error1');
}

$inserted = $pdo->insert(
    "INSERT INTO message_reply (messageID, senderID, body)
     VALUES (:messageID, :senderID, :body)",
    ['messageID' => $messageID, 'senderID' => $currentPersonID, 'body' => $body]
);

if (!empty($inserted)) {
    try {
        $recipientID = (string) $message['senderID'] === $currentPersonID
            ? (string) $message['receiverID']
            : (string) $message['senderID'];
        if ($recipientID !== $currentPersonID) {
            $replyAuthor = trim((string) $session->get('preferredName').' '.(string) $session->get('surname'));
            $container->get(NotificationGateway::class)->insertNotification([
                'gibbonPersonID' => $recipientID,
                'text'           => sprintf(schoolCommunityT('New reply from %s: %s'), $replyAuthor, $message['title']),
                'moduleName'     => 'School Community',
                'actionLink'     => '/index.php?q=/modules/School Community/message_view.php&messageID='.urlencode($messageID),
            ]);
        }
    } catch (\Throwable $e) {
        // The reply is already stored; notification failure must not lose it.
    }
}

$redirect(empty($inserted) ? 'error2' : 'success1');
