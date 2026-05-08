<?php
require_once 'config.php';
header('Content-Type: application/json');

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 1;

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Récupérer toutes les alertes
    $stmt = $pdo->prepare("
        SELECT * FROM alerts 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 100
    ");
    $stmt->execute([$user_id]);
    $alerts = $stmt->fetchAll();
    
    // Compter les alertes non lues
    $stmt2 = $pdo->prepare("
        SELECT COUNT(*) as unread_count 
        FROM alerts 
        WHERE user_id = ? AND status = 'unread'
    ");
    $stmt2->execute([$user_id]);
    $unreadData = $stmt2->fetch();
    
    echo json_encode([
        "success" => true,
        "alerts" => $alerts,
        "total" => count($alerts),
        "unread_count" => $unreadData['unread_count']
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_alerts.php: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Error retrieving alerts"
    ]);
}
?>