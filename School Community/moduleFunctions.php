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

function schoolCommunityCategoryOptions($pdo, $roleCategory, bool $includeAll = false): array
{
    $result = $pdo->select(
        "SELECT categoryID, categoryName, accessControl
         FROM message_category
         ORDER BY categoryName"
    )->fetchAll(PDO::FETCH_ASSOC);

    $options = [];
    foreach ($result as $category) {
        if ($includeAll || schoolCommunityCategoryAllows($category['accessControl'], $roleCategory)) {
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
                member.gibbonPersonID AS groupMemberPersonID,
                owner.title AS ownerTitle, owner.preferredName AS ownerPreferredName,
                owner.surname AS ownerSurname, ownerRole.category AS ownerRoleCategory
         FROM message_group
         JOIN message_category ON message_category.categoryID=message_group.categoryID
         JOIN message_group_member AS member
            ON member.groupID=message_group.groupID
           AND member.gibbonPersonID=:gibbonPersonID
         JOIN gibbonPerson AS owner ON owner.gibbonPersonID=message_group.ownerID
         JOIN gibbonRole AS ownerRole ON ownerRole.gibbonRoleID=owner.gibbonRoleIDPrimary
         WHERE message_group.groupID=:groupID",
        ['groupID' => $groupID, 'gibbonPersonID' => $gibbonPersonID]
    );

    if (empty($group) || !schoolCommunityGroupAccessAllowed($group, $gibbonPersonID, $roleCategory, $ownerOnly)) {
        return null;
    }

    return $group;
}

function schoolCommunityAccessibleGroupMessage($pdo, $groupMessageID, $gibbonPersonID, $roleCategory): ?array
{
    $message = $pdo->selectOne(
        "SELECT message_group_message.*, message_group.categoryID, message_group.groupName, message_group.ownerID,
                message_category.categoryName, message_category.accessControl,
                member.gibbonPersonID AS groupMemberPersonID
         FROM message_group_message
         JOIN message_group ON message_group.groupID=message_group_message.groupID
         JOIN message_category ON message_category.categoryID=message_group.categoryID
         JOIN message_group_member AS member
            ON member.groupID=message_group.groupID
           AND member.gibbonPersonID=:gibbonPersonID
         JOIN message_group_message_recipient AS recipient
            ON recipient.groupMessageID=message_group_message.groupMessageID
           AND recipient.gibbonPersonID=:gibbonPersonID
         WHERE message_group_message.groupMessageID=:groupMessageID",
        ['groupMessageID' => $groupMessageID, 'gibbonPersonID' => $gibbonPersonID]
    );

    if (empty($message) || !schoolCommunityGroupAccessAllowed($message, $gibbonPersonID, $roleCategory)) {
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
            'unreadLabel'=> (int) $group['unreadCount'] > 0 ? (string) (int) $group['unreadCount'] : '',
            'url'        => '/index.php?q=/modules/School Community/message_groupView.php&groupID='.urlencode($group['groupID']),
        ];
    }

    return new DataSet($rows);
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

        $rows[] = $message + [
            'direction'     => $isSelf ? schoolCommunityT('Sent / Received') : ($isSent ? schoolCommunityT('Sent') : schoolCommunityT('Received')),
            'otherPerson'   => trim($otherTitle.' '.$otherName),
            'priorityLabel' => schoolCommunityPriorityLabel($message['priority']),
            'isUnread'      => (!$isSent || $isSelf) && empty($message['readTimestamp']),
            'url'           => '/index.php?q=/modules/School Community/message_view.php&messageID='.urlencode($message['messageID']),
        ];
    }

    return new DataSet($rows);
}

function schoolCommunityBodyForDisplay($container, $body): string
{
    $body = (string) $body;
    $decoded = base64_decode($body, true);
    if ($decoded !== false && (strpos($decoded, '<') !== false || strpos($decoded, '>') !== false)) {
        $body = $decoded;
    }

    $validated = $container->get(Validator::class)->sanitize(['body' => $body], ['body' => 'HTML']);

    return $validated['body'];
}
