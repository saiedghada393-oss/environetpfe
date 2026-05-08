<?php
require_once 'config.php';
header('Content-Type: application/json');

// ID utilisateur par défaut
$user_id = 1;

// Lire le contenu JSON envoyé
$jsonInput = file_get_contents('php://input');
$inputData = json_decode($jsonInput, true);

// Si des données JSON sont reçues, les utiliser
if ($inputData && is_array($inputData)) {
    $temperature = $inputData['temperature'] ?? null;
    $humidity = $inputData['humidity'] ?? null;
    $signal_strength = $inputData['signal_strength'] ?? null;
    $bandwidth = $inputData['bandwidth'] ?? null;
    $ping = $inputData['ping'] ?? null;
    $ssid = $inputData['ssid'] ?? null;
    $ip_address = $inputData['ip_address'] ?? $inputData['ip'] ?? null;
    $mac_address = $inputData['mac_address'] ?? $inputData['mac'] ?? null;
    $user_id = $inputData['user_id'] ?? $user_id;
} else {
    // Fallback sur GET/POST traditionnel
    $temperature = $_GET['temperature'] ?? $_POST['temperature'] ?? null;
    $humidity = $_GET['humidity'] ?? $_GET['humidite'] ?? $_POST['humidity'] ?? $_POST['humidite'] ?? null;
    $signal_strength = $_GET['signal_strength'] ?? $_POST['signal_strength'] ?? null;
    $bandwidth = $_GET['bandwidth'] ?? $_POST['bandwidth'] ?? null;
    $ping = $_GET['ping'] ?? $_POST['ping'] ?? null;
    $ssid = $_GET['ssid'] ?? $_POST['ssid'] ?? null;
    $ip_address = $_GET['ip'] ?? $_POST['ip'] ?? null;
    $mac_address = $_GET['mac'] ?? $_POST['mac'] ?? null;
}

// Accepter les données même si une seule valeur est présente
if ($temperature === null && $humidity === null && $signal_strength === null && $bandwidth === null && $ping === null) {
    echo json_encode([
        "success" => false, 
        "message" => "Aucune donnée valide reçue"
    ]);
    exit;
}

try {
    $pdo = getDBConnection();
    
// 1. Insérer dans esp32_cam_data (données brutes)
$stmt = $pdo->prepare("
    INSERT INTO esp32_cam_data 
    (user_id, temperature, humidity, signal_strength, bandwidth, ping, ssid, ip_address, mac_address) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $user_id, $temperature, $humidity, $signal_strength, 
    $bandwidth, $ping, $ssid, $ip_address, $mac_address
]);

