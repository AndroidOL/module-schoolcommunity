<?php
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = [];
$count = 0;

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
