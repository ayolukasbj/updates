<?php
// classes/User.php
// User model for authentication and user management

class User {
    private $conn;
    private $table = 'users';

    public $id;
    public $username;
    public $email;
    public $password;
    public $first_name;
    public $last_name;
    public $avatar;
    public $subscription_type;
    public $subscription_expires;
    public $is_active;
    public $email_verified;
    public $created_at;
    public $last_login;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Register new user
    public function register($data) {
        // Use stage_name if provided, otherwise use username as artist name
        $artist_name = !empty($data['stage_name']) ? $data['stage_name'] : $data['username'];
        
        // Check if stage_name column exists, if not, we'll use a separate query to update artist name
        $query = "INSERT INTO " . $this->table . " 
                  (username, email, password, first_name, last_name, verification_token) 
                  VALUES (:username, :email, :password, :first_name, :last_name, :verification_token)";
        
        $stmt = $this->conn->prepare($query);
        
        // Hash password
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        $verification_token = generate_token();
        
        $stmt->bindParam(':username', $data['username']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':first_name', $data['first_name']);
        $stmt->bindParam(':last_name', $data['last_name']);
        $stmt->bindParam(':verification_token', $verification_token);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            
            // Update songs table to use stage_name as artist name for this user
            // Also update the users table if stage_name column exists
            try {
                // Try to add stage_name to users table if column doesn't exist
                $updateQuery = "UPDATE " . $this->table . " SET artist = :artist_name WHERE id = :id";
                $updateStmt = $this->conn->prepare($updateQuery);
                $updateStmt->bindParam(':artist_name', $artist_name);
                $updateStmt->bindParam(':id', $this->id);
                $updateStmt->execute();
            } catch (Exception $e) {
                // Column might not exist, that's okay - we'll use the artist field in songs table
            }
            
            return ['success' => true, 'user_id' => $this->id, 'verification_token' => $verification_token, 'artist_name' => $artist_name];
        }
        
        return ['success' => false, 'error' => 'Registration failed'];
    }

    // Login user (accepts username or email)
    public function login($username_or_email, $password) {
        // Check if input is email or username
        $is_email = filter_var($username_or_email, FILTER_VALIDATE_EMAIL);
        
        if ($is_email) {
            // Login by email
            $query = "SELECT id, username, email, password, subscription_type, is_active, email_verified 
                      FROM " . $this->table . " 
                      WHERE LOWER(email) = LOWER(:identifier) AND is_active = 1";
        } else {
            // Login by username
            $query = "SELECT id, username, email, password, subscription_type, is_active, email_verified 
                      FROM " . $this->table . " 
                      WHERE username = :identifier AND is_active = 1";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':identifier', $username_or_email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $row['password'])) {
                // Update last login
                $this->updateLastLogin($row['id']);
                
                // Set session
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['subscription_type'] = $row['subscription_type'];
                
                return ['success' => true, 'user' => $row];
            }
        }
        
        return ['success' => false, 'error' => 'Invalid username/email or password'];
    }

    // Get user by ID
    public function getUserById($id) {
        $query = "SELECT id, username, email, first_name, last_name, avatar, 
                         subscription_type, subscription_expires, created_at, last_login
                  FROM " . $this->table . " 
                  WHERE id = :id AND is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get user by email
    public function getUserByEmail($email) {
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Update user profile
    public function updateProfile($id, $username, $email, $bio = '', $stage_name = '') {
        // Build dynamic query based on available columns
        try {
            // Try to update with all possible columns
            $query = "UPDATE " . $this->table . " 
                      SET username = :username, email = :email, 
                          updated_at = CURRENT_TIMESTAMP";
            
            // Add bio if column exists
            if (!empty($bio)) {
                $query .= ", bio = :bio";
            }
            
            // Add stage_name/artist if provided
            if (!empty($stage_name)) {
                // Try artist column first, then stage_name
                $query .= ", artist = :stage_name";
            }
            
            $query .= " WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':id', $id);
            
            if (!empty($bio)) {
                $stmt->bindParam(':bio', $bio);
            }
            
            if (!empty($stage_name)) {
                $stmt->bindParam(':stage_name', $stage_name);
            }
            
            return $stmt->execute();
        } catch (Exception $e) {
            // Fallback: try without artist/stage_name column
            try {
                $query = "UPDATE " . $this->table . " 
                          SET username = :username, email = :email, 
                              updated_at = CURRENT_TIMESTAMP 
                          WHERE id = :id";
                
                if (!empty($bio)) {
                    $query = str_replace("updated_at = CURRENT_TIMESTAMP", "bio = :bio, updated_at = CURRENT_TIMESTAMP", $query);
                }
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                if (!empty($bio)) {
                    $stmt->bindParam(':bio', $bio);
                }
                $stmt->bindParam(':id', $id);
                
                return $stmt->execute();
            } catch (Exception $e2) {
                error_log("Error updating profile: " . $e2->getMessage());
                return false;
            }
        }
    }

    // Change password
    public function changePassword($id, $current_password, $new_password) {
        // First verify current password
        $query = "SELECT password FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!password_verify($current_password, $user['password'])) {
            return ['success' => false, 'error' => 'Current password is incorrect'];
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $query = "UPDATE " . $this->table . " 
                  SET password = :password, updated_at = CURRENT_TIMESTAMP 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Password update failed'];
    }

    // Verify email
    public function verifyEmail($token) {
        $query = "UPDATE " . $this->table . " 
                  SET email_verified = 1, verification_token = NULL 
                  WHERE verification_token = :token";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        
        return $stmt->execute() && $stmt->rowCount() > 0;
    }

    // Request password reset
    public function requestPasswordReset($email) {
        $user = $this->getUserByEmail($email);
        
        if (!$user) {
            return ['success' => false, 'error' => 'Email not found'];
        }
        
        $reset_token = generate_token();
        $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $query = "UPDATE " . $this->table . " 
                  SET reset_token = :reset_token, reset_token_expires = :reset_expires 
                  WHERE email = :email";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':reset_token', $reset_token);
        $stmt->bindParam(':reset_expires', $reset_expires);
        $stmt->bindParam(':email', $email);
        
        if ($stmt->execute()) {
            return ['success' => true, 'reset_token' => $reset_token];
        }
        
        return ['success' => false, 'error' => 'Reset request failed'];
    }

    // Reset password
    public function resetPassword($token, $new_password) {
        $query = "SELECT id FROM " . $this->table . " 
                  WHERE reset_token = :token AND reset_token_expires > NOW()";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid or expired token'];
        }
        
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $query = "UPDATE " . $this->table . " 
                  SET password = :password, reset_token = NULL, reset_token_expires = NULL 
                  WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':id', $user['id']);
        
        if ($stmt->execute()) {
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Password reset failed'];
    }

    // Update last login
    private function updateLastLogin($id) {
        $query = "UPDATE " . $this->table . " SET last_login = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
    }

    // Check if username exists
    public function usernameExists($username) {
        $query = "SELECT id FROM " . $this->table . " WHERE username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    // Check if email exists
    public function emailExists($email) {
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    // Get user statistics
    public function getUserStats($id) {
        $stats = [];
        
        // Get playlists count
        $query = "SELECT COUNT(*) as count FROM playlists WHERE user_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $stats['playlists'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Get favorites count
        $query = "SELECT COUNT(*) as count FROM user_favorites WHERE user_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $stats['favorites'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Get downloads count
        $query = "SELECT COUNT(*) as count FROM downloads WHERE user_id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $stats['downloads'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        return $stats;
    }
}
?>