// 2. Insérer dans sensor_data (pour l'historique - SANS ping)
$stmt2 = $pdo->prepare("
    INSERT INTO sensor_data 
    (user_id, temperature, humidity, signal_strength, bandwidth) 
    VALUES (?, ?, ?, ?, ?)
");
$stmt2->execute([
    $user_id, $temperature, $humidity, $signal_strength, $bandwidth
]);
    
    // 3. ✅ GESTION INTELLIGENTE DES ALERTES (SEULEMENT SI CHANGEMENT)
    $alertCount = 0;
    $alertsGenerated = [];
    
    // === VÉRIFICATION TEMPÉRATURE ===
    if ($temperature !== null) {
        $tempState = getTemperatureState($temperature);
        $previousTempState = getPreviousAlertState($pdo, $user_id, 'temperature');
        
        // Vérifier si l'état a changé ET si ce n'est pas un état normal
        if ($tempState !== $previousTempState['current_state'] && $tempState !== 'normal') {
            // Vérifier l'hystérésis (éviter les oscillations)
            if (shouldGenerateAlert($pdo, $user_id, 'temperature', $temperature, $tempState, $previousTempState)) {
                $message = getAlertMessage('temperature', $temperature, $tempState);
                $severity = getAlertSeverity($tempState);
                $alertId = createAlert($user_id, 'temperature', $message, $severity);
                
                if ($alertId) {
                    $alertCount++;
                    $alertsGenerated[] = [
                        'type' => 'temperature',
                        'state' => $tempState,
                        'value' => $temperature,
                        'message' => $message
                    ];
                    
                    // Mettre à jour l'état
                    updateAlertState($pdo, $user_id, 'temperature', $tempState, $temperature);
                }
            }
        } elseif ($tempState === 'normal' && $previousTempState['current_state'] !== 'normal' && $previousTempState['current_state'] !== null) {
            // Retour à la normale après une alerte
            $message = "Température revenue à la normale : {$temperature}°C";
            $alertId = createAlert($user_id, 'temperature', $message, 'info');
            
            if ($alertId) {
                $alertCount++;
                $alertsGenerated[] = [
                    'type' => 'temperature',
                    'state' => 'normal',
                    'value' => $temperature,
                    'message' => $message
                ];
                
                // Mettre à jour l'état
                updateAlertState($pdo, $user_id, 'temperature', 'normal', $temperature);
            }
        } else {
            // Pas de changement d'état, juste mettre à jour la valeur
            updateAlertStateValue($pdo, $user_id, 'temperature', $temperature);
        }
    }
    
    // === VÉRIFICATION HUMIDITÉ ===
    if ($humidity !== null) {
        $humState = getHumidityState($humidity);
        $previousHumState = getPreviousAlertState($pdo, $user_id, 'humidity');
        
        if ($humState !== $previousHumState['current_state'] && $humState !== 'normal') {
            if (shouldGenerateAlert($pdo, $user_id, 'humidity', $humidity, $humState, $previousHumState)) {
                $message = getAlertMessage('humidity', $humidity, $humState);
                $severity = getAlertSeverity($humState);
                $alertId = createAlert($user_id, 'humidity', $message, $severity);
                
                if ($alertId) {
                    $alertCount++;
                    $alertsGenerated[] = [
                        'type' => 'humidity',
                        'state' => $humState,
                        'value' => $humidity,
                        'message' => $message
                    ];
                    
                    updateAlertState($pdo, $user_id, 'humidity', $humState, $humidity);
                }
            }
        } elseif ($humState === 'normal' && $previousHumState['current_state'] !== 'normal' && $previousHumState['current_state'] !== null) {
            $message = "Humidité revenue à la normale : {$humidity}%";
            $alertId = createAlert($user_id, 'humidity', $message, 'info');
            
            if ($alertId) {
                $alertCount++;
                $alertsGenerated[] = [
                    'type' => 'humidity',
                    'state' => 'normal',
                    'value' => $humidity,
                    'message' => $message
                ];
                
                updateAlertState($pdo, $user_id, 'humidity', 'normal', $humidity);
            }
        } else {
            updateAlertStateValue($pdo, $user_id, 'humidity', $humidity);
        }
    }
    
    // === VÉRIFICATION SIGNAL ===
    if ($signal_strength !== null) {
        $signalState = getSignalState($signal_strength);
        $previousSignalState = getPreviousAlertState($pdo, $user_id, 'signal');
        
        if ($signalState !== $previousSignalState['current_state'] && $signalState !== 'normal') {
            if (shouldGenerateAlert($pdo, $user_id, 'signal', $signal_strength, $signalState, $previousSignalState)) {
                $message = getAlertMessage('signal', $signal_strength, $signalState);
                $severity = getAlertSeverity($signalState);
                $alertId = createAlert($user_id, 'signal', $message, $severity);
                
                if ($alertId) {
                    $alertCount++;
                    $alertsGenerated[] = [
                        'type' => 'signal',
                        'state' => $signalState,
                        'value' => $signal_strength,
                        'message' => $message
                    ];
                    
                    updateAlertState($pdo, $user_id, 'signal', $signalState, $signal_strength);
                }
            }
        } elseif ($signalState === 'normal' && $previousSignalState['current_state'] !== 'normal' && $previousSignalState['current_state'] !== null) {
            $message = "Signal WiFi revenu à la normale : {$signal_strength}%";
            $alertId = createAlert($user_id, 'signal', $message, 'info');
            
            if ($alertId) {
                $alertCount++;
                $alertsGenerated[] = [
                    'type' => 'signal',
                    'state' => 'normal',
                    'value' => $signal_strength,
                    'message' => $message
                ];
                
                updateAlertState($pdo, $user_id, 'signal', 'normal', $signal_strength);
            }
        } else {
            updateAlertStateValue($pdo, $user_id, 'signal', $signal_strength);
        }
    }
    
    echo json_encode([
    "success" => true,
    "message" => "Données insérées" . ($alertCount > 0 ? " + {$alertCount} alerte(s)" : ""),
    "alerts_generated" => $alertCount,  // ✅ Cette ligne est importante
    "alerts_details" => $alertsGenerated
]);
    
} catch (PDOException $e) {
    error_log("Erreur insert.php: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Erreur base de données: " . $e->getMessage()
    ]);
}

