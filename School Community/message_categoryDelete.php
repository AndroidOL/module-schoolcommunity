<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Prefab\DeleteForm;

require_once __DIR__.'/moduleFunctions.php';

$page->breadcrumbs
    ->add(schoolCommunityT('Message Categories'), 'message_categorySetting.php')
    ->add(schoolCommunityT('Delete Category'));

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_categorySetting.php')) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$categoryID = is_scalar($_GET['categoryID'] ?? null) ? trim((string) $_GET['categoryID']) : '';
$category = $categoryID !== '' ? $pdo->selectOne(
    "SELECT categoryID, categoryName FROM message_category WHERE categoryID=:categoryID",
    ['categoryID' => $categoryID]
) : null;

if (empty($category)) {
    $page->addError(schoolCommunityT('The specified record does not exist.'));
    return;
}

$form = DeleteForm::createForm($session->get('absoluteURL').'/modules/School Community/message_categoryProcess.php');
$form->addHiddenValue('action', 'delete');
$form->addHiddenValue('categoryID', $categoryID);

$page->write($form->getOutput());