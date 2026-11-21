<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Session\TokenHandler;

require_once '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$action = is_scalar($_POST['action'] ?? null) ? (string) $_POST['action'] : '';
$base = $action === 'create' ? 'message_groups.php' : 'message_groupMembers.php';
$groupID = is_scalar($_POST['groupID'] ?? null) ? trim((string) $_POST['groupID']) : '';
$URL = $session->get('absoluteURL').'/index.php?q=/modules/School Community/'.$base;
if ($action !== 'create' && $groupID !== '') {
    $URL .= '&groupID='.urlencode($groupID);
}
$redirect = function (string $return) use ($URL) {
    header('Location: '.$URL.'&return='.$return);
    exit;
};

if (!in_array($action, ['create', 'addMember', 'removeMember'], true)) {
    $redirect('error1');
}

$canRead = isActionAccessible($guid, $connection2, '/modules/School Community/message_reading.php');
$canSend = isActionAccessible($guid, $connection2, '/modules/School Community/message_sending.php');
$canManageGroups = isActionAccessible($guid, $connection2, '/modules/School Community/message_groupManage.php');
if (($action === 'create' && !$canManageGroups && !schoolCommunityMessagingAccessAllowed($canSend, $canRead))
    || ($action !== 'create' && !$canRead && !$canManageGroups)) {
    $redirect('error0');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $redirect('error1');
}
if (!$container->get(TokenHandler::class)->validateCsrfToken()) {
    $redirect('error9');
}

$_POST = $container->get(Validator::class)->sanitize($_POST);
$currentPersonID = (string) $session->get('gibbonPersonID');
$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$gibbonSchoolYearID = (string) $session->get('gibbonSchoolYearID');

if ($action === 'create') {
    $groupName = is_scalar($_POST['groupName'] ?? null) ? trim((string) $_POST['groupName']) : '';
    $categoryID = is_scalar($_POST['categoryID'] ?? null) ? trim((string) $_POST['categoryID']) : '';
    $linkedSource = is_scalar($_POST['linkedSource'] ?? null) ? trim((string) $_POST['linkedSource']) : '';
    $sourceType = 'custom';
    $sourceID = null;

    if ($linkedSource !== 'custom') {
        $sources = schoolCommunityLinkedSourceOptions($pdo, $currentPersonID, $gibbonSchoolYearID);
        if (!array_key_exists($linkedSource, $sources)) {
            $redirect('error0');
        }
        [$sourceType, $sourceID] = array_pad(explode(':', $linkedSource, 2), 2, null);
        if (!in_array($sourceType, ['formGroup', 'courseClass', 'gibbonGroup'], true) || empty($sourceID)) {
            $redirect('error1');
        }
        if ($groupName === '') {
            $groupName = $sources[$linkedSource];
        }
    }

    if (($sourceType === 'custom' && $groupName === '')
        || mb_strlen($groupName) > 100 || $categoryID === '') {
        $redirect('error1');
    }

    $category = $pdo->selectOne(
        "SELECT categoryID, accessControl, groupMessages
         FROM message_category
         WHERE categoryID=:categoryID",
        ['categoryID' => $categoryID]
    );
    if (empty($category)
        || !schoolCommunityCategoryAllows($category['accessControl'], $roleCategory)
        || !schoolCommunityGroupMessagesAllowed($category)) {
        $redirect('error0');
    }

    if ($sourceType !== 'custom') {
        $existingGroupID = $pdo->selectOne(
            "SELECT groupID FROM message_group
             WHERE sourceType=:sourceType AND sourceID=:sourceID
             LIMIT 1",
            ['sourceType' => $sourceType, 'sourceID' => $sourceID]
        );
        if (!empty($existingGroupID)) {
            header('Location: '.$session->get('absoluteURL').'/index.php?q=/modules/School Community/message_groupView.php&groupID='.urlencode((string) $existingGroupID).'&return=error4');
            exit;
        }
    }

    try {
        $pdo->beginTransaction();
        $inserted = $pdo->insert(
            "INSERT INTO message_group (groupName, categoryID, ownerID, sourceType, sourceID)
             VALUES (:groupName, :categoryID, :ownerID, :sourceType, :sourceID)",
            [
                'groupName' => $groupName,
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
             VALUES (:groupID, :gibbonPersonID)",
            ['groupID' => $inserted, 'gibbonPersonID' => $currentPersonID]
        );
        schoolCommunityAddLinkedGroupMembers($pdo, [
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

    header('Location: '.$session->get('absoluteURL').'/index.php?q=/modules/School Community/message_groupView.php&groupID='.urlencode((string) $inserted).'&return=success3');
    exit;
}

$group = $groupID !== ''
    ? ($canManageGroups
        ? schoolCommunityManageableGroup($pdo, $groupID)
        : schoolCommunityAccessibleGroup($pdo, $groupID, $currentPersonID, $roleCategory, true))
    : null;
if (empty($group)) {
    $redirect('error0');
}

$memberID = is_scalar($_POST['gibbonPersonID'] ?? null) ? trim((string) $_POST['gibbonPersonID']) : '';
if ($memberID === '') {
    $redirect('error1');
}

if ($action === 'addMember') {
    $member = $pdo->selectOne(
        "SELECT gibbonPerson.gibbonPersonID, gibbonRole.category AS roleCategory
         FROM gibbonPerson
         JOIN gibbonRole ON gibbonRole.gibbonRoleID=gibbonPerson.gibbonRoleIDPrimary
         WHERE gibbonPerson.gibbonPersonID=:gibbonPersonID
           AND gibbonPerson.status='Full'",
        ['gibbonPersonID' => $memberID]
    );
    if (empty($member) || !schoolCommunityCategoryAllows($group['accessControl'], $member['roleCategory'])) {
        $redirect('error1');
    }
    $pdo->insert(
        "INSERT INTO message_group_member (groupID, gibbonPersonID)
         VALUES (:groupID, :gibbonPersonID)
         ON DUPLICATE KEY UPDATE joinedAt=joinedAt",
        ['groupID' => $groupID, 'gibbonPersonID' => $memberID]
    );
    $redirect('success1');
}

if (schoolCommunitySamePersonID($group['ownerID'], $memberID)) {
    $redirect('error1');
}
$deleted = $pdo->delete(
    "DELETE FROM message_group_member
     WHERE groupID=:groupID AND gibbonPersonID=:gibbonPersonID",
    ['groupID' => $groupID, 'gibbonPersonID' => $memberID]
);
$redirect($deleted === 1 ? 'success2' : 'error1');