// ==================== FONCTIONS DE GESTION DES ALERTES ====================

/**
 * Détermine l'état de la température
 */
function getTemperatureState($value) {
    if ($value > DEFAULT_TEMP_CRITICAL_HIGH) {
        return 'critical';
    } elseif ($value > DEFAULT_TEMP_WARNING_HIGH) {
        return 'warning';
    } elseif ($value >= DEFAULT_TEMP_WARNING_LOW && $value <= DEFAULT_TEMP_WARNING_HIGH) {
        return 'normal';
    } elseif ($value < DEFAULT_TEMP_CRITICAL_LOW) {
        return 'critical_low';
    } elseif ($value < DEFAULT_TEMP_WARNING_LOW) {
        return 'warning_low';
    }
    return 'normal';
}

/**
 * Détermine l'état de l'humidité
 */
function getHumidityState($value) {
    if ($value > DEFAULT_HUM_CRITICAL_HIGH) {
        return 'critical';
    } elseif ($value > DEFAULT_HUM_WARNING_HIGH) {
        return 'warning';
    } elseif ($value >= DEFAULT_HUM_WARNING_LOW && $value <= DEFAULT_HUM_WARNING_HIGH) {
        return 'normal';
    } elseif ($value < DEFAULT_HUM_CRITICAL_LOW) {
        return 'critical_low';
    } elseif ($value < DEFAULT_HUM_WARNING_LOW) {
        return 'warning_low';
    }
    return 'normal';
}

/**
 * Détermine l'état du signal
 */
function getSignalState($value) {
    if ($value < DEFAULT_SIGNAL_CRITICAL) {
        return 'critical';
    } elseif ($value < DEFAULT_SIGNAL_WARNING) {
        return 'warning';
    }
    return 'normal';
}

/**
 * Récupère l'état précédent d'un type d'alerte
 */
