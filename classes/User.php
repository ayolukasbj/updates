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
        try {
            // Generate token function if not exists
            if (!function_exists('generate_token')) {
                function generate_token($length = 32) {
                    if (function_exists('random_bytes')) {
                        return bin2hex(random_bytes($length));
                    } elseif (function_exists('openssl_random_pseudo_bytes')) {
                        return bin2hex(openssl_random_pseudo_bytes($length));
                    } else {
                        // Fallback for older PHP versions
                        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                        $token = '';
                        for ($i = 0; $i < $length * 2; $i++) {
                            $token .= $characters[rand(0, strlen($characters) - 1)];
                        }
                        return $token;
                    }
                }
            }
            
            // Use stage_name if provided, otherwise use username as artist name
            $artist_name = !empty($data['stage_name']) ? $data['stage_name'] : $data['username'];
            
            // Hash password
            $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            $verification_token = generate_token();
            
            // Check if stage_name column exists
            $has_stage_name = false;
            try {
                $col_check = $this->conn->query("SHOW COLUMNS FROM " . $this->table . " LIKE 'stage_name'");
                $has_stage_name = $col_check->rowCount() > 0;
            } catch (Exception $e) {
                $has_stage_name = false;
            }
            
            // Build INSERT query based on available columns
            if ($has_stage_name) {
                $query = "INSERT INTO " . $this->table . " 
                          (username, email, password, first_name, last_name, stage_name, verification_token, is_active, email_verified) 
                          VALUES (:username, :email, :password, :first_name, :last_name, :stage_name, :verification_token, 1, 0)";
            } else {
                $query = "INSERT INTO " . $this->table . " 
                          (username, email, password, first_name, last_name, verification_token, is_active, email_verified) 
                          VALUES (:username, :email, :password, :first_name, :last_name, :verification_token, 1, 0)";
            }
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':username', $data['username']);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':first_name', $data['first_name']);
            $stmt->bindParam(':last_name', $data['last_name']);
            $stmt->bindParam(':verification_token', $verification_token);
            
            if ($has_stage_name) {
                $stmt->bindParam(':stage_name', $artist_name);
            }
            
            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                
                // Try to update artist field if it exists (for backward compatibility)
                try {
                    $updateQuery = "UPDATE " . $this->table . " SET artist = :artist_name WHERE id = :id";
                    $updateStmt = $this->conn->prepare($updateQuery);
                    $updateStmt->bindParam(':artist_name', $artist_name);
                    $updateStmt->bindParam(':id', $this->id);
                    $updateStmt->execute();
                } catch (Exception $e) {
                    // Column might not exist, that's okay
                }
                
                return [
                    'success' => true, 
                    'user_id' => $this->id, 
                    'verification_token' => $verification_token, 
                    'artist_name' => $artist_name
                ];
            } else {
                $error_info = $stmt->errorInfo();
                error_log('Registration SQL error: ' . print_r($error_info, true));
                return ['success' => false, 'error' => 'Registration failed: Database error'];
            }
        } catch (PDOException $e) {
            error_log('Registration PDO error: ' . $e->getMessage());
            error_log('SQL State: ' . $e->getCode());
            return ['success' => false, 'error' => 'Registration failed: ' . $e->getMessage()];
        } catch (Exception $e) {
            error_log('Registration error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return ['success' => false, 'error' => 'Registration failed: ' . $e->getMessage()];
        }
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
                
                // Set session (include email for verification checks)
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['user_email'] = $row['email']; // Store for verification checks
                $_SESSION['subscription_type'] = $row['subscription_type'];
                
                return [
                    'success' => true,
                    'user' => [
                        'id' => $row['id'],
                        'username' => $row['username'],
                        'email' => $row['email'],
                        'subscription_type' => $row['subscription_type'],
                        'email_verified' => $row['email_verified'] ?? 0
                    ]
                ];
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
        try {
            if (empty($token)) {
                error_log('VerifyEmail: Empty token provided');
                return ['success' => false, 'error' => 'Token is required'];
            }
            
            // First, get user info before updating
            $getUserQuery = "SELECT id, username, email, password, subscription_type, is_active, email_verified 
                           FROM " . $this->table . " 
                           WHERE verification_token = :token AND (email_verified = 0 OR email_verified IS NULL)";
            $getUserStmt = $this->conn->prepare($getUserQuery);
            $getUserStmt->bindParam(':token', $token);
            $getUserStmt->execute();
            $user = $getUserStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                error_log('VerifyEmail: No user found with token: ' . substr($token, 0, 10) . '...');
                return ['success' => false, 'error' => 'Invalid or expired verification token'];
            }
            
            // Update email verification status
            $query = "UPDATE " . $this->table . " 
                      SET email_verified = 1, verification_token = NULL 
                      WHERE verification_token = :token AND (email_verified = 0 OR email_verified IS NULL)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':token', $token);
            
            if ($stmt->execute()) {
                $rowCount = $stmt->rowCount();
                if ($rowCount > 0) {
                    error_log('VerifyEmail: Successfully verified email for token: ' . substr($token, 0, 10) . '...');
                    // Return user data for auto-login
                    return [
                        'success' => true,
                        'user' => [
                            'id' => $user['id'],
                            'username' => $user['username'],
                            'email' => $user['email'],
                            'subscription_type' => $user['subscription_type'] ?? 'free',
                            'email_verified' => 1
                        ]
                    ];
                } else {
                    error_log('VerifyEmail: No rows updated. Token may be invalid or already verified.');
                    return ['success' => false, 'error' => 'Token already used or invalid'];
                }
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log('VerifyEmail: SQL execution failed: ' . print_r($errorInfo, true));
                return ['success' => false, 'error' => 'Verification failed'];
            }
        } catch (Exception $e) {
            error_log('VerifyEmail: Exception: ' . $e->getMessage());
            error_log('VerifyEmail: Stack trace: ' . $e->getTraceAsString());
            return ['success' => false, 'error' => 'Verification error: ' . $e->getMessage()];
        } catch (Error $e) {
            error_log('VerifyEmail: Fatal error: ' . $e->getMessage());
            error_log('VerifyEmail: Stack trace: ' . $e->getTraceAsString());
            return ['success' => false, 'error' => 'Verification error: ' . $e->getMessage()];
        }
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
