<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http:// www.gnu.org/licenses/>.
*/

// This file describes the module, including database tables

// Basic variables
$name        = 'School Community';            // The name of the module as it appears to users. Needs to be unique to installation. Also the name of the folder that holds the unit.
$description = 'A plugin designed to enhance communication between schools and parents by providing an easy-to-use platform for sharing student progress, updates, and announcements.';            // Short text description
$entryURL    = "message_reading.php";   // The landing page for the unit, used in the main menu
$type        = "Additional";  // Do not change.
$category    = 'Other';            // The main menu area to place the module in
$version     = '0.0.00';            // Version number
$author      = 'Tianhao Wu';            // Your name
$url         = 'https://gibbonedu.org';            // Your URL

// Module tables & gibbonSettings entries
$moduleTables[] = 'CREATE TABLE `message_totals` (
    `messageID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,    -- 消息ID
    `title` VARCHAR(50) NOT NULL,    -- 消息标题，最多50个字符
    `body` TEXT NOT NULL,    -- 消息本体，可以容纳较长的文本
    `categoryID` INT(10) UNSIGNED ZEROFILL NOT NULL,    -- 消息分类，关联分类表
    `priority` BOOLEAN DEFAULT NULL,    -- 消息优先级，空表示标准优先级，FALSE表示低优先级，TRUE表示紧急
    `senderID` INT(10) UNSIGNED ZEROFILL NOT NULL,    -- 发送人ID，关联系统人员表
    `receiverID` INT(10) UNSIGNED ZEROFILL NOT NULL,    -- 接收人ID，关联系统人员表
    `sendTimestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,    -- 消息发送时间
    `updateTimestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,    -- 消息最后更新时间
    PRIMARY KEY (`messageID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;';

$moduleTables[] = 'CREATE TABLE `message_category` (
    `categoryID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,    -- 分类ID
    `categoryName` VARCHAR(100) NOT NULL,    -- 分类名称
    `userID` INT(10) UNSIGNED ZEROFILL NOT NULL,    -- 添加人ID
    `accessControl` INT(2) UNSIGNED ZEROFILL NOT NULL,    -- 权限控制
    `createTime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,    -- 创建时间
    `updateTime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,    -- 修改时间
    PRIMARY KEY (`categoryID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;';

$moduleTables[] = 'CREATE TABLE `message_read_receipt` (
    `receiptID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,    -- 回执ID
    `messageID` INT(10) UNSIGNED ZEROFILL NOT NULL,    -- 关联消息ID
    `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,    -- 用户ID，表示谁读取了消息
    `readTimestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,    -- 用户读取消息的时间
    PRIMARY KEY (`receiptID`),
    FOREIGN KEY (`messageID`) REFERENCES `message_totals`(`messageID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;';

