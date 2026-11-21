<?php
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = [];
$count = 0;

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
