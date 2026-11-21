<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Data\Validator;
use Gibbon\Session\TokenHandler;

require_once '../../gibbon.php';
require_once __DIR__.'/moduleFunctions.php';

$categoryID = is_scalar($_POST['categoryID'] ?? null) ? trim((string) $_POST['categoryID']) : '';
$composeURL = $session->get('absoluteURL').'/index.php?q=/modules/School Community/message_groupCompose.php'
    .'&categoryID='.urlencode($categoryID).'&targetType=free';
$redirect = function (string $return = '', string $recipientIDs = '') use ($composeURL) {
    $url = $composeURL;
    if ($recipientIDs !== '') {
        $url .= '&recipientIDs='.urlencode($recipientIDs);
    }
    if ($return !== '') {
        $url .= '&return='.$return;
    }
    header('Location: '.$url);
    exit;
};

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_sending.php')) {
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

$category = $categoryID !== '' ? $pdo->selectOne(
    "SELECT categoryID, accessControl, groupMessages
     FROM message_category
     WHERE categoryID=:categoryID",
    ['categoryID' => $categoryID]
) : null;
if (empty($category)
    || !schoolCommunityCategoryAllows($category['accessControl'], $roleCategory)
    || !schoolCommunityGroupMessagesAllowed($category)) {
    $redirect('error0');
}

$people = schoolCommunityPersonOptions($pdo, $currentPersonID, (int) $category['accessControl'], true);
$selected = $_POST['recipientIDs'] ?? [];
$selected = is_array($selected)
    ? array_values(array_unique(array_filter(array_map('strval', $selected), function ($recipientID) use ($people) {
        return $recipientID !== '' && isset($people[$recipientID]);
    })))
    : [];
if (count($selected) < 1) {
    $redirect('error1');
}
if (count($selected) > 5) {
    $redirect('error4');
}

$redirect('', implode(',', $selected));