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
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Comms\NotificationSender;
use Gibbon\Domain\System\LogGateway;
use Gibbon\Domain\System\NotificationGateway;
use Gibbon\Domain\System\SettingGateway;

require_once '../../gibbon.php';

require_once './moduleFunctions.php';

$absoluteURL = $session->get('absoluteURL');
$moduleName = $session->get('module');

$URL = $absoluteURL . '/index.php?q=/modules/' . $moduleName;

if (!isActionAccessible($guid, $connection2, '/modules/' . $moduleName . '/message_sending.php')) {
    $URL .= '/message_reading.php&return=error0';
    header("Location: {$URL}");
    exit();
} else {
    //Proceed!    
    $URL .= '/message_sending.php';

    $gibbonPersonID = $session->get('gibbonPersonID');

    $data = [
        //Default data
        'gibbonPersonID' => $gibbonPersonID,
        'gibbonSchoolYearID' => $session->get('gibbonSchoolYearID'),
        'createDate' => date('Y-m-d'),
        //Data to get from Post or getSettingByScope
        'messageTitle' => '',
        'messageCategory' => '',
        'messageDescription' => '',
        'messageRecipient' => null,
        'messagePriority' => 'm',
    ];

    foreach ($data as $key => $value) {
        if (empty($value) && isset($_POST[$key])) {
            $data[$key] = $_POST[$key];
        }
    }

    $data['messageDescription'] = base64_encode($_POST['messageDescription'] ?? '');

    $sendData = [
        'title' => $data['messageTitle'],  // 标题
        'body' => $data['messageDescription'],  // 内容
        'categoryID' => $data['messageCategory'],  // 分类
        'priority' => null,  // 需要根据 messagePriority 来设置
        'senderID' => $data['gibbonPersonID'],  // 发送者 ID
        'receiverID' => $data['messageRecipient'],  // 接收者 ID
    ];
    switch ($data['messagePriority']) {
        case 'h':
            $sendData['priority'] = true;
            break;
        case 'l':
            $sendData['priority'] = false;
            break;
        default:
            $sendData['priority'] = null;  // 'm' 或其他任何值都设为 null
            break;
    }
    echo '<pre>';
    print_r($sendData);
    echo '</pre>';

    /*
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
        $sql = 'INSERT INTO message_totals (title, body, categoryID, priority, senderID, receiverID) 
        VALUES (:title, :body, :categoryID, :priority, :senderID, :receiverID)';
        $result = $connection2->prepare($sql);
        $result->execute($sendData);
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