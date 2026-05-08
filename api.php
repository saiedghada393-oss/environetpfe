<?php
header("Content-Type: application/json");

require_once "config.php";

// ================== JSON SUPPORT ==================
$inputJSON = file_get_contents("php://input");
$input = json_decode($inputJSON, true);

if ($input && is_array($input)) {
    $_POST = array_merge($_POST, $input);
}

// ================== HELPER ==================
function response($status, $message, $data = null) {
    echo json_encode([
        "status" => $status, // 🔥 compatible Android
        "message" => $message,
        "data" => $data
    ]);
    exit;
}

// ================== DB ==================
$pdo = getDBConnection();

if (!$pdo) {
    response("error", "Database connection failed");
}

// ================== ACTION ==================
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    response("error", "No action provided");
}

// ================== LOGIN ==================
if ($action === "login") {

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        response("error", "Email or password missing");
    }

    $stmt = $pdo->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
    $stmt->execute([$email]);

    $user = $stmt->fetch();

    if ($user) {

        if ($password === $user['password'] || password_verify($password, $user['password'])) {

            unset($user['password']);
            response("success", "Login success", $user);

        } else {
            response("error", "Wrong password");
        }

    } else {
        response("error", "User not found");
    }
}

// ================== REGISTER ==================
elseif ($action === "register") {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($name === '' || $email === '' || $password === '') {
        response("error", "Missing fields");
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->rowCount() > 0) {
        response("error", "Email already exists");
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");

    if ($stmt->execute([$name, $email, $hashed])) {
        response("success", "User registered");
    } else {
        response("error", "Insert failed");
    }
}

// ================== GET SENSOR ==================
elseif ($action === "getSensors") {

    $stmt = $pdo->query("SELECT * FROM sensor_data ORDER BY id DESC LIMIT 50");
    $data = $stmt->fetchAll();

    response("success", "Sensor data", $data);
}

// ================== INSERT SENSOR ==================
elseif ($action === "insertSensor") {

    $temperature = $_GET['temperature'] ?? $_POST['temperature'] ?? null;
    $humidity    = $_GET['humidity'] ?? $_POST['humidity'] ?? null;

    if ($temperature === null || $humidity === null) {
        response("error", "Missing temperature or humidity");
    }

    $user_id = 1; // temporaire

    $stmt = $pdo->prepare("
        INSERT INTO sensor_data (user_id, temperature, humidity, timestamp)
        VALUES (?, ?, ?, NOW())
    ");

    if ($stmt->execute([$user_id, $temperature, $humidity])) {
        response("success", "Data inserted");
    } else {
        response("error", "Insert error");
    }
}

elseif ($action === "user") {
    $user_id = $_GET['user_id'] ?? 0;

    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id=?");
    $stmt->execute([$user_id]);

    $user = $stmt->fetch();

    if ($user) {
        response("success", "User data", $user);
    } else {
        response("error", "User not found");
    }
}

elseif ($action === "last_update") {
    $stmt = $pdo->query("SELECT timestamp FROM sensor_data ORDER BY timestamp DESC LIMIT 1");
    $row = $stmt->fetch();

    response("success", "Last update", [
        "latest_update" => $row['timestamp'] ?? null
    ]);
}

// ================== DEFAULT ==================
else {
    response("error", "Invalid action");
}
?>