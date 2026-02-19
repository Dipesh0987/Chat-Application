# Chat Application - New Features Setup

## Database Updates

Run the following SQL commands to update your database with the new features:

```sql
-- Add profile_image column to users table
ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL;

-- Update friends table to support rejected status
ALTER TABLE friends MODIFY COLUMN status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending';

-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('friend_request', 'friend_accepted', 'message') NOT NULL,
    from_user_id INT NOT NULL,
    reference_id INT NULL,
    message TEXT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_notifications_read ON notifications(is_read);
```

## Directory Setup

Create the following directories for file uploads:

```bash
mkdir -p uploads/profiles
chmod 777 uploads/profiles
```

## Default Avatar

Create a default avatar image at: `assets/images/default-avatar.png`

You can use any placeholder image or download one from the internet.

## New Features Added

### 1. Friend Request System
- Users now send friend requests instead of directly adding friends
- Requests must be accepted before users become friends
- New "Requests" tab shows pending friend requests
- Accept/Reject buttons for each request

### 2. Notification System
- Real-time notification badge in header
- Notifications for:
  - Friend requests received
  - Friend requests accepted
  - New messages
- Click notification bell to view all notifications
- Mark all as read functionality

### 3. User Settings
- Click settings icon (⚙️) to access:
  - Upload profile picture
  - Delete account (with password confirmation)
  - Logout

### 4. Profile Pictures
- Users can upload profile pictures (JPG, PNG, GIF)
- Max file size: 5MB
- Images stored in `uploads/profiles/`
- Profile picture displayed in sidebar header

## API Endpoints Added

### api/notifications.php
- `GET ?action=get` - Get all notifications
- `GET ?action=get_unread_count` - Get unread notification count
- `POST action=mark_read` - Mark notification as read
- `POST action=mark_all_read` - Mark all notifications as read
- `POST action=delete` - Delete a notification

### api/settings.php
- `GET ?action=get_profile` - Get user profile info
- `POST action=upload_profile_image` - Upload profile picture
- `POST action=delete_account` - Delete user account
- `POST action=logout` - Logout user

### api/users.php (Updated)
- `POST action=send_friend_request` - Send friend request
- `POST action=accept_friend_request` - Accept friend request
- `POST action=reject_friend_request` - Reject friend request
- `GET ?action=get_friend_requests` - Get pending friend requests

## Testing

1. Run the SQL updates on your database
2. Create the uploads directory
3. Add a default avatar image
4. Login and test:
   - Send a friend request
   - Accept/reject requests
   - Upload a profile picture
   - Check notifications
   - Delete account feature

## Notes

- Notifications are polled every 10 seconds
- Profile images are automatically deleted when account is deleted
- Old profile images are replaced when uploading new ones
- Friend requests create notifications automatically
