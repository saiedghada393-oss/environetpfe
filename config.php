<?php
// config.php - Configuration centrale de l'application

// ==================== CONFIGURATION ====================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Configuration base de données
define('DB_HOST', 'sql107.infinityfree.com');
define('DB_NAME', 'if0_41856900_environet_db');
define('DB_USER', 'if0_41856900');
define('DB_PASS', '9QpLpcTgRAH');
define('DB_PORT', 3306);
define('DB_CHARSET', 'utf8mb4');

// Configuration application
define('APP_NAME', 'EnviroNet');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://environetpfe.infinityfreeapp.com');
define('APP_TIMEZONE', 'Europe/Paris');
define('APP_ROOT', __DIR__);

// Configuration email
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'votre-email@gmail.com');  // À modifier
define('SMTP_PASS', 'votre-mot-de-passe-app'); // Mot de passe d'application Gmail
define('SMTP_FROM_EMAIL', 'noreply@environet.com');
define('SMTP_FROM_NAME', 'EnviroNet');

// Sécurité
define('SESSION_LIFETIME', 7200);
define('REMEMBER_TOKEN_EXPIRY', 30);
define('PASSWORD_RESET_EXPIRY', 3600);

// ===== SEUILS D'ALERTES =====

// Température
define('DEFAULT_TEMP_CRITICAL_HIGH', 28);  // > 28°C = critique
define('DEFAULT_TEMP_WARNING_HIGH', 24);   // > 24°C = avertissement
define('DEFAULT_TEMP_WARNING_LOW', 18);    // < 18°C = avertissement
define('DEFAULT_TEMP_CRITICAL_LOW', 15);   // < 15°C = critique

// Humidité
define('DEFAULT_HUM_CRITICAL_HIGH', 80);   // > 80% = critique
define('DEFAULT_HUM_WARNING_HIGH', 70);    // > 70% = avertissement
define('DEFAULT_HUM_WARNING_LOW', 30);     // < 30% = avertissement
define('DEFAULT_HUM_CRITICAL_LOW', 20);    // < 20% = critique

// Signal WiFi
define('DEFAULT_SIGNAL_CRITICAL', 30);     // < 30% = critique
define('DEFAULT_SIGNAL_WARNING', 50);      // < 50% = avertissement

// ==================== CONFIGURATION DU FUSEAU HORAIRE ====================
date_default_timezone_set(APP_TIMEZONE);

// ==================== INCLUSION PHPMailer ====================
// Vérifier si PHPMailer existe
$phpmailerPath = __DIR__ . '/PHPMailer/src/PHPMailer.php';
if (file_exists($phpmailerPath)) {
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    define('PHPMAILER_AVAILABLE', true);
} else {
    define('PHPMAILER_AVAILABLE', false);
    error_log("PHPMailer non trouvé. Utilisation de la fonction mail() native.");
}

// ==================== CONNEXION À LA BASE DE DONNÉES ====================
$pdo = null;

