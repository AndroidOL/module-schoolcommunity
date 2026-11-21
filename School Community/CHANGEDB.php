<?php
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = [];
$count = 0;

// v1.4.2
$sql[$count][0] = "1.4.2";
$sql[$count][1] = "UPDATE `gibbonAction` SET `URLList`='message_sending.php, message_sendingProcess.php, message_groupTargetProcess.php, message_groupAdding.php, message_groupProcess.php' WHERE `entryURL`='message_sending.php' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='School Community');end
UPDATE `gibbonAction` SET `URLList`='message_reading.php, message_view.php, message_replyProcess.php, message_threadProcess.php, message_threadConfirm.php, message_groups.php, message_groupView.php, message_groupRecipients.php, message_groupMembers.php, message_groupMessageProcess.php, message_groupReplyProcess.php' WHERE `entryURL`='message_reading.php' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='School Community');end";
$count++;

// v1.4.1
$sql[$count][0] = "1.4.1";
$sql[$count][1] = "SET @schoolCommunityGroupMessagesMissing = (SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='message_category' AND COLUMN_NAME='groupMessages');end
SET @schoolCommunityAddGroupMessages = IF(@schoolCommunityGroupMessagesMissing, 'ALTER TABLE message_category ADD COLUMN groupMessages ENUM(\\'Y\\', \\'N\\') NOT NULL DEFAULT \\'Y\\' AFTER accessControl', 'DO 0');end
PREPARE schoolCommunityAddGroupMessagesStmt FROM @schoolCommunityAddGroupMessages;end
EXECUTE schoolCommunityAddGroupMessagesStmt;end
DEALLOCATE PREPARE schoolCommunityAddGroupMessagesStmt;end
UPDATE `gibbonAction` SET `URLList`='message_sending.php, message_sendingProcess.php, message_groupTargetProcess.php, message_groupAdding.php, message_groupProcess.php' WHERE `entryURL`='message_sending.php' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='School Community');end
UPDATE `gibbonAction` SET `URLList`='message_reading.php, message_view.php, message_replyProcess.php, message_threadProcess.php, message_threadConfirm.php, message_groups.php, message_groupView.php, message_groupRecipients.php, message_groupMembers.php, message_groupMessageProcess.php, message_groupReplyProcess.php' WHERE `entryURL`='message_reading.php' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='School Community');end";
$count++;

// v1.4.0
$sql[$count][0] = "1.4.0";
$sql[$count][1] = "ALTER TABLE message_thread_state ADD COLUMN deletedAt TIMESTAMP NULL DEFAULT NULL AFTER archivedAt;end
SET @schoolCommunityGroupMessagesMissingBase = (SELECT COUNT(*)=0 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='message_category' AND COLUMN_NAME='groupMessages');end
SET @schoolCommunityAddGroupMessagesBase = IF(@schoolCommunityGroupMessagesMissingBase, 'ALTER TABLE message_category ADD COLUMN groupMessages ENUM(\\'Y\\', \\'N\\') NOT NULL DEFAULT \\'Y\\' AFTER accessControl', 'DO 0');end
PREPARE schoolCommunityAddGroupMessagesBaseStmt FROM @schoolCommunityAddGroupMessagesBase;end
EXECUTE schoolCommunityAddGroupMessagesBaseStmt;end
DEALLOCATE PREPARE schoolCommunityAddGroupMessagesBaseStmt;end
UPDATE `gibbonAction` SET `URLList`='message_sending.php, message_sendingProcess.php, message_groupTargetProcess.php, message_groupAdding.php, message_groupProcess.php' WHERE `entryURL`='message_sending.php' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='School Community');end
UPDATE `gibbonAction` SET `URLList`='message_reading.php, message_view.php, message_replyProcess.php, message_threadProcess.php, message_threadConfirm.php, message_groups.php, message_groupView.php, message_groupRecipients.php, message_groupMembers.php, message_groupMessageProcess.php, message_groupReplyProcess.php' WHERE `entryURL`='message_reading.php' AND gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='School Community');end";
$count++;

