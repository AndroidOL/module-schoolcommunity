<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

use Gibbon\Domain\DataSet;
use Gibbon\Data\Validator;
use Gibbon\Services\Format;

function schoolCommunityT($text, array $params = []): string
{
    global $session;

    if (function_exists('__m') && is_object($session) && method_exists($session, 'has') && $session->has('module')) {
        $translated = __m($text, $params);

        return $translated !== $text ? $translated : __($text, $params);
    }

    return __($text, $params);
}

function schoolCommunityRoleBit($roleCategory): int
{
    return [
        'Staff'   => 8,
        'Student' => 4,
        'Parent'  => 2,
        'Other'   => 1,
    ][$roleCategory] ?? 0;
}

function schoolCommunityCategoryAllows($accessControl, $roleCategory): bool
{
    $bit = schoolCommunityRoleBit($roleCategory);

    return $bit > 0 && (((int) $accessControl & $bit) === $bit);
}

function schoolCommunityGroupMessagesAllowed(array $category): bool
{
    return ($category['groupMessages'] ?? 'N') === 'Y';
}

function schoolCommunityYesNoLabel($value): string
{
    return $value === 'Y' ? schoolCommunityT('Enabled') : schoolCommunityT('Disabled');
}

function schoolCommunityMessagingAccessAllowed(bool $canSend, bool $canRead): bool
{
    return $canSend && $canRead;
}

function schoolCommunitySamePersonID($left, $right): bool
{
    if (!is_scalar($left) || !is_scalar($right)
        || !ctype_digit((string) $left) || !ctype_digit((string) $right)) {
        return false;
    }

    return (int) $left === (int) $right;
}

function schoolCommunityPermissionValue(array $values): int
{
    $value = 0;
    foreach (['staff' => 8, 'student' => 4, 'parent' => 2, 'other' => 1] as $key => $bit) {
        if (($values[$key] ?? 'N') === 'Y') {
            $value |= $bit;
        }
    }

    return $value;
}

function schoolCommunityPermissionLabel($accessControl): string
{
    $labels = [];
    foreach (['Staff' => 8, 'Student' => 4, 'Parent' => 2, 'Other' => 1] as $label => $bit) {
        if (((int) $accessControl & $bit) === $bit) {
            $labels[] = schoolCommunityT($label);
        }
    }

    return implode(', ', $labels);
}

function schoolCommunityCategoryOptions($pdo, $roleCategory, bool $includeAll = false, bool $groupMessagesOnly = false): array
{
    $result = $pdo->select(
        "SELECT categoryID, categoryName, accessControl, groupMessages
         FROM message_category
         ORDER BY categoryName"
    )->fetchAll(PDO::FETCH_ASSOC);

    $options = [];
    foreach ($result as $category) {
        if (!$groupMessagesOnly || schoolCommunityGroupMessagesAllowed($category)) {
            if (!$includeAll && !schoolCommunityCategoryAllows($category['accessControl'], $roleCategory)) {
                continue;
            }
            $options[(string) $category['categoryID']] = $category['categoryName'];
        }
    }

    return $options;
}

function schoolCommunityPersonOptions($pdo, $currentPersonID, ?int $accessControl = null, bool $includeCurrent = false): array
{
    $result = $pdo->select(
        "SELECT gibbonPersonID, title, preferredName, surname, username, roleCategory
         FROM (
             SELECT gibbonPerson.gibbonPersonID, gibbonPerson.title, gibbonPerson.preferredName,
                    gibbonPerson.surname, gibbonPerson.username, gibbonRole.category AS roleCategory
             FROM gibbonPerson
             JOIN gibbonRole ON gibbonRole.gibbonRoleID=gibbonPerson.gibbonRoleIDPrimary
             WHERE gibbonPerson.status='Full'
         ) AS people
         ORDER BY surname, preferredName"
    )->fetchAll(PDO::FETCH_ASSOC);

    $options = [];
    foreach ($result as $person) {
        if (!$includeCurrent && schoolCommunitySamePersonID($person['gibbonPersonID'], $currentPersonID)) {
            continue;
        }
        if ($accessControl !== null && !schoolCommunityCategoryAllows($accessControl, $person['roleCategory'])) {
            continue;
        }
        $name = Format::name($person['title'], $person['preferredName'], $person['surname'], $person['roleCategory'], true);
        $label = $name.' ('.schoolCommunityT($person['roleCategory']).')';
        if ($includeCurrent && schoolCommunitySamePersonID($person['gibbonPersonID'], $currentPersonID)) {
            $label .= ' — '.schoolCommunityT('Self (visible only to you)');
        }
        $options[(string) $person['gibbonPersonID']] = $label;
    }

    return $options;
}