function getPreviousAlertState($pdo, $user_id, $type) {
    try {
        $stmt = $pdo->prepare("
            SELECT current_state, last_value 
            FROM alert_state 
            WHERE user_id = ? AND alert_type = ?
        ");
        $stmt->execute([$user_id, $type]);
        $result = $stmt->fetch();
        
        return $result ?: ['current_state' => null, 'last_value' => null];
    } catch (PDOException $e) {
        return ['current_state' => null, 'last_value' => null];
    }
}

/**
 * Vérifie si l'alerte doit être générée (avec hystérésis)
 */
function shouldGenerateAlert($pdo, $user_id, $type, $value, $newState, $previousState) {
    // Si pas d'état précédent, c'est la première alerte
    if ($previousState['current_state'] === null) {
        return true;
    }
    
    // Si c'est le même état, pas d'alerte
    if ($newState === $previousState['current_state']) {
        return false;
    }
    
    // Vérifier l'hystérésis pour éviter les oscillations
    $previousValue = $previousState['last_value'];
    if ($previousValue === null) {
        return true;
    }
    
    // Définir l'hystérésis selon le type
    $hysteresis = [
        'temperature' => 0.5,  // 0.5°C
        'humidity' => 2,        // 2%
        'signal' => 3           // 3%
    ];
    
    $minDifference = $hysteresis[$type] ?? 1;
    $difference = abs($value - $previousValue);
    
    // Si la différence est trop faible, ignorer (évite les oscillations)
    if ($difference < $minDifference) {
        return false;
    }
    
    // Vérifier si le changement est confirmé (2 mesures consécutives dans le nouvel état)
    $consecutiveMeasures = getConsecutiveMeasuresInState($pdo, $user_id, $type, $newState);
    
    // Attendre au moins 2 mesures consécutives pour confirmer le changement
    return $consecutiveMeasures >= 1;
}

/**
 * Compte les mesures consécutives dans un état donné
 */
function getConsecutiveMeasuresInState($pdo, $user_id, $type, $state) {
    // Cette fonction vérifie les dernières valeurs dans esp32_cam_data
    // pour confirmer que le changement est réel
    try {
        $stmt = $pdo->prepare("
            SELECT {$type} as value 
            FROM esp32_cam_data 
            WHERE user_id = ? 
            AND {$type} IS NOT NULL 
            ORDER BY timestamp DESC 
            LIMIT 3
        ");
        $stmt->execute([$user_id]);
        $recentValues = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $count = 0;
        foreach ($recentValues as $recentValue) {
            $recentState = null;
            
            switch ($type) {
                case 'temperature':
                    $recentState = getTemperatureState($recentValue);
                    break;
                case 'humidity':
                    $recentState = getHumidityState($recentValue);
                    break;
                case 'signal':
                    $recentState = getSignalState($recentValue);
                    break;
            }
            
            if ($recentState === $state) {
                $count++;
            } else {
                break;
            }
        }
        
        return $count;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Génère le message d'alerte approprié
 */
function getAlertMessage($type, $value, $state) {
    $messages = [
        'temperature' => [
            'critical' => "⚠️ ALERTE CRITIQUE - Température trop élevée : {$value}°C (seuil: " . DEFAULT_TEMP_CRITICAL_HIGH . "°C)",
            'warning' => "⚠️ ATTENTION - Température élevée : {$value}°C (seuil: " . DEFAULT_TEMP_WARNING_HIGH . "°C)",
            'critical_low' => "⚠️ ALERTE CRITIQUE - Température trop basse : {$value}°C (seuil: " . DEFAULT_TEMP_CRITICAL_LOW . "°C)",
            'warning_low' => "⚠️ ATTENTION - Température basse : {$value}°C (seuil: " . DEFAULT_TEMP_WARNING_LOW . "°C)",
            'normal' => "✅ Température normale : {$value}°C"
        ],
        'humidity' => [
            'critical' => "⚠️ ALERTE CRITIQUE - Humidité trop élevée : {$value}% (seuil: " . DEFAULT_HUM_CRITICAL_HIGH . "%)",
            'warning' => "⚠️ ATTENTION - Humidité élevée : {$value}% (seuil: " . DEFAULT_HUM_WARNING_HIGH . "%)",
            'critical_low' => "⚠️ ALERTE CRITIQUE - Humidité trop basse : {$value}% (seuil: " . DEFAULT_HUM_CRITICAL_LOW . "%)",
            'warning_low' => "⚠️ ATTENTION - Humidité basse : {$value}% (seuil: " . DEFAULT_HUM_WARNING_LOW . "%)",
            'normal' => "✅ Humidité normale : {$value}%"
        ],
        'signal' => [
            'critical' => "⚠️ ALERTE CRITIQUE - Signal WiFi très faible : {$value}% (seuil: " . DEFAULT_SIGNAL_CRITICAL . "%)",
            'warning' => "⚠️ ATTENTION - Signal WiFi faible : {$value}% (seuil: " . DEFAULT_SIGNAL_WARNING . "%)",
            'normal' => "✅ Signal WiFi normal : {$value}%"
        ]
    ];
    
    return $messages[$type][$state] ?? "Alerte {$type} : {$value}";
}

/**
 * Détermine la sévérité de l'alerte
 */
function getAlertSeverity($state) {
    if (strpos($state, 'critical') !== false) {
        return 'critical';
    } elseif (strpos($state, 'warning') !== false) {
        return 'warning';
    }
    return 'info';
}

/**
 * Met à jour l'état d'une alerte
 */
function updateAlertState($pdo, $user_id, $type, $state, $value) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO alert_state (user_id, alert_type, current_state, last_value, last_checked) 
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                current_state = VALUES(current_state),
                last_value = VALUES(last_value),
                last_checked = NOW()
        ");
        $stmt->execute([$user_id, $type, $state, $value]);
    } catch (PDOException $e) {
        error_log("Erreur updateAlertState: " . $e->getMessage());
    }
}

/**
 * Met à jour uniquement la valeur (sans changer l'état)
 */
function updateAlertStateValue($pdo, $user_id, $type, $value) {
    try {
        $stmt = $pdo->prepare("
            UPDATE alert_state 
            SET last_value = ?, last_checked = NOW() 
            WHERE user_id = ? AND alert_type = ?
        ");
        $stmt->execute([$value, $user_id, $type]);
    } catch (PDOException $e) {
        error_log("Erreur updateAlertStateValue: " . $e->getMessage());
    }
}
?>