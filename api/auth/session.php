<?php
/**
 * Lightweight session check for tenant users (used by idle-page heartbeat).
 */
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'code' => 'unauthorized', 'message' => 'Unauthorized']);
    exit;
}

echo json_encode(['status' => 'ok']);
