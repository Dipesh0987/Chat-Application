-- Update notifications table to add 'message_request' type
-- Run this SQL in your phpMyAdmin or MySQL client to update the existing database

ALTER TABLE notifications 
MODIFY COLUMN type ENUM('friend_request', 'friend_accepted', 'message_request', 'warning') NOT NULL;

-- Delete old 'message' type notifications (they are no longer needed)
DELETE FROM notifications WHERE type = 'message';
