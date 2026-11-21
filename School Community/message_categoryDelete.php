<?php
/*
Gibbon: the flexible, open school platform
Copyright © 2010, Gibbon Foundation
*/

use Gibbon\Forms\Form;

require_once __DIR__.'/moduleFunctions.php';

$page->breadcrumbs
    ->add(schoolCommunityT('Message Categories'), 'message_categorySetting.php')
    ->add(schoolCommunityT('Delete Category'));

if (!isActionAccessible($guid, $connection2, '/modules/School Community/message_categorySetting.php')) {
    $page->addError(schoolCommunityT('You do not have access to this page.'));
    return;
}

$categoryID = is_scalar($_GET['CategoryID'] ?? null) ? trim((string) $_GET['CategoryID']) : '';
$category = $categoryID !== '' ? $pdo->selectOne(
    "SELECT categoryID, categoryName FROM message_category WHERE categoryID=:categoryID",
    ['categoryID' => $categoryID]
) : null;

if (empty($category)) {
    $page->addError(schoolCommunityT('The specified record does not exist.'));
    return;
}

$form = Form::create('schoolCommunityCategoryDelete', $session->get('absoluteURL').'/modules/School Community/message_categoryProcess.php', 'post');
$form->addHiddenValue('action', 'delete');
$form->addHiddenValue('categoryID', $categoryID);
$form->setTitle(schoolCommunityT('Delete Category'));
$form->addRow()->addAlert(sprintf(schoolCommunityT('Delete category "%s"? This cannot be undone.'), htmlPrep($category['categoryName'])), 'warning');
$row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(schoolCommunityT('Delete Category'));

echo $form->getOutput();
