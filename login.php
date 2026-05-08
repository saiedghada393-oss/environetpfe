<?php
// login.php - Traitement de la connexion
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Méthode non autorisée');
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$remember = isset($_POST['remember']) && ($_POST['remember'] === 'true' || $_POST['remember'] === '1');

if (empty($email) || empty($password)) {
    jsonResponse(false, 'Veuillez remplir tous les champs');
}

try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT id, name, email, password, role, is_active FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(false, 'Email ou mot de passe incorrect');
    }
    
    if ($user['is_active'] != 1) {
        jsonResponse(false, 'Votre compte est désactivé');
    }
    
    if (!password_verify($password, $user['password'])) {
        jsonResponse(false, 'Email ou mot de passe incorrect');
    }
    
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['logged_in'] = true;
    
    if ($remember) {
        $token = createRememberToken($user['id']);
        if ($token) {
            setcookie('remember_token', $token, time() + (REMEMBER_TOKEN_EXPIRY * 24 * 3600), '/', '', false, true);
        }
    }
    
    jsonResponse(true, 'Connexion réussie', ['redirect' => 'dashboard.php']);
    
} catch(PDOException $e) {
    error_log("Erreur login: " . $e->getMessage());
    jsonResponse(false, 'Erreur de connexion à la base de données');
}
?>