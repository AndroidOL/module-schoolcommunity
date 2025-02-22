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

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Forms\Form;
use Gibbon\Module\SchoolCommunity\Tables\MessageList;
use Gibbon\Domain\School\FacilityGateway;

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('查看所有消息'));

if (!isActionAccessible($guid, $connection2, '/modules/' .'School Community' . '/message_reading.php')) {
    //Acess denied
    $page->addError(__('您没有权限访问该页面。'));
} else {
    //Get action with highest precendence
    $highestAction = getHighestGroupedAction($guid, $_GET['q'], $connection2);
    if ($highestAction == false) {
        $page->addError(__('The highest grouped action cannot be determined.'));
    } else {
        $roleCategory = getRoleCategory($session->get('gibbonRoleIDCurrent'), $connection2);
        $canManage = isActionAccessible($guid, $connection2, '/modules/' . 'School Community' . '/message_manage.php');

        $table = $container->get(MessageList::class)->create($roleCategory, $canManage);
        $table->setTitle(__('School Community'));

        echo $table->getOutput();
    }
}