# New Features Added - Complete Guide

## Features Summary

### 1. User Profile Management
- Edit username
- Update email address
- Change password
- All with validation and duplicate checking

### 2. Unread Message Indicators
- Green badge showing unread message count (WhatsApp-style)
- Messages automatically marked as read when chat is opened
- Real-time unread count updates

### 3. Admin Enhancements
- Profile pictures displayed in user list
- Warning notifications sent to users
- Better user management interface

### 4. Improved UI
- Minimalistic SVG icons for settings and notifications
- Better visual feedback
- Cleaner design

## Installation Steps

### 1. Run Database Updates

Visit this URL to automatically update your database:
```
http://localhost/chat/update_database.php
```

Or run these SQL commands manually:

```sql
-- Add warning type to notifications
ALTER TABLE notifications 
MODIFY COLUMN type ENUM('friend_request', 'friend_accepted', 'message', 'warning') NOT NULL;

-- Add index for better unread message performance
ALTER TABLE messages 
ADD INDEX idx_unread (receiver_id, is_read);
```

### 2. Clear Browser Cache

Press `Ctrl + Shift + Delete` and clear cached images and files.

### 3. Test the Features

## Feature Details

### User Settings Panel

Access via the settings icon (gear icon) in the chat interface.

**Update Username:**
1. Enter new username
2. Click "Update Username"
3. Page will reload with new username

**Update Email:**
1. Enter new email address
2. Click "Update Email"
3. Email updated (must be unique)

**Change Password:**
1. Enter current password
2. Enter new password (min 6 characters)
3. Confirm new password
4. Click "Update Password"

### Unread Message Badges

- Green circular badge appears on chat items with unread messages
- Shows count of unread messages (e.g., "3")
- Badge disappears when you open the chat
- Messages marked as read automatically

### Admin Warning System

When admin warns a user:
1. Warning count increases
2. Notification sent to user
3. User sees notification in their notification panel
4. Notification shows: "You have received a warning from the administrator"

### Profile Pictures in Admin Panel

- Admin dashboard now shows user profile pictures
- Default avatar shown if no picture uploaded
- Easier to identify users visually

## API Endpoints Added

### Settings API (api/settings.php)

**Update Username:**
```
POST action=update_username
Parameters: username
```

**Update Email:**
```
POST action=update_email
Parameters: email
```

**Update Password:**
```
POST action=update_password
Parameters: current_password, new_password
```

### Updated Endpoints

**Get Chats (api/users.php):**
- Now includes `unread_count` field for each chat

**Get Messages (api/messages.php):**
- Automatically marks messages as read when retrieved

**Warn User (api/admin.php):**
- Now creates a notification for the warned user

## UI Changes

### New Icons

**Settings Icon:**
- Minimalistic gear/cog SVG icon
- Clean, modern design

**Notifications Icon:**
- Bell SVG icon
- Shows notification badge when unread notifications exist

### Chat List

**Before:**
```
Username
Last message
```

**After:**
```
Username                    [3]  <- Green badge
Last message
```

## Testing Checklist

- [ ] Update username works
- [ ] Update email works (validates uniqueness)
- [ ] Change password works (validates current password)
- [ ] Unread badge appears on new messages
- [ ] Badge disappears when chat is opened
- [ ] Admin can see profile pictures
- [ ] Warning creates notification for user
- [ ] New icons display correctly
- [ ] Settings modal shows all options

## Troubleshooting

**Unread badges not showing:**
- Run update_database.php to add the index
- Clear browser cache
- Check that messages table has is_read column

**Settings not saving:**
- Check browser console for errors
- Verify you're logged in
- Check that api/settings.php is accessible

**Admin profile pictures not showing:**
- Verify profile_image column exists in users table
- Check that image paths are correct
- Ensure default-avatar.svg exists

**Warning notifications not appearing:**
- Run update_database.php to add 'warning' type
- Check notifications table structure
- Verify admin has proper permissions

## Database Schema Changes

### notifications table
```sql
type ENUM('friend_request', 'friend_accepted', 'message', 'warning')
-- Added 'warning' type
```

### messages table
```sql
INDEX idx_unread (receiver_id, is_read)
-- Added for better query performance
```

## Performance Notes

- Unread message queries are now indexed for faster performance
- Chat list loads with single query including unread counts
- Messages marked as read in batch when chat is opened

## Security Features

- Username uniqueness validated
- Email uniqueness validated
- Current password required for password changes
- Password minimum length enforced (6 characters)
- All inputs sanitized and validated

## Future Enhancements

Consider adding:
- Online/offline status indicators
- Typing indicators
- Message delivery status (sent, delivered, read)
- Group chats
- File sharing
- Voice messages
- Video calls
