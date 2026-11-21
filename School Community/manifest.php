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
$description = 'Categorised direct and group messages between staff, students and families.';            // Short text description
$entryURL    = "message_reading.php";   // The landing page for the unit, used in the main menu
$type        = "Additional";  // Do not change.
$category    = 'Other';            // The main menu area to place the module in
$version     = '1.2.0';            // Version number
$author      = 'Tianhao Wu';            // Your name
$url         = 'https://gibbonedu.org';            // Your URL

// Module tables & gibbonSettings entries
$moduleTables[] = 'CREATE TABLE `message_totals` (
    `messageID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(100) NOT NULL,
    `body` MEDIUMTEXT NOT NULL,
    `categoryID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `priority` BOOLEAN DEFAULT NULL,
    `senderID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `receiverID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `sendTimestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updateTimestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`messageID`),
    KEY `categoryID` (`categoryID`),
    KEY `senderID` (`senderID`),
    KEY `receiverID` (`receiverID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

$moduleTables[] = 'CREATE TABLE `message_category` (
    `categoryID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `categoryName` VARCHAR(100) NOT NULL,
    `userID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `accessControl` TINYINT UNSIGNED NOT NULL,
    `createTime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updateTime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`categoryID`),
    UNIQUE KEY `categoryName` (`categoryName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

$moduleTables[] = 'CREATE TABLE `message_read_receipt` (
    `receiptID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `messageID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `readTimestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`receiptID`),
    UNIQUE KEY `messagePerson` (`messageID`, `gibbonPersonID`),
    FOREIGN KEY (`messageID`) REFERENCES `message_totals`(`messageID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

$moduleTables[] = 'CREATE TABLE `message_reply` (
    `replyID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `messageID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `senderID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `body` MEDIUMTEXT NOT NULL,
    `sendTimestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`replyID`),
    KEY `messageID` (`messageID`),
    FOREIGN KEY (`messageID`) REFERENCES `message_totals`(`messageID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

$moduleTables[] = 'CREATE TABLE `message_group` (
    `groupID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `groupName` VARCHAR(100) NOT NULL,
    `categoryID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `ownerID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `sourceType` VARCHAR(20) NOT NULL DEFAULT \'custom\',
    `sourceID` INT(10) UNSIGNED ZEROFILL DEFAULT NULL,
    `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`groupID`),
    KEY `categoryID` (`categoryID`),
    KEY `ownerID` (`ownerID`),
    UNIQUE KEY `linkedSource` (`sourceType`, `sourceID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

$moduleTables[] = 'CREATE TABLE `message_group_member` (
    `groupID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `joinedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`groupID`, `gibbonPersonID`),
    KEY `gibbonPersonID` (`gibbonPersonID`),
    FOREIGN KEY (`groupID`) REFERENCES `message_group`(`groupID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

$moduleTables[] = 'CREATE TABLE `message_group_message` (
    `groupMessageID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `groupID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `senderID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `title` VARCHAR(100) NOT NULL,
    `body` MEDIUMTEXT NOT NULL,
    `sendTimestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`groupMessageID`),
    KEY `groupID` (`groupID`),
    FOREIGN KEY (`groupID`) REFERENCES `message_group`(`groupID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

$moduleTables[] = 'CREATE TABLE `message_group_message_recipient` (
    `groupMessageID` INT(12) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `deliveredAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`groupMessageID`, `gibbonPersonID`),
    KEY `gibbonPersonID` (`gibbonPersonID`),
    FOREIGN KEY (`groupMessageID`) REFERENCES `message_group_message`(`groupMessageID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

$moduleTables[] = 'CREATE TABLE `message_group_reply` (
    `groupReplyID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `groupMessageID` INT(12) UNSIGNED ZEROFILL NOT NULL,
    `senderID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `body` MEDIUMTEXT NOT NULL,
    `sendTimestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`groupReplyID`),
    KEY `groupMessageID` (`groupMessageID`),
    FOREIGN KEY (`groupMessageID`) REFERENCES `message_group_message`(`groupMessageID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

$moduleTables[] = 'CREATE TABLE `message_group_read_receipt` (
    `groupReadReceiptID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `groupMessageID` INT(12) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `readTimestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`groupReadReceiptID`),
    UNIQUE KEY `messagePerson` (`groupMessageID`, `gibbonPersonID`),
    FOREIGN KEY (`groupMessageID`) REFERENCES `message_group_message`(`groupMessageID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

// Add gibbonSettings entries
// $gibbonSetting[] = "";

// Action rows
$actionRows[] = [
    'name'                      => 'Manage Message Categories', // The name of the action (appears to user in the right hand side module menu)
    'precedence'                => '0',// If it is a grouped action, the precedence controls which is highest action in group
    'category'                  => 'Settings', // Optional: subgroups for the right hand side module menu
    'description'               => 'Create and manage message categories and audience permissions.', // Text description
    'URLList'                   => 'message_categorySetting.php, message_categoryAdding.php, message_categoryEditing.php, message_categoryDelete.php, message_categoryProcess.php', // List of pages included in this action
    'entryURL'                  => 'message_categorySetting.php', // The landing action for the page.
    'entrySidebar'              => 'Y', // Whether or not there's a sidebar on entry to the action
    'menuShow'                  => 'Y', // Whether or not this action shows up in menus or if it's hidden
    'defaultPermissionAdmin'    => 'Y', // Default permission for built in role Admin
    'defaultPermissionTeacher'  => 'N', // Default permission for built in role Teacher
    'defaultPermissionStudent'  => 'N', // Default permission for built in role Student
    'defaultPermissionParent'   => 'N', // Default permission for built in role Parent
    'defaultPermissionSupport'  => 'N', // Default permission for built in role Support
    'categoryPermissionStaff'   => 'Y', // Should this action be available to user roles in the Staff category?
    'categoryPermissionStudent' => 'N', // Should this action be available to user roles in the Student category?
    'categoryPermissionParent'  => 'N', // Should this action be available to user roles in the Parent category?
    'categoryPermissionOther'   => 'N', // Should this action be available to user roles in the Other category?
];

$actionRows[] = [
    'name'                      => 'Send Message', // The name of the action (appears to user in the right hand side module menu)
    'precedence'                => '0',// If it is a grouped action, the precedence controls which is highest action in group
    'category'                  => 'Messages', // Optional: subgroups for the right hand side module menu
    'description'               => 'Send a categorised message to another school user.', // Text description
    'URLList'                   => 'message_sending.php, message_sendingProcess.php, message_groupAdding.php, message_groupProcess.php', // List of pages included in this action
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
    'name'                      => 'View Messages', // The name of the action (appears to user in the right hand side module menu)
    'precedence'                => '0',// If it is a grouped action, the precedence controls which is highest action in group
    'category'                  => 'Messages', // Optional: subgroups for the right hand side module menu
    'description'               => 'View sent and received messages, receipts and replies.', // Text description
    'URLList'                   => 'message_reading.php, message_view.php, message_replyProcess.php, message_groups.php, message_groupView.php, message_groupMembers.php, message_groupMessageProcess.php, message_groupReplyProcess.php', // List of pages included in this action
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
