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

function schoolCommunityPersonOptions($pdo, $excludePersonID): array
{
    $result = $pdo->select(
        "SELECT gibbonPersonID, title, preferredName, surname, username, roleCategory
         FROM (
             SELECT gibbonPerson.gibbonPersonID, gibbonPerson.title, gibbonPerson.preferredName,
                    gibbonPerson.surname, gibbonPerson.username, gibbonRole.category AS roleCategory
             FROM gibbonPerson
             JOIN gibbonRole ON gibbonRole.gibbonRoleID=gibbonPerson.gibbonRoleIDPrimary
             WHERE gibbonPerson.status='Full'
               AND gibbonPerson.gibbonPersonID<>:gibbonPersonID
         ) AS people
         ORDER BY surname, preferredName",
        ['gibbonPersonID' => $excludePersonID]
    )->fetchAll(PDO::FETCH_ASSOC);

    $options = [];
    foreach ($result as $person) {
        $name = Format::name($person['title'], $person['preferredName'], $person['surname'], $person['roleCategory'], true);
        $options[(string) $person['gibbonPersonID']] = $name.' ('.schoolCommunityT($person['roleCategory']).')';
    }

    return $options;
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
        $isSent = (string) $message['senderID'] === (string) $gibbonPersonID;
        $otherTitle = $isSent ? $message['receiverTitle'] : $message['senderTitle'];
        $otherName = $isSent
            ? trim($message['receiverPreferredName'].' '.$message['receiverSurname'])
            : trim($message['senderPreferredName'].' '.$message['senderSurname']);

        $rows[] = $message + [
            'direction'     => $isSent ? schoolCommunityT('Sent') : schoolCommunityT('Received'),
            'otherPerson'   => trim($otherTitle.' '.$otherName),
            'priorityLabel' => schoolCommunityPriorityLabel($message['priority']),
            'isUnread'      => !$isSent && empty($message['readTimestamp']),
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