function getDBConnection() {
    global $pdo;
    
    if ($pdo !== null) {
        try {
            $pdo->query("SELECT 1");
            return $pdo;
        } catch(PDOException $e) {
            $pdo = null;
        }
    }
    
    try {
        $dsn = sprintf("mysql:host=%s;port=%d;dbname=%s;charset=%s", DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch(PDOException $e) {
        error_log("Erreur de connexion: " . $e->getMessage());
        return null;
    }
}



function createTablesIfNotExist($pdo) {
    // Table users
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('user', 'admin') DEFAULT 'user',
            is_active TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME,
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Table remember_tokens
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS remember_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_token (token),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Table password_reset_tokens (AJOUTÉE)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_token (token),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Table esp32_cam_data
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS esp32_cam_data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            temperature DECIMAL(5,2),
            humidity DECIMAL(5,2),
            signal_strength INT,
            bandwidth DECIMAL(5,2),
            ping INT,
            ssid VARCHAR(100),
            ip_address VARCHAR(45),
            mac_address VARCHAR(17),
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_timestamp (user_id, timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Table sensor_data
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sensor_data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            temperature DECIMAL(5,2),
            humidity DECIMAL(5,2),
            signal_strength INT,
            bandwidth DECIMAL(5,2),
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_timestamp (user_id, timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Table alerts
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            severity ENUM('info', 'warning', 'critical') DEFAULT 'info',
            location VARCHAR(100),
            status ENUM('unread', 'read') DEFAULT 'unread',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_status (user_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Table rooms
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            location VARCHAR(255),
            position_top VARCHAR(20) DEFAULT '25%',
            position_left VARCHAR(20) DEFAULT '33%',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Table pour suivre l'état des alertes (état précédent)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS alert_state (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            alert_type VARCHAR(50) NOT NULL,
            current_state VARCHAR(50) NOT NULL,
            last_value DECIMAL(10,2),
            last_checked DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_type (user_id, alert_type),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Créer un compte admin par défaut si la table est vide
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Administrateur', 'admin@environet.com', $defaultPassword, 'admin']);
        $adminId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO rooms (user_id, name, location) VALUES (?, ?, ?)");
        $stmt->execute([$adminId, 'Main Room', 'ESP32-CAM Location']);
    }
}

// ==================== GESTION DES SESSIONS ====================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// ==================== FONCTION D'ENVOI D'EMAIL ====================

function sendEmail($to, $subject, $message) {
    // Essayer d'abord avec PHPMailer si disponible
    if (defined('PHPMAILER_AVAILABLE') && PHPMAILER_AVAILABLE) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';
            
            $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Erreur PHPMailer: " . $mail->ErrorInfo);
            // Fallback to mail() function
        }
    }
    
    // Fallback avec la fonction mail() native
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($to, $subject, $message, $headers);
}

// ==================== FONCTIONS UTILITAIRES ====================

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.html');
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit;
    }
}

function jsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response = array_merge($response, $data);
    }
    echo json_encode($response);
    exit;
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function createRememberToken($user_id) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    $token = generateToken();
    $expires = date('Y-m-d H:i:s', strtotime('+' . REMEMBER_TOKEN_EXPIRY . ' days'));
    
    try {
        // Supprimer les anciens tokens
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
        $stmt->execute([$user_id]);
        // Créer un nouveau token
        $stmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $token, $expires]);
        return $token;
    } catch(PDOException $e) {
        error_log("Erreur createRememberToken: " . $e->getMessage());
        return false;
    }
}

function checkRememberToken($token) {
    $pdo = getDBConnection();
    if (!$pdo) return null;
    
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM remember_tokens WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        $result = $stmt->fetch();
        if ($result) {
            $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ? AND is_active = 1");
            $stmt->execute([$result['user_id']]);
            return $stmt->fetch();
        }
        return null;
    } catch(PDOException $e) {
        error_log("Erreur checkRememberToken: " . $e->getMessage());
        return null;
    }
}

// ==================== FONCTIONS PASSWORD RESET ====================

function createPasswordResetToken($user_id) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    $token = generateToken();
    $expires = date('Y-m-d H:i:s', strtotime('+' . PASSWORD_RESET_EXPIRY . ' seconds'));
    
    try {
        // Désactiver les anciens tokens
        $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND used = 0");
        $stmt->execute([$user_id]);
        // Créer un nouveau token
        $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $token, $expires]);
        return $token;
    } catch(PDOException $e) {
        error_log("Erreur createPasswordResetToken: " . $e->getMessage());
        return false;
    }
}

function verifyPasswordResetToken($token) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM password_reset_tokens WHERE token = ? AND expires_at > NOW() AND used = 0");
        $stmt->execute([$token]);
        $result = $stmt->fetch();
        return $result ? $result['user_id'] : false;
    } catch(PDOException $e) {
        error_log("Erreur verifyPasswordResetToken: " . $e->getMessage());
        return false;
    }
}

function markResetTokenAsUsed($token) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
        return $stmt->execute([$token]);
    } catch(PDOException $e) {
        error_log("Erreur markResetTokenAsUsed: " . $e->getMessage());
        return false;
    }
}

