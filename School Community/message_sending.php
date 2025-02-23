<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Module\SchoolCommunity\Domain\CategoryGateway;
use Gibbon\Module\SchoolCommunity\Domain\TechnicianGateway;
use Gibbon\Domain\School\FacilityGateway;

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('发送校内消息'));

if (!isActionAccessible($guid, $connection2, '/modules/' . 'School Community' . '/message_sending.php')) {
    // Acess denied
    $page->addError(__('您没有权限访问该页面。'));
} else {
    // Proceed!
    $page->return->setEditLink($session->get('absoluteURL') . '/index.php?q=/modules/' . 'School Community' . '/name_view.php');


    // Get Non-Technicians
    $technicianGateway = $container->get(TechnicianGateway::class);

    // Get Message Category
    $cGateway = $container->get(CategoryGateway::class);
    $categoryOptions = array_column($cGateway->querySimpleCategory()->fetchAll(), 'CategoryName');

    $users = array_reduce($technicianGateway->selectGibbonPerson()->fetchAll(), function ($group, $item) {
        $group[$item['gibbonPersonID']] = Format::name('', $item['preferredName'], $item['surname'], 'Staff', true) . ' (' . $item['username'] . ', ' . __($item['category']) . ')';
        return $group;
    }, []);

    $form = Form::create('createMessage',  $session->get('absoluteURL') . '/modules/' . 'School Community' . '/name_view.php', 'post');
    $form->addHiddenValue('address', $session->get('address'));

    $row = $form->addRow();
        $row->addLabel('messageTitle', __('消息标题'));
        $row->addTextField('messageTitle')
            ->required()
            ->maxLength(50);

    $row = $form->addRow();
        $row->addLabel('messageCategory', __('消息分类'));
        $row->addSelect('messageCategory')
            ->fromArray($categoryOptions)
            ->placeholder()
            ->required();
    
    $row = $form->addRow();
    $column = $row->addColumn();
        $column->addLabel('messageDescription', __('消息内容'));
        $column->addEditor('messageDescription', $guid)
                ->setRows(10)
                ->showMedia()
                ->required();

    $row = $form->addRow();
        $row->addLabel('messageRecipient', __('Person'));
        $row->addSelectPerson('person')
            ->fromArray($users)
            ->placeholder()
            ->required();

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit();

    echo $form->getOutput();
}