// v1.3.0
$sql[$count][0] = "1.3.0";
$sql[$count][1] = "CREATE TABLE `message_thread_state` (
    `threadStateID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `threadType` VARCHAR(10) NOT NULL,
    `threadID` INT(12) UNSIGNED ZEROFILL NOT NULL,
    `archivedAt` TIMESTAMP NULL DEFAULT NULL,
    `createTimestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updateTimestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`threadStateID`),
    UNIQUE KEY `personThread` (`gibbonPersonID`, `threadType`, `threadID`),
    KEY `thread` (`threadType`, `threadID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;end";
$count++;

// v1.2.0
$sql[$count][0] = "1.2.0";
$sql[$count][1] = "ALTER TABLE message_group ADD COLUMN sourceType VARCHAR(20) NOT NULL DEFAULT 'custom' AFTER ownerID;end
ALTER TABLE message_group ADD COLUMN sourceID INT(10) UNSIGNED ZEROFILL DEFAULT NULL AFTER sourceType;end
ALTER TABLE message_group ADD UNIQUE KEY linkedSource (sourceType, sourceID);end";
$count++;

// v1.1.0
$sql[$count][0] = "1.1.0";
$sql[$count][1] = "CREATE TABLE `message_group` (
    `groupID` INT(10) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `groupName` VARCHAR(100) NOT NULL,
    `categoryID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `ownerID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `createdAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`groupID`),
    KEY `categoryID` (`categoryID`),
    KEY `ownerID` (`ownerID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;end
CREATE TABLE `message_group_member` (
    `groupID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `joinedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`groupID`, `gibbonPersonID`),
    KEY `gibbonPersonID` (`gibbonPersonID`),
    FOREIGN KEY (`groupID`) REFERENCES `message_group`(`groupID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;end
CREATE TABLE `message_group_message` (
    `groupMessageID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `groupID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `senderID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `title` VARCHAR(100) NOT NULL,
    `body` MEDIUMTEXT NOT NULL,
    `sendTimestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`groupMessageID`),
    KEY `groupID` (`groupID`),
    FOREIGN KEY (`groupID`) REFERENCES `message_group`(`groupID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;end
CREATE TABLE `message_group_message_recipient` (
    `groupMessageID` INT(12) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `deliveredAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`groupMessageID`, `gibbonPersonID`),
    KEY `gibbonPersonID` (`gibbonPersonID`),
    FOREIGN KEY (`groupMessageID`) REFERENCES `message_group_message`(`groupMessageID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;end
CREATE TABLE `message_group_reply` (
    `groupReplyID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `groupMessageID` INT(12) UNSIGNED ZEROFILL NOT NULL,
    `senderID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `body` MEDIUMTEXT NOT NULL,
    `sendTimestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`groupReplyID`),
    KEY `groupMessageID` (`groupMessageID`),
    FOREIGN KEY (`groupMessageID`) REFERENCES `message_group_message`(`groupMessageID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;end
CREATE TABLE `message_group_read_receipt` (
    `groupReadReceiptID` INT(12) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
    `groupMessageID` INT(12) UNSIGNED ZEROFILL NOT NULL,
    `gibbonPersonID` INT(10) UNSIGNED ZEROFILL NOT NULL,
    `readTimestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`groupReadReceiptID`),
    UNIQUE KEY `messagePerson` (`groupMessageID`, `gibbonPersonID`),
    FOREIGN KEY (`groupMessageID`) REFERENCES `message_group_message`(`groupMessageID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;end";
$count++;

// v1.0.0
$sql[$count][0] = "1.0.0";
$sql[$count][1] = "ALTER TABLE message_totals MODIFY title VARCHAR(100) NOT NULL;end
ALTER TABLE message_totals MODIFY body MEDIUMTEXT NOT NULL;end
ALTER TABLE message_reply MODIFY body MEDIUMTEXT NOT NULL;end
DELETE newer FROM message_read_receipt newer
JOIN message_read_receipt older
  ON newer.messageID=older.messageID
 AND newer.gibbonPersonID=older.gibbonPersonID
 AND newer.receiptID>older.receiptID;end
ALTER TABLE message_read_receipt ADD UNIQUE KEY messagePerson (messageID, gibbonPersonID);end";
$count++;

// v0.0.00
// $sql[$count][0] = "0.0.00";
// $sql[$count][1] = "-- First version, nothing to update";