$moduleTables[] = 'CREATE TABLE `message_reply` (
    `replyID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,    -- 回复ID
    `messageID` INT(10) UNSIGNED ZEROFILL NOT NULL,    -- 关联的消息ID
    `senderID` INT(10) UNSIGNED ZEROFILL NOT NULL,    -- 回复发送者ID
    `body` TEXT NOT NULL,    -- 回复的内容
    `sendTimestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,    -- 回复时间
    PRIMARY KEY (`replyID`),
    FOREIGN KEY (`messageID`) REFERENCES `message_totals`(`messageID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;';

// Add gibbonSettings entries
// $gibbonSetting[] = "";

// Action rows 
$actionRows[] = [
    'name'                      => '设置消息分类', // The name of the action (appears to user in the right hand side module menu)
    'precedence'                => '0',// If it is a grouped action, the precedence controls which is highest action in group
    'category'                  => '设置', // Optional: subgroups for the right hand side module menu
    'description'               => '允许用户修改设置分类', // Text description
    'URLList'                   => 'message_categorySetting.php, message_categoryAdding.php, message_categoryAddingProccess.php,  message_categoryEditting.php, message_categoryDeleting.php', // List of pages included in this action
    'entryURL'                  => 'message_categorySetting.php', // The landing action for the page.
    'entrySidebar'              => 'Y', // Whether or not there's a sidebar on entry to the action
    'menuShow'                  => 'Y', // Whether or not this action shows up in menus or if it's hidden
    'defaultPermissionAdmin'    => 'Y', // Default permission for built in role Admin
    'defaultPermissionTeacher'  => 'N', // Default permission for built in role Teacher
    'defaultPermissionStudent'  => 'N', // Default permission for built in role Student
    'defaultPermissionParent'   => 'N', // Default permission for built in role Parent
    'defaultPermissionSupport'  => 'Y', // Default permission for built in role Support
    'categoryPermissionStaff'   => 'Y', // Should this action be available to user roles in the Staff category?
    'categoryPermissionStudent' => 'N', // Should this action be available to user roles in the Student category?
    'categoryPermissionParent'  => 'N', // Should this action be available to user roles in the Parent category?
    'categoryPermissionOther'   => 'N', // Should this action be available to user roles in the Other category?
];

$actionRows[] = [
    'name'                      => '发送校内消息', // The name of the action (appears to user in the right hand side module menu)
    'precedence'                => '0',// If it is a grouped action, the precedence controls which is highest action in group
    'category'                  => '操作', // Optional: subgroups for the right hand side module menu
    'description'               => '允许用户发送消息', // Text description
    'URLList'                   => 'message_sending.php', // List of pages included in this action
    'entryURL'                  => 'message_sending.php', // The landing action for the page.
    'entrySidebar'              => 'Y', // Whether or not there's a sidebar on entry to the action
    'menuShow'                  => 'Y', // Whether or not this action shows up in menus or if it's hidden
    'defaultPermissionAdmin'    => 'Y', // Default permission for built in role Admin
    'defaultPermissionTeacher'  => 'Y', // Default permission for built in role Teacher
    'defaultPermissionStudent'  => 'Y', // Default permission for built in role Student
    'defaultPermissionParent'   => 'Y', // Default permission for built in role Parent
    'defaultPermissionSupport'  => 'Y', // Default permission for built in role Support
    'categoryPermissionStaff'   => 'Y', // Should this action be available to user roles in the Staff category?
    'categoryPermissionStudent' => 'Y', // Should this action be available to user roles in the Student category?
    'categoryPermissionParent'  => 'Y', // Should this action be available to user roles in the Parent category?
    'categoryPermissionOther'   => 'Y', // Should this action be available to user roles in the Other category?
];

$actionRows[] = [
    'name'                      => '查看所有消息', // The name of the action (appears to user in the right hand side module menu)
    'precedence'                => '0',// If it is a grouped action, the precedence controls which is highest action in group
    'category'                  => '查询', // Optional: subgroups for the right hand side module menu
    'description'               => '允许用户查看消息', // Text description
    'URLList'                   => 'message_reading.php', // List of pages included in this action
    'entryURL'                  => 'message_reading.php', // The landing action for the page.
    'entrySidebar'              => 'Y', // Whether or not there's a sidebar on entry to the action
    'menuShow'                  => 'Y', // Whether or not this action shows up in menus or if it's hidden
    'defaultPermissionAdmin'    => 'Y', // Default permission for built in role Admin
    'defaultPermissionTeacher'  => 'Y', // Default permission for built in role Teacher
    'defaultPermissionStudent'  => 'N', // Default permission for built in role Student
    'defaultPermissionParent'   => 'Y', // Default permission for built in role Parent
    'defaultPermissionSupport'  => 'Y', // Default permission for built in role Support
    'categoryPermissionStaff'   => 'Y', // Should this action be available to user roles in the Staff category?
    'categoryPermissionStudent' => 'Y', // Should this action be available to user roles in the Student category?
    'categoryPermissionParent'  => 'Y', // Should this action be available to user roles in the Parent category?
    'categoryPermissionOther'   => 'Y', // Should this action be available to user roles in the Other category?
];

// Hooks
// $hooks[] = ''; // Serialised array to create hook and set options. See Hooks documentation online.
