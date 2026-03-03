-- Run this SQL to manually fix users stuck online
-- This will set all users offline who haven't been active in the last 2 minutes

UPDATE users 
SET is_online = FALSE 
WHERE last_seen < DATE_SUB(NOW(), INTERVAL 2 MINUTE) 
AND is_online = TRUE;

-- Check current online status
SELECT id, username, is_online, last_seen, 
       TIMESTAMPDIFF(SECOND, last_seen, NOW()) as seconds_since_last_seen
FROM users 
ORDER BY last_seen DESC;
