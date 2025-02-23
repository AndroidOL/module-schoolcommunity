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

use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\SchoolCommunity\Domain\CategoryGateway;

include '../../gibbon.php';
//Module includes
require_once __DIR__ . '/moduleFunctions.php';
$URL = '/index.php?q=/modules/' . 'School Community' . '/message_categorySetting.php&search='.$_GET['search'];
if (isActionAccessible($guid, $connection2, '/modules/' . 'School Community' . '/message_categorySetting.php') == false) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    //Proceed!
    $title = $_POST['title'] ?? '';
    $staff = $_POST['staff'] ?? '';
    $student = $_POST['student'] ?? '';
    $parent = $_POST['parent'] ?? '';
    $other = $_POST['other'] ?? '';

    if ($title == '' or $staff == '' or $student == '' or $parent == '' or $other == '') {
        // 检查必填项
        $URL = $URL.'&return=error3';
        header("Location: {$URL}");
    } else {
        $perm = calcPermission($staff, $student, $parent, $other);
        $user = $session->get('gibbonPersonID');

        /*
        $data1 = [
            'title'   => $title,
            'staff'   => $staff,
            'student' => $student,
            'parent'  => $parent,
            'other'   => $other,
            'perm'    => $perm,
            'user'    => $user
        ];

        //Move attached image  file, if there is one
        if (!empty($_FILES['file']['tmp_name'])) {
            $fileUploader = new Gibbon\FileUploader($pdo, $gibbon->session);
            $fileUploader->getFileExtensions('Graphics/Design');

            $file = (isset($_FILES['file']))? $_FILES['file'] : null;

            // Upload the file, return the /uploads relative path
            $logo = $fileUploader->uploadFromPost($file, 'infoGrid');

            if (empty($logo)) {
                $partialFail = true;
            }
        }
        */

        //Write to database
        try {
            $data = array('title' => $title, 'perm' => $perm, 'userID' => $session->get('gibbonPersonID'));
            $sql = 'INSERT INTO message_category SET categoryName=:title, accessControl=:perm, userID=:userID';
            $result = $connection2->prepare($sql);
            $result->execute($data);
        } catch (PDOException $ex) {
            $errorMessage = "Database error: " . $ex->getMessage() . "\n";
            $errorMessage .= "In file: " . $ex->getFile() . " on line " . $ex->getLine() . "\n";
            $errorMessage .= "Stack trace:\n" . $ex->getTraceAsString() . "\n";
        
            // 将错误信息记录到日志文件中
            error_log($errorMessage, 3, "./logfile.log");
            //Fail 2
            $URL = $URL.'&return=error2';
            header("Location: {$URL}");
            exit();
        }
        $AI = str_pad($connection2->lastInsertID(), 8, '0', STR_PAD_LEFT);

        if ($partialFail == true) {
            $URL .= '&return=warning1';
            header("Location: {$URL}");
        } else {
            $URL .= "&return=success0&editID=$AI";
            header("Location: {$URL}");
        }
    }
}