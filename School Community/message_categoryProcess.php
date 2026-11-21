<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Session\TokenHandler;

require_once '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/School Community/message_categorySetting.php';
$redirect = function (string $return) use ($URL) {
    header('Location: '.$URL.'&return='.$return);
    exit;
};

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_categorySetting.php')) {
    $redirect('error0');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $redirect('error1');
}
if (!$container->get(TokenHandler::class)->validateCsrfToken()) {
    $redirect('error9');
}

$_POST = $container->get(Validator::class)->sanitize($_POST);

$action = is_scalar($_POST['action'] ?? null) ? (string) $_POST['action'] : '';
$categoryID = is_scalar($_POST['categoryID'] ?? null) ? trim((string) $_POST['categoryID']) : '';
$categoryName = is_scalar($_POST['categoryName'] ?? null) ? trim((string) $_POST['categoryName']) : '';
$currentPersonID = (string) $session->get('gibbonPersonID');

if ($action === 'delete') {
    if ($categoryID === '') {
        $redirect('error1');
    }
    $inUse = $pdo->selectOne(
        "SELECT 1 FROM message_totals WHERE categoryID=:categoryID LIMIT 1",
        ['categoryID' => $categoryID]
    );
    if (empty($inUse)) {
        $inUse = $pdo->selectOne(
            "SELECT 1 FROM message_group WHERE categoryID=:categoryID LIMIT 1",
            ['categoryID' => $categoryID]
        );
    }
    if (!empty($inUse)) {
        $redirect('error3');
    }
    $deleted = $pdo->delete(
        "DELETE FROM message_category WHERE categoryID=:categoryID",
        ['categoryID' => $categoryID]
    );
    $redirect($deleted === 1 ? 'success2' : 'error2');
}

if (!in_array($action, ['add', 'edit'], true) || $categoryName === '' || mb_strlen($categoryName) > 100) {
    $redirect('error1');
}

$values = [
    'staff'   => ($_POST['staff'] ?? 'N') === 'Y' ? 'Y' : 'N',
    'student' => ($_POST['student'] ?? 'N') === 'Y' ? 'Y' : 'N',
    'parent'  => ($_POST['parent'] ?? 'N') === 'Y' ? 'Y' : 'N',
    'other'   => ($_POST['other'] ?? 'N') === 'Y' ? 'Y' : 'N',
];
$groupMessages = ($_POST['groupMessages'] ?? 'N') === 'Y' ? 'Y' : 'N';
$accessControl = schoolCommunityPermissionValue($values);
if ($accessControl === 0) {
    $redirect('error1');
}

if ($action === 'add') {
    $inserted = $pdo->insert(
        "INSERT INTO message_category (categoryName, userID, accessControl, groupMessages)
         VALUES (:categoryName, :userID, :accessControl, :groupMessages)",
        [
            'categoryName' => $categoryName,
            'userID' => $currentPersonID,
            'accessControl' => $accessControl,
            'groupMessages' => $groupMessages,
        ]
    );
    $redirect(empty($inserted) ? 'error2' : 'success0');
}

if ($categoryID === '') {
    $redirect('error1');
}
if (empty($pdo->selectOne(
    "SELECT categoryID FROM message_category WHERE categoryID=:categoryID",
    ['categoryID' => $categoryID]
))) {
    $redirect('error1');
}
$pdo->update(
    "UPDATE message_category
     SET categoryName=:categoryName, accessControl=:accessControl, groupMessages=:groupMessages
     WHERE categoryID=:categoryID",
    [
        'categoryName' => $categoryName,
        'accessControl' => $accessControl,
        'groupMessages' => $groupMessages,
        'categoryID' => $categoryID,
    ]
);
$redirect('success1');
