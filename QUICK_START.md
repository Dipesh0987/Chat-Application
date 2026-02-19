# Quick Start - New Features

## 🚀 Quick Setup (3 Steps)

### Step 1: Update Database
Visit: `http://localhost/chat/update_database.php`

This will automatically:
- Add 'warning' notification type
- Add index for unread messages
- Show you what was updated

### Step 2: Clear Browser Cache
Press `Ctrl + Shift + Delete` and clear cache

### Step 3: Test!
Login and try the new features

---

## ✨ What's New

### For Users:

**1. Edit Your Profile** (Settings Icon)
- Change username
- Update email
- Change password

**2. Unread Message Badges** (Green circles like WhatsApp)
- See how many unread messages from each contact
- Badge shows number (e.g., "3")
- Disappears when you open the chat

**3. Better Notifications**
- Get notified when admin warns you
- Cleaner notification icons

### For Admins:

**1. See User Profile Pictures**
- Profile pics now show in user list
- Easier to identify users

**2. Warning Notifications**
- When you warn a user, they get notified
- Shows in their notification panel

**3. Better UI**
- Cleaner icons
- Better layout

---

## 🎯 Quick Test

1. **Test Unread Badges:**
   - Login as User A
   - Send message to User B
   - Login as User B
   - See green badge with "1" on User A's chat

2. **Test Profile Edit:**
   - Click settings icon (gear)
   - Change username
   - Update password
   - Update email

3. **Test Admin Warning:**
   - Login as admin (admin/admin123)
   - Warn a user
   - Login as that user
   - Check notifications

---

## 📁 Files Changed

**New Files:**
- `update_database.php` - Database updater
- `NEW_FEATURES_GUIDE.md` - Detailed guide
- `QUICK_START.md` - This file

**Updated Files:**
- `config/setup.sql` - Added warning type, index
- `user/chat.php` - New SVG icons
- `assets/js/chat.js` - Profile editing, unread badges
- `assets/css/style.css` - Unread badge styles
- `api/settings.php` - Profile update endpoints
- `api/users.php` - Unread count in chats
- `api/messages.php` - Mark as read
- `api/admin.php` - Warning notifications
- `assets/js/admin.js` - Profile pictures

---

## 🐛 Common Issues

**Issue: Unread badges not showing**
- Solution: Run update_database.php

**Issue: Can't update profile**
- Solution: Clear browser cache

**Issue: Icons look weird**
- Solution: Hard refresh (Ctrl + F5)

---

## 📞 Need Help?

Check these files:
1. `NEW_FEATURES_GUIDE.md` - Detailed documentation
2. `UPLOAD_TROUBLESHOOTING.md` - Upload issues
3. `SETUP_INSTRUCTIONS.md` - Initial setup

---

## 🎨 UI Preview

**Settings Icon:** Gear/cog SVG (minimalistic)
**Notification Icon:** Bell SVG (minimalistic)
**Unread Badge:** Green circle with number (WhatsApp-style)

---

That's it! You're ready to use all the new features. 🎉
