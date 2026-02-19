<?php
// Content filtering and moderation

class ContentFilter {
    private $vulgarWords = [
        'fuck', 'shit', 'bitch', 'asshole', 'bastard', 'damn', 'crap',
        'dick', 'pussy', 'cock', 'slut', 'whore', 'fag', 'nigger',
        'cunt', 'piss', 'ass', 'hell', 'sex', 'porn', 'xxx'
        // Add more words as needed
    ];
    
    private $db;
    private $user_id;
    
    public function __construct($database, $user_id) {
        $this->db = $database;
        $this->user_id = $user_id;
    }
    
    /**
     * Check if message contains vulgar content
     * Returns: ['is_vulgar' => bool, 'matched_words' => array]
     */
    public function checkMessage($message) {
        $message_lower = strtolower($message);
        $matched_words = [];
        
        foreach ($this->vulgarWords as $word) {
            // Check for whole word matches (with word boundaries)
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $message_lower)) {
                $matched_words[] = $word;
            }
        }
        
        return [
            'is_vulgar' => count($matched_words) > 0,
            'matched_words' => $matched_words
        ];
    }
    
    /**
     * Issue warning to user
     * Returns: ['success' => bool, 'warnings' => int, 'banned' => bool, 'error' => string]
     */
    public function issueWarning($reason = 'Inappropriate content') {
        try {
            // Get current warnings
            $query = "SELECT warnings FROM users WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $this->user_id);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                error_log("ContentFilter: User not found - ID: {$this->user_id}");
                return ['success' => false, 'warnings' => 0, 'banned' => false, 'error' => 'User not found'];
            }
            
            $new_warnings = $user['warnings'] + 1;
            
            // Update warnings
            $update = "UPDATE users SET warnings = :warnings WHERE id = :user_id";
            $stmt = $this->db->prepare($update);
            $stmt->bindParam(':warnings', $new_warnings);
            $stmt->bindParam(':user_id', $this->user_id);
            $stmt->execute();
            
            error_log("ContentFilter: Updated warnings for user {$this->user_id} to $new_warnings");
            
            // Create notification
            try {
                $warning_msg = "Warning: $reason. You have $new_warnings warning(s). 3 warnings will result in a 7-day ban.";
                $notif = "INSERT INTO notifications (user_id, type, from_user_id, message) 
                          VALUES (:user_id, 'warning', 0, :message)";
                $stmt_notif = $this->db->prepare($notif);
                $stmt_notif->bindParam(':user_id', $this->user_id);
                $stmt_notif->bindParam(':message', $warning_msg);
                $stmt_notif->execute();
                
                error_log("ContentFilter: Created warning notification for user {$this->user_id}");
            } catch (Exception $e) {
                error_log("ContentFilter: Failed to create notification - " . $e->getMessage());
                // Continue even if notification fails
            }
            
            // Check if user should be banned (3 warnings)
            $banned = false;
            if ($new_warnings >= 3) {
                $ban_until = date('Y-m-d H:i:s', strtotime('+7 days'));
                $ban = "UPDATE users SET is_banned = TRUE, ban_until = :ban_until WHERE id = :user_id";
                $stmt_ban = $this->db->prepare($ban);
                $stmt_ban->bindParam(':ban_until', $ban_until);
                $stmt_ban->bindParam(':user_id', $this->user_id);
                $stmt_ban->execute();
                
                error_log("ContentFilter: BANNED user {$this->user_id} until $ban_until");
                
                // Send ban notification
                try {
                    $ban_notif = "INSERT INTO notifications (user_id, type, from_user_id, message) 
                                  VALUES (:user_id, 'warning', 0, 'Your account has been banned for 7 days due to multiple violations.')";
                    $stmt_ban_notif = $this->db->prepare($ban_notif);
                    $stmt_ban_notif->bindParam(':user_id', $this->user_id);
                    $stmt_ban_notif->execute();
                    
                    error_log("ContentFilter: Created ban notification for user {$this->user_id}");
                } catch (Exception $e) {
                    error_log("ContentFilter: Failed to create ban notification - " . $e->getMessage());
                }
                
                $banned = true;
            }
            
            return [
                'success' => true,
                'warnings' => $new_warnings,
                'banned' => $banned
            ];
        } catch (Exception $e) {
            error_log("ContentFilter: Error in issueWarning - " . $e->getMessage());
            return [
                'success' => false,
                'warnings' => 0,
                'banned' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Censor vulgar words in message
     */
    public function censorMessage($message) {
        $censored = $message;
        foreach ($this->vulgarWords as $word) {
            $replacement = str_repeat('*', strlen($word));
            $censored = preg_replace('/\b' . preg_quote($word, '/') . '\b/i', $replacement, $censored);
        }
        return $censored;
    }
}
?>