function schoolCommunityGroupSourceLabel($sourceType): string
{
    return [
        'custom'     => schoolCommunityT('Custom Group'),
        'formGroup'  => schoolCommunityT('Administrative Class'),
        'courseClass'=> schoolCommunityT('Course Class'),
        'gibbonGroup'=> schoolCommunityT('Gibbon Group'),
    ][$sourceType] ?? schoolCommunityT('Custom Group');
}

function schoolCommunityLinkedSourceOptions($pdo, $gibbonPersonID, $gibbonSchoolYearID): array
{
    $formGroups = $pdo->select(
        "SELECT gibbonFormGroupID, name
         FROM gibbonFormGroup
         WHERE gibbonSchoolYearID=:gibbonSchoolYearID
           AND (gibbonPersonIDTutor=:gibbonPersonID
             OR gibbonPersonIDTutor2=:gibbonPersonID
             OR gibbonPersonIDTutor3=:gibbonPersonID)
         ORDER BY LENGTH(name), name",
        ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'gibbonPersonID' => $gibbonPersonID]
    )->fetchAll(PDO::FETCH_ASSOC);

    $courseClasses = $pdo->select(
        "SELECT gibbonCourse.nameShort AS courseName, gibbonCourseClass.gibbonCourseClassID,
                gibbonCourseClass.nameShort AS className
         FROM gibbonCourseClassPerson
         JOIN gibbonCourseClass ON gibbonCourseClass.gibbonCourseClassID=gibbonCourseClassPerson.gibbonCourseClassID
         JOIN gibbonCourse ON gibbonCourse.gibbonCourseID=gibbonCourseClass.gibbonCourseID
         WHERE gibbonCourse.gibbonSchoolYearID=:gibbonSchoolYearID
           AND gibbonCourseClassPerson.gibbonPersonID=:gibbonPersonID
           AND gibbonCourseClassPerson.role IN ('Teacher', 'Assistant', 'Technician')
           AND gibbonCourseClassPerson.role NOT LIKE '% - Left%'
         ORDER BY gibbonCourse.nameShort, gibbonCourseClass.nameShort",
        ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'gibbonPersonID' => $gibbonPersonID]
    )->fetchAll(PDO::FETCH_ASSOC);

    $gibbonGroups = $pdo->select(
        "SELECT gibbonGroup.gibbonGroupID, gibbonGroup.name
         FROM gibbonGroup
         WHERE gibbonGroup.gibbonSchoolYearID=:gibbonSchoolYearID
           AND (
               gibbonGroup.gibbonPersonIDOwner=:gibbonPersonID
               OR EXISTS (
                   SELECT 1 FROM gibbonGroupPerson
                   WHERE gibbonGroupPerson.gibbonGroupID=gibbonGroup.gibbonGroupID
                     AND gibbonGroupPerson.gibbonPersonID=:gibbonPersonID
               )
           )
         ORDER BY gibbonGroup.name",
        ['gibbonSchoolYearID' => $gibbonSchoolYearID, 'gibbonPersonID' => $gibbonPersonID]
    )->fetchAll(PDO::FETCH_ASSOC);

    $options = [];
    foreach ($formGroups as $formGroup) {
        $options['formGroup:'.$formGroup['gibbonFormGroupID']] = sprintf(
            schoolCommunityT('Administrative Class: %s'),
            $formGroup['name']
        );
    }
    foreach ($courseClasses as $courseClass) {
        $options['courseClass:'.$courseClass['gibbonCourseClassID']] = sprintf(
            schoolCommunityT('Course Class: %s.%s'),
            $courseClass['courseName'],
            $courseClass['className']
        );
    }
    foreach ($gibbonGroups as $group) {
        $options['gibbonGroup:'.$group['gibbonGroupID']] = sprintf(
            schoolCommunityT('Gibbon Group: %s'),
            $group['name']
        );
    }

    return $options;
}