// ==================== FONCTIONS SENSOR DATA ====================

function getLatestSensorData($user_id = null) {
    $pdo = getDBConnection();
    if (!$pdo) return null;
    
    if ($user_id === null && isLoggedIn()) {
        $user_id = $_SESSION['user_id'];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM esp32_cam_data WHERE user_id = ? ORDER BY timestamp DESC LIMIT 1");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Erreur getLatestSensorData: " . $e->getMessage());
        return null;
    }
}

function getSensorHistory($user_id = null, $hours = 24) {
    $pdo = getDBConnection();
    if (!$pdo) return [];
    
    if ($user_id === null && isLoggedIn()) {
        $user_id = $_SESSION['user_id'];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM sensor_data WHERE user_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR) ORDER BY timestamp ASC");
        $stmt->execute([$user_id, $hours]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Erreur getSensorHistory: " . $e->getMessage());
        return [];
    }
}

function getUnreadAlerts($user_id = null) {
    $pdo = getDBConnection();
    if (!$pdo) return [];
    
    if ($user_id === null && isLoggedIn()) {
        $user_id = $_SESSION['user_id'];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM alerts WHERE user_id = ? AND status = 'unread' ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Erreur getUnreadAlerts: " . $e->getMessage());
        return [];
    }
}

function createAlert($user_id, $type, $message, $severity = 'info', $location = null) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO alerts (user_id, type, severity, message, location, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'unread', NOW())
        ");
        $stmt->execute([$user_id, $type, $severity, $message, $location ?? 'System']);
        return $pdo->lastInsertId();
    } catch(PDOException $e) {
        error_log("Error creating alert: " . $e->getMessage());
        return false;
    }
}

function saveSensorData($user_id, $temperature = null, $humidity = null, $signal_strength = null, $bandwidth = null, $ping = null, $ssid = null, $ip_address = null, $mac_address = null) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO esp32_cam_data (user_id, temperature, humidity, signal_strength, bandwidth, ping, ssid, ip_address, mac_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $temperature, $humidity, $signal_strength, $bandwidth, $ping, $ssid, $ip_address, $mac_address]);
        
        saveToHistory($user_id, $temperature, $humidity, $signal_strength, $bandwidth);
        return $pdo->lastInsertId();
    } catch(PDOException $e) {
        error_log("Erreur saveSensorData: " . $e->getMessage());
        return false;
    }
}

function saveToHistory($user_id, $temperature = null, $humidity = null, $signal_strength = null, $bandwidth = null) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO sensor_data (user_id, temperature, humidity, signal_strength, bandwidth) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $temperature, $humidity, $signal_strength, $bandwidth]);
        return $pdo->lastInsertId();
    } catch(PDOException $e) {
        error_log("Erreur saveToHistory: " . $e->getMessage());
        return false;
    }
}

function getUserRooms($user_id = null) {
    $pdo = getDBConnection();
    if (!$pdo) return [];
    
    if ($user_id === null && isLoggedIn()) {
        $user_id = $_SESSION['user_id'];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM rooms WHERE user_id = ? ORDER BY created_at ASC");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Erreur getUserRooms: " . $e->getMessage());
        return [];
    }
}

function addRoom($user_id, $name, $location, $position_top = '25%', $position_left = '33%') {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO rooms (user_id, name, location, position_top, position_left, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $name, $location, $position_top, $position_left]);
        return $pdo->lastInsertId();
    } catch(PDOException $e) {
        error_log("Erreur addRoom: " . $e->getMessage());
        return false;
    }
}

function deleteRoom($room_id, $user_id = null) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    if ($user_id === null && isLoggedIn()) {
        $user_id = $_SESSION['user_id'];
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ? AND user_id = ?");
        return $stmt->execute([$room_id, $user_id]);
    } catch(PDOException $e) {
        error_log("Erreur deleteRoom: " . $e->getMessage());
        return false;
    }
}

// ==================== INITIALISATION ====================
$pdo = getDBConnection();
?>