<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Session\TokenHandler;

require_once '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$currentPersonID = (string) $session->get('gibbonPersonID');
$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$gibbonSchoolYearID = (string) $session->get('gibbonSchoolYearID');
$categoryID = is_scalar($_POST['categoryID'] ?? null) ? trim((string) $_POST['categoryID']) : '';
$groupTarget = is_scalar($_POST['groupTarget'] ?? null) ? trim((string) $_POST['groupTarget']) : '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/School Community/message_sending.php&categoryID='.urlencode($categoryID).'&groupMessage=Y';
$redirect = function (string $return) use ($URL) {
    header('Location: '.$URL.'&return='.$return);
    exit;
};
$openGroup = function (string $groupID) use ($session) {
    header('Location: '.$session->get('absoluteURL').'/index.php?q=/modules/School Community/message_groupView.php&groupID='.urlencode($groupID));
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

$_POST = $container->get(Validator::class)->sanitize($_POST);
if ($categoryID === '' || $groupTarget === '') {
    $redirect('error1');
}
$category = $pdo->selectOne(
    "SELECT categoryID, accessControl, groupMessages
     FROM message_category
     WHERE categoryID=:categoryID",
    ['categoryID' => $categoryID]
);
if (empty($category) || !schoolCommunityCategoryAllows($category['accessControl'], $roleCategory)) {
    $redirect('error0');
}
if (!schoolCommunityGroupMessagesAllowed($category)) {
    $redirect('error1');
}

if (str_starts_with($groupTarget, 'group:')) {
    $groupID = substr($groupTarget, 6);
    $group = schoolCommunityAccessibleGroup($pdo, $groupID, $currentPersonID, $roleCategory);
    if (empty($group) || (string) $group['categoryID'] !== $categoryID) {
        $redirect('error0');
    }
    $openGroup($groupID);
}

[$sourceType, $sourceID] = array_pad(explode(':', $groupTarget, 2), 2, '');
$sources = schoolCommunityLinkedSourceOptions($pdo, $currentPersonID, $gibbonSchoolYearID);
if (!in_array($sourceType, ['formGroup', 'courseClass', 'gibbonGroup'], true)
    || empty($sourceID)
    || !array_key_exists($groupTarget, $sources)) {
    $redirect('error0');
}

$existingGroupID = $pdo->selectOne(
    "SELECT groupID FROM message_group
     WHERE sourceType=:sourceType AND sourceID=:sourceID
     LIMIT 1",
    ['sourceType' => $sourceType, 'sourceID' => $sourceID]
);
if (!empty($existingGroupID)) {
    $group = schoolCommunityAccessibleGroup($pdo, $existingGroupID, $currentPersonID, $roleCategory);
    if (empty($group) || (string) $group['categoryID'] !== $categoryID) {
        $redirect('error0');
    }
    $openGroup($existingGroupID);
}

try {
    $pdo->beginTransaction();
    $inserted = $pdo->insert(
        "INSERT INTO message_group (groupName, categoryID, ownerID, sourceType, sourceID)
         VALUES (:groupName, :categoryID, :ownerID, :sourceType, :sourceID)",
        [
            'groupName' => $sources[$groupTarget],
            'categoryID'=> $categoryID,
            'ownerID'   => $currentPersonID,
            'sourceType'=> $sourceType,
            'sourceID'  => $sourceID,
        ]
    );
    if (empty($inserted)) {
        throw new RuntimeException('Unable to create group.');
    }
    $pdo->insert(
        "INSERT INTO message_group_member (groupID, gibbonPersonID)
         VALUES (:groupID, :gibbonPersonID)
         ON DUPLICATE KEY UPDATE joinedAt=joinedAt",
        ['groupID' => $inserted, 'gibbonPersonID' => $currentPersonID]
    );
    schoolCommunitySyncLinkedGroupMembers($pdo, [
        'groupID'   => $inserted,
        'sourceType'=> $sourceType,
        'sourceID'  => $sourceID,
    ]);
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $redirect('error2');
}

$openGroup((string) $inserted);
