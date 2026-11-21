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

$postedCategoryID = is_scalar($_POST['categoryID'] ?? null) ? trim((string) $_POST['categoryID']) : '';
$recipientMode = is_scalar($_POST['recipientMode'] ?? null) ? (string) $_POST['recipientMode'] : 'single';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/School Community/message_sending.php';
if ($postedCategoryID !== '' && ctype_digit($postedCategoryID)) {
    $URL .= '&categoryID='.urlencode($postedCategoryID);
    if ($recipientMode === 'multi') {
        $URL .= '&groupMessage=Y&targetType=free';
    }
}
if ($recipientMode === 'multi') {
    $URL = $session->get('absoluteURL').'/index.php?q=/modules/School Community/message_groupCompose.php'
        .'&categoryID='.urlencode($postedCategoryID).'&targetType=free';
}
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
$categoryID = is_scalar($_POST['categoryID'] ?? null) ? trim((string) $_POST['categoryID']) : '';
$priority = is_scalar($_POST['priority'] ?? null) ? (string) $_POST['priority'] : '';
$recipientID = is_scalar($_POST['recipientID'] ?? null) ? trim((string) $_POST['recipientID']) : '';

if ($title === '' || mb_strlen($title) > 100 || $body === '' || mb_strlen(strip_tags($body)) > 10000
    || $categoryID === '' || !array_key_exists($priority, schoolCommunityPriorityOptions())) {
    $redirect('error1');
}

$category = $pdo->selectOne(
    "SELECT categoryID, accessControl FROM message_category WHERE categoryID=:categoryID",
    ['categoryID' => $categoryID]
);
if (empty($category) || !schoolCommunityCategoryAllows($category['accessControl'], $roleCategory)) {
    $redirect('error0');
}

if ($recipientMode === 'multi') {
    $selected = $_POST['recipientIDs'] ?? [];
    $selected = is_array($selected)
        ? array_values(array_unique(array_filter(array_map('strval', $selected), function ($recipientID) {
            return ctype_digit($recipientID) && $recipientID !== '';
        })))
        : [];
    if (count($selected) < 1) {
        $redirect('error1');
    }
    if (count($selected) > 5) {
        $redirect('error4');
    }

    $placeholders = [];
    $params = [];
    foreach ($selected as $index => $recipientID) {
        $key = 'person'.$index;
        $placeholders[] = ':'.$key;
        $params[$key] = $recipientID;
    }
    $recipients = $pdo->select(
        "SELECT gibbonPerson.gibbonPersonID, gibbonRole.category AS roleCategory
         FROM gibbonPerson
         JOIN gibbonRole ON gibbonRole.gibbonRoleID=gibbonPerson.gibbonRoleIDPrimary
         WHERE gibbonPerson.status='Full'
           AND gibbonPerson.gibbonPersonID IN (".implode(', ', $placeholders).')',
        $params
    )->fetchAll(PDO::FETCH_ASSOC);

    $validRecipients = [];
    foreach ($recipients as $recipient) {
        if (schoolCommunityCategoryAllows($category['accessControl'], $recipient['roleCategory'])) {
            $validRecipients[] = (string) $recipient['gibbonPersonID'];
        }
    }
    sort($validRecipients, SORT_STRING);
    sort($selected, SORT_STRING);
    if ($validRecipients !== $selected) {
        $redirect('error1');
    }

    try {
        $pdo->beginTransaction();
        $insertedIDs = [];
        foreach ($selected as $recipientID) {
            $inserted = $pdo->insert(
                "INSERT INTO message_totals (title, body, categoryID, priority, senderID, receiverID)
                 VALUES (:title, :body, :categoryID, :priority, :senderID, :receiverID)",
                [
                    'title'      => $title,
                    'body'       => $body,
                    'categoryID' => $categoryID,
                    'priority'   => schoolCommunityPriorityToDatabase($priority),
                    'senderID'   => $currentPersonID,
                    'receiverID' => $recipientID,
                ]
            );
            if (empty($inserted)) {
                throw new RuntimeException('Unable to create message.');
            }
            $insertedIDs[] = (string) $inserted;
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $redirect('error2');
    }

    $senderName = trim((string) $session->get('preferredName').' '.(string) $session->get('surname'));
    $notification = $container->get(NotificationGateway::class);
    foreach ($insertedIDs as $index => $insertedID) {
        try {
            schoolCommunityRestoreThreadStates($pdo, 'direct', $insertedID, $currentPersonID);
        } catch (\Throwable $e) {
        }
        if (schoolCommunitySamePersonID($selected[$index], $currentPersonID)) {
            continue;
        }
        try {
            $notification->insertNotification([
                'gibbonPersonID' => $selected[$index],
                'text'           => sprintf(schoolCommunityT('New message from %s: %s'), $senderName, $title),
                'moduleName'     => 'School Community',
                'actionLink'     => '/index.php?q=/modules/School Community/message_view.php&messageID='.urlencode($insertedID),
            ]);
        } catch (\Throwable $e) {
        }
    }

    header('Location: '.$session->get('absoluteURL').'/index.php?q=/modules/School Community/message_reading.php&return=success10');
    exit;
}

if ($recipientID === '') {
    $redirect('error1');
}

$recipient = $pdo->selectOne(
    "SELECT gibbonPerson.gibbonPersonID, gibbonRole.category AS roleCategory
     FROM gibbonPerson
     JOIN gibbonRole ON gibbonRole.gibbonRoleID=gibbonPerson.gibbonRoleIDPrimary
     WHERE gibbonPerson.gibbonPersonID=:gibbonPersonID AND gibbonPerson.status='Full'",
    ['gibbonPersonID' => $recipientID]
);
if (empty($recipient) || !schoolCommunityCategoryAllows($category['accessControl'], $recipient['roleCategory'])) {
    $redirect('error1');
}

$inserted = $pdo->insert(
    "INSERT INTO message_totals (title, body, categoryID, priority, senderID, receiverID)
     VALUES (:title, :body, :categoryID, :priority, :senderID, :receiverID)",
    [
        'title'     => $title,
        'body'      => $body,
        'categoryID'=> $categoryID,
        'priority'  => schoolCommunityPriorityToDatabase($priority),
        'senderID'  => $currentPersonID,
        'receiverID'=> $recipientID,
    ]
);

if (empty($inserted)) {
    $redirect('error2');
}

try {
    schoolCommunityRestoreThreadStates($pdo, 'direct', $inserted, $currentPersonID);
} catch (\Throwable $e) {
    // The message is stored; archive cleanup must not lose it.
}

if (!schoolCommunitySamePersonID($recipientID, $currentPersonID)) {
    try {
        $senderName = trim((string) $session->get('preferredName').' '.(string) $session->get('surname'));
        $container->get(NotificationGateway::class)->insertNotification([
            'gibbonPersonID' => $recipientID,
            'text'           => sprintf(schoolCommunityT('New message from %s: %s'), $senderName, $title),
            'moduleName'     => 'School Community',
            'actionLink'     => '/index.php?q=/modules/School Community/message_view.php&messageID='.urlencode((string) $inserted),
        ]);
    } catch (\Throwable $e) {
        // The message is already stored; notification failure must not lose it.
    }
}

header('Location: '.$session->get('absoluteURL').'/index.php?q=/modules/School Community/message_view.php&messageID='.urlencode((string) $inserted).'&return=success0');
exit;