function schoolCommunityLinkedSourceMembers($pdo, $sourceType, $sourceID): array
{
    if ($sourceType === 'formGroup') {
        return $pdo->select(
            "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.title, gibbonPerson.preferredName,
                    gibbonPerson.surname, gibbonPerson.status, gibbonRole.category AS roleCategory
             FROM gibbonStudentEnrolment
             JOIN gibbonPerson ON gibbonPerson.gibbonPersonID=gibbonStudentEnrolment.gibbonPersonID
             JOIN gibbonRole ON gibbonRole.gibbonRoleID=gibbonPerson.gibbonRoleIDPrimary
             WHERE gibbonStudentEnrolment.gibbonFormGroupID=:sourceID
               AND gibbonPerson.status='Full'
               AND (gibbonPerson.dateStart IS NULL OR gibbonPerson.dateStart<=CURRENT_DATE)
               AND (gibbonPerson.dateEnd IS NULL OR gibbonPerson.dateEnd>=CURRENT_DATE)
             UNION
             SELECT tutor.gibbonPersonID, tutor.title, tutor.preferredName,
                    tutor.surname, tutor.status, tutorRole.category AS roleCategory
             FROM gibbonFormGroup
             JOIN gibbonPerson AS tutor
                ON tutor.gibbonPersonID IN (gibbonFormGroup.gibbonPersonIDTutor,
                                            gibbonFormGroup.gibbonPersonIDTutor2,
                                            gibbonFormGroup.gibbonPersonIDTutor3)
             JOIN gibbonRole AS tutorRole ON tutorRole.gibbonRoleID=tutor.gibbonRoleIDPrimary
             WHERE gibbonFormGroup.gibbonFormGroupID=:sourceID
               AND tutor.status='Full'
               AND (tutor.dateStart IS NULL OR tutor.dateStart<=CURRENT_DATE)
               AND (tutor.dateEnd IS NULL OR tutor.dateEnd>=CURRENT_DATE)
             ORDER BY surname, preferredName",
            ['sourceID' => $sourceID]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($sourceType === 'courseClass') {
        return $pdo->select(
            "SELECT DISTINCT gibbonPerson.gibbonPersonID, gibbonPerson.title, gibbonPerson.preferredName,
                    gibbonPerson.surname, gibbonPerson.status, gibbonRole.category AS roleCategory
             FROM gibbonCourseClassPerson
             JOIN gibbonPerson ON gibbonPerson.gibbonPersonID=gibbonCourseClassPerson.gibbonPersonID
             JOIN gibbonRole ON gibbonRole.gibbonRoleID=gibbonPerson.gibbonRoleIDPrimary
             WHERE gibbonCourseClassPerson.gibbonCourseClassID=:sourceID
               AND gibbonCourseClassPerson.role IN ('Student', 'Teacher', 'Assistant', 'Technician')
               AND gibbonCourseClassPerson.role NOT LIKE '% - Left%'
               AND gibbonPerson.status='Full'
               AND (gibbonCourseClassPerson.dateEnrolled IS NULL OR gibbonCourseClassPerson.dateEnrolled<=CURRENT_DATE)
               AND (gibbonCourseClassPerson.dateUnenrolled IS NULL OR gibbonCourseClassPerson.dateUnenrolled>=CURRENT_DATE)
               AND (gibbonPerson.dateStart IS NULL OR gibbonPerson.dateStart<=CURRENT_DATE)
               AND (gibbonPerson.dateEnd IS NULL OR gibbonPerson.dateEnd>=CURRENT_DATE)
             ORDER BY gibbonPerson.surname, gibbonPerson.preferredName",
            ['sourceID' => $sourceID]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($sourceType === 'gibbonGroup') {
        return $pdo->select(
            "SELECT gibbonPerson.gibbonPersonID, gibbonPerson.title, gibbonPerson.preferredName,
                    gibbonPerson.surname, gibbonPerson.status, gibbonRole.category AS roleCategory
             FROM gibbonGroup
             JOIN gibbonSchoolYear
               ON gibbonSchoolYear.gibbonSchoolYearID=gibbonGroup.gibbonSchoolYearID
              AND gibbonSchoolYear.status='Current'
             JOIN gibbonPerson
               ON gibbonPerson.gibbonPersonID=gibbonGroup.gibbonPersonIDOwner
             JOIN gibbonRole ON gibbonRole.gibbonRoleID=gibbonPerson.gibbonRoleIDPrimary
             WHERE gibbonGroup.gibbonGroupID=:sourceID
               AND gibbonPerson.status='Full'
               AND (gibbonPerson.dateStart IS NULL OR gibbonPerson.dateStart<=CURRENT_DATE)
               AND (gibbonPerson.dateEnd IS NULL OR gibbonPerson.dateEnd>=CURRENT_DATE)
             UNION
             SELECT gibbonPerson.gibbonPersonID, gibbonPerson.title, gibbonPerson.preferredName,
                    gibbonPerson.surname, gibbonPerson.status, gibbonRole.category AS roleCategory
             FROM gibbonGroup
             JOIN gibbonSchoolYear
               ON gibbonSchoolYear.gibbonSchoolYearID=gibbonGroup.gibbonSchoolYearID
              AND gibbonSchoolYear.status='Current'
             JOIN gibbonGroupPerson ON gibbonGroupPerson.gibbonGroupID=gibbonGroup.gibbonGroupID
             JOIN gibbonPerson ON gibbonPerson.gibbonPersonID=gibbonGroupPerson.gibbonPersonID
             JOIN gibbonRole ON gibbonRole.gibbonRoleID=gibbonPerson.gibbonRoleIDPrimary
             WHERE gibbonGroup.gibbonGroupID=:sourceID
               AND gibbonPerson.status='Full'
               AND (gibbonPerson.dateStart IS NULL OR gibbonPerson.dateStart<=CURRENT_DATE)
               AND (gibbonPerson.dateEnd IS NULL OR gibbonPerson.dateEnd>=CURRENT_DATE)
             ORDER BY gibbonPerson.surname, gibbonPerson.preferredName",
            ['sourceID' => $sourceID]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    return [];
}

function schoolCommunityLinkedSourceAllowsPerson($pdo, $sourceType, $sourceID, $gibbonPersonID): bool
{
    if ($sourceType === 'gibbonGroup') {
        return !empty($pdo->selectOne(
            "SELECT gibbonGroup.gibbonGroupID
             FROM gibbonGroup
             JOIN gibbonSchoolYear
               ON gibbonSchoolYear.gibbonSchoolYearID=gibbonGroup.gibbonSchoolYearID
              AND gibbonSchoolYear.status='Current'
             JOIN gibbonPerson AS currentPerson
               ON currentPerson.gibbonPersonID=:gibbonPersonID
              AND currentPerson.status='Full'
              AND (currentPerson.dateStart IS NULL OR currentPerson.dateStart<=CURRENT_DATE)
              AND (currentPerson.dateEnd IS NULL OR currentPerson.dateEnd>=CURRENT_DATE)
             WHERE gibbonGroup.gibbonGroupID=:sourceID
               AND (
                   gibbonGroup.gibbonPersonIDOwner=:gibbonPersonID
                   OR EXISTS (
                       SELECT 1 FROM gibbonGroupPerson
                       WHERE gibbonGroupPerson.gibbonGroupID=gibbonGroup.gibbonGroupID
                         AND gibbonGroupPerson.gibbonPersonID=:gibbonPersonID
                   )
               )",
            ['sourceID' => $sourceID, 'gibbonPersonID' => $gibbonPersonID]
        ));
    }

    foreach (schoolCommunityLinkedSourceMembers($pdo, $sourceType, $sourceID) as $member) {
        if (schoolCommunitySamePersonID($member['gibbonPersonID'], $gibbonPersonID)) {
            return true;
        }
    }

    return false;
}

function schoolCommunitySyncLinkedGroupMembers($pdo, array $group): void
{
    $sourceType = $group['sourceType'] ?? 'custom';
    $sourceID = $group['sourceID'] ?? null;
    if (!in_array($sourceType, ['formGroup', 'courseClass', 'gibbonGroup'], true) || empty($sourceID)) {
        return;
    }

    $memberIDs = array_values(array_filter(array_map('strval', array_column(
        schoolCommunityLinkedSourceMembers($pdo, $sourceType, $sourceID),
        'gibbonPersonID'
    ))));

    if (empty($memberIDs)) {
        $pdo->delete(
            "DELETE FROM message_group_member WHERE groupID=:groupID",
            ['groupID' => $group['groupID']]
        );
        return;
    }

    $placeholders = [];
    $params = ['groupID' => $group['groupID']];
    foreach ($memberIDs as $index => $memberID) {
        $key = 'person'.$index;
        $placeholders[] = ':'.$key;
        $params[$key] = $memberID;
    }
    $pdo->delete(
        "DELETE FROM message_group_member
         WHERE groupID=:groupID
           AND gibbonPersonID NOT IN (".implode(', ', $placeholders).")",
        $params
    );
    foreach ($memberIDs as $memberID) {
        $pdo->insert(
            "INSERT INTO message_group_member (groupID, gibbonPersonID)
             VALUES (:groupID, :gibbonPersonID)
             ON DUPLICATE KEY UPDATE joinedAt=joinedAt",
            ['groupID' => $group['groupID'], 'gibbonPersonID' => $memberID]
        );
    }
}

function schoolCommunityGroupAccessAllowed(array $group, $gibbonPersonID, $roleCategory, bool $ownerOnly = false): bool
{
    if (!schoolCommunitySamePersonID($group['groupMemberPersonID'] ?? '', $gibbonPersonID)) {
        return false;
    }
    if ($ownerOnly && !schoolCommunitySamePersonID($group['ownerID'] ?? '', $gibbonPersonID)) {
        return false;
    }

    return schoolCommunityCategoryAllows($group['accessControl'] ?? 0, $roleCategory);
}

function schoolCommunityAccessibleGroup($pdo, $groupID, $gibbonPersonID, $roleCategory, bool $ownerOnly = false): ?array
{
    $group = $pdo->selectOne(
        "SELECT message_group.*, message_category.categoryName, message_category.accessControl,
                message_category.groupMessages,
                member.gibbonPersonID AS groupMemberPersonID,
                owner.title AS ownerTitle, owner.preferredName AS ownerPreferredName,
                owner.surname AS ownerSurname, ownerRole.category AS ownerRoleCategory
         FROM message_group
         JOIN message_category ON message_category.categoryID=message_group.categoryID
         LEFT JOIN message_group_member AS member
            ON member.groupID=message_group.groupID
           AND member.gibbonPersonID=:gibbonPersonID
         JOIN gibbonPerson AS owner ON owner.gibbonPersonID=message_group.ownerID
         JOIN gibbonRole AS ownerRole ON ownerRole.gibbonRoleID=owner.gibbonRoleIDPrimary
         WHERE message_group.groupID=:groupID",
        ['groupID' => $groupID, 'gibbonPersonID' => $gibbonPersonID]
    );

    if (empty($group)) {
        return null;
    }

    $sourceType = $group['sourceType'] ?? 'custom';
    if ($sourceType !== 'custom') {
        if (!schoolCommunityLinkedSourceAllowsPerson($pdo, $sourceType, $group['sourceID'] ?? '', $gibbonPersonID)) {
            return null;
        }
        schoolCommunitySyncLinkedGroupMembers($pdo, $group);
        $group['groupMemberPersonID'] = $gibbonPersonID;
    }

    if (!schoolCommunityGroupAccessAllowed($group, $gibbonPersonID, $roleCategory, $ownerOnly)) {
        return null;
    }

    return $group;
}

function schoolCommunityAccessibleGroupMessage($pdo, $groupMessageID, $gibbonPersonID, $roleCategory): ?array
{
    $message = $pdo->selectOne(
        "SELECT message_group_message.*, message_group.categoryID, message_group.groupName,
                message_group.ownerID, message_group.sourceType, message_group.sourceID,
                message_category.categoryName, message_category.accessControl,
                message_category.groupMessages
         FROM message_group_message
         JOIN message_group ON message_group.groupID=message_group_message.groupID
         JOIN message_category ON message_category.categoryID=message_group.categoryID
         JOIN message_group_message_recipient AS recipient
            ON recipient.groupMessageID=message_group_message.groupMessageID
           AND recipient.gibbonPersonID=:gibbonPersonID
         WHERE message_group_message.groupMessageID=:groupMessageID",
        ['groupMessageID' => $groupMessageID, 'gibbonPersonID' => $gibbonPersonID]
    );

    if (empty($message)) {
        return null;
    }

    $group = schoolCommunityAccessibleGroup($pdo, $message['groupID'], $gibbonPersonID, $roleCategory);
    if (empty($group)) {
        return null;
    }

    return $message;
}

function schoolCommunityGroupMemberOptions($pdo, $group, $currentPersonID, array $existingMemberIDs): array
{
    $people = schoolCommunityPersonOptions($pdo, $currentPersonID, (int) $group['accessControl']);

    foreach ($existingMemberIDs as $memberID) {
        unset($people[(string) $memberID]);
    }

    return $people;
}

function schoolCommunityGroupSendTargetOptions($pdo, $currentPersonID, $roleCategory, $categoryID, $gibbonSchoolYearID): array
{
    $groups = $pdo->select(
        "SELECT message_group.groupID, message_group.groupName, message_group.sourceType, message_group.sourceID,
                message_category.accessControl, member.gibbonPersonID AS groupMemberPersonID
         FROM message_group
         JOIN message_category ON message_category.categoryID=message_group.categoryID
         JOIN message_group_member AS member
           ON member.groupID=message_group.groupID
          AND member.gibbonPersonID=:gibbonPersonID
         WHERE message_group.categoryID=:categoryID
         ORDER BY message_group.groupName",
        ['gibbonPersonID' => $currentPersonID, 'categoryID' => $categoryID]
    )->fetchAll(PDO::FETCH_ASSOC);

    $options = [];
    $linkedSources = [];
    foreach ($groups as $group) {
        if (($group['sourceType'] ?? 'custom') !== 'custom') {
            $linkedSources[$group['sourceType'].':'.$group['sourceID']] = true;
        }
        if (schoolCommunityGroupAccessAllowed($group, $currentPersonID, $roleCategory)) {
            $options['group:'.$group['groupID']] = schoolCommunityGroupSourceLabel($group['sourceType'] ?? 'custom')
                .' · '.$group['groupName'];
        }
    }

    $allLinkedSources = $pdo->select(
        "SELECT sourceType, sourceID FROM message_group
         WHERE sourceType IN ('formGroup', 'courseClass', 'gibbonGroup')"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allLinkedSources as $source) {
        $linkedSources[$source['sourceType'].':'.$source['sourceID']] = true;
    }

    foreach (schoolCommunityLinkedSourceOptions($pdo, $currentPersonID, $gibbonSchoolYearID) as $key => $label) {
        if (!isset($linkedSources[$key])) {
            $options[$key] = $label.' · '.schoolCommunityT('Create Group');
        }
    }

    return $options;
}

function schoolCommunityGroupAudience(array $members, $accessControl): array
{
    $audience = ['eligible' => [], 'excluded' => []];
    foreach ($members as $member) {
        $isEligible = ($member['status'] ?? '') === 'Full'
            && schoolCommunityCategoryAllows($accessControl, $member['roleCategory'] ?? '');
        $audience[$isEligible ? 'eligible' : 'excluded'][] = $member;
    }

    return $audience;
}

function schoolCommunityGroupTable(array $groups, $gibbonPersonID): DataSet
{
    $rows = [];
    foreach ($groups as $group) {
        $ownerName = Format::name(
            $group['ownerTitle'],
            $group['ownerPreferredName'],
            $group['ownerSurname'],
            $group['ownerRoleCategory'] ?? 'Staff',
            true
        );

        $rows[] = $group + [
            'ownerName'  => $ownerName,
            'isOwner'    => schoolCommunitySamePersonID($group['ownerID'], $gibbonPersonID),
            'sourceLabel'=> schoolCommunityGroupSourceLabel($group['sourceType'] ?? 'custom'),
            'unreadLabel'=> (int) $group['unreadCount'] > 0 ? (string) (int) $group['unreadCount'] : '',
            'latestPreview' => schoolCommunityMessagePreview($group['latestBody'] ?? ''),
            'isArchived' => !empty($group['archivedAt']),
            'isDeleted'  => !empty($group['deletedAt']),
            'threadType' => 'group',
            'threadID'   => $group['groupID'],
            'url'        => '/index.php?q=/modules/School Community/message_groupView.php&groupID='.urlencode($group['groupID']),
        ];
    }

    return new DataSet($rows);
}

function schoolCommunityThreadState($pdo, $threadType, $threadID, $gibbonPersonID): ?array
{
    if (!in_array($threadType, ['direct', 'group'], true)) {
        return null;
    }

    $state = $pdo->selectOne(
        "SELECT * FROM message_thread_state
         WHERE threadType=:threadType
           AND threadID=:threadID
           AND gibbonPersonID=:gibbonPersonID",
        [
            'threadType'     => $threadType,
            'threadID'       => $threadID,
            'gibbonPersonID' => $gibbonPersonID,
        ]
    );

    return empty($state) ? null : $state;
}

function schoolCommunitySetThreadArchived($pdo, $threadType, $threadID, $gibbonPersonID, bool $archived): bool
{
    if (!in_array($threadType, ['direct', 'group'], true)) {
        return false;
    }

    return $pdo->statement(
        "INSERT INTO message_thread_state
            (threadType, threadID, gibbonPersonID, archivedAt)
         VALUES (:threadType, :threadID, :gibbonPersonID, ".($archived ? 'CURRENT_TIMESTAMP' : 'NULL').")
         ON DUPLICATE KEY UPDATE archivedAt=VALUES(archivedAt)",
        [
            'threadType'     => $threadType,
            'threadID'       => $threadID,
            'gibbonPersonID' => $gibbonPersonID,
        ]
    );
}

function schoolCommunitySetThreadDeleted($pdo, $threadType, $threadID, $gibbonPersonID, bool $deleted): bool
{
    if (!in_array($threadType, ['direct', 'group'], true)) {
        return false;
    }

    return $pdo->statement(
        "INSERT INTO message_thread_state
            (threadType, threadID, gibbonPersonID, deletedAt)
         VALUES (:threadType, :threadID, :gibbonPersonID, ".($deleted ? 'CURRENT_TIMESTAMP' : 'NULL').")
         ON DUPLICATE KEY UPDATE deletedAt=VALUES(deletedAt)",
        [
            'threadType'     => $threadType,
            'threadID'       => $threadID,
            'gibbonPersonID' => $gibbonPersonID,
        ]
    );
}

function schoolCommunityRestoreThreadStates($pdo, $threadType, $threadID): void
{
    if (!in_array($threadType, ['direct', 'group'], true)) {
        return;
    }

    $pdo->delete(
        "DELETE FROM message_thread_state
         WHERE threadType=:threadType AND threadID=:threadID",
        ['threadType' => $threadType, 'threadID' => $threadID]
    );
}

function schoolCommunityMessagePreview($body, int $length = 160): string
{
    $body = schoolCommunityDecodeLegacyBody((string) $body);
    if (!mb_check_encoding($body, 'UTF-8')) {
        $body = mb_convert_encoding($body, 'UTF-8', 'UTF-8');
    }
    $body = preg_replace('/<[^>]+>/', ' ', $body) ?? $body;
    $body = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $body = trim(preg_replace('/\s+/u', ' ', $body) ?? '');

    return mb_strlen($body) > $length ? mb_substr($body, 0, $length).'…' : $body;
}

function schoolCommunityPriorityOptions(): array
{
    return [
        'h' => schoolCommunityT('High'),
        'm' => schoolCommunityT('Normal'),
        'l' => schoolCommunityT('Notice'),
    ];
}

function schoolCommunityPriorityToDatabase($priority)
{
    return ['h' => 1, 'm' => null, 'l' => 0][$priority] ?? null;
}

function schoolCommunityPriorityLabel($priority): string
{
    if ($priority === 1 || $priority === '1' || $priority === true) {
        return schoolCommunityT('High');
    }
    if ($priority === 0 || $priority === '0' || $priority === false) {
        return schoolCommunityT('Notice');
    }

    return schoolCommunityT('Normal');
}

function schoolCommunityAccessibleMessage($pdo, $messageID, $gibbonPersonID): ?array
{
    $message = $pdo->selectOne(
        "SELECT message_totals.*, message_category.categoryName,
                sender.title AS senderTitle, sender.preferredName AS senderPreferredName, sender.surname AS senderSurname,
                receiver.title AS receiverTitle, receiver.preferredName AS receiverPreferredName, receiver.surname AS receiverSurname
         FROM message_totals
         JOIN message_category ON message_category.categoryID=message_totals.categoryID
         JOIN gibbonPerson AS sender ON sender.gibbonPersonID=message_totals.senderID
         JOIN gibbonPerson AS receiver ON receiver.gibbonPersonID=message_totals.receiverID
         WHERE message_totals.messageID=:messageID
           AND (message_totals.senderID=:senderID OR message_totals.receiverID=:receiverID)",
        [
            'messageID' => $messageID,
            'senderID'  => $gibbonPersonID,
            'receiverID'=> $gibbonPersonID,
        ]
    );

    return empty($message) ? null : $message;
}

function schoolCommunityMessageTable(array $messages, $gibbonPersonID): DataSet
{
    $rows = [];
    foreach ($messages as $message) {
        $isSent = schoolCommunitySamePersonID($message['senderID'], $gibbonPersonID);
        $isSelf = $isSent && schoolCommunitySamePersonID($message['receiverID'], $gibbonPersonID);
        $otherTitle = $isSent ? $message['receiverTitle'] : $message['senderTitle'];
        $otherName = $isSent
            ? trim($message['receiverPreferredName'].' '.$message['receiverSurname'])
            : trim($message['senderPreferredName'].' '.$message['senderSurname']);
        $senderName = trim($message['senderTitle'].' '.$message['senderPreferredName'].' '.$message['senderSurname']);
        $receiverName = trim($message['receiverTitle'].' '.$message['receiverPreferredName'].' '.$message['receiverSurname']);

        $rows[] = $message + [
            'direction'     => $isSelf ? schoolCommunityT('Sent / Received') : ($isSent ? schoolCommunityT('Sent') : schoolCommunityT('Received')),
            'otherPerson'   => trim($otherTitle.' '.$otherName),
            'senderName'    => $senderName,
            'receiverName'  => $receiverName,
            'priorityLabel' => schoolCommunityPriorityLabel($message['priority']),
            'isUnread'      => (!$isSent || $isSelf) && empty($message['readTimestamp']),
            'preview'       => schoolCommunityMessagePreview($message['body'] ?? ''),
            'isArchived'    => !empty($message['archivedAt']),
            'isDeleted'     => !empty($message['deletedAt']),
            'threadType'    => 'direct',
            'threadID'      => $message['messageID'],
            'url'           => '/index.php?q=/modules/School Community/message_view.php&messageID='.urlencode($message['messageID']),
        ];
    }

    return new DataSet($rows);
}

function schoolCommunityBodyForDisplay($container, $body): string
{
    $body = schoolCommunityDecodeLegacyBody((string) $body);

    $validated = $container->get(Validator::class)->sanitize(['body' => $body], ['body' => 'HTML']);

    return $validated['body'];
}

function schoolCommunityDecodeLegacyBody(string $body): string
{
    if (!preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $body)) {
        return $body;
    }

    $decoded = base64_decode($body, true);
    if ($decoded === false
        || !mb_check_encoding($decoded, 'UTF-8')
        || !preg_match('/<[a-z][^>]*>/i', $decoded)) {
        return $body;
    }

    return $decoded;
}
