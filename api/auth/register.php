<?php
header('Content-Type: application/json');
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

function sendJson($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$hotelName = trim($data['hotel_name'] ?? $data['hotelName'] ?? '');
$slug = trim($data['slug'] ?? '');
$ownerName = trim($data['owner_name'] ?? $data['ownerName'] ?? '');
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';
$confirm = $data['password_confirm'] ?? $data['passwordConfirm'] ?? $password;

if ($password !== $confirm) {
    sendJson(['status' => 'error', 'message' => 'Passwords do not match'], 400);
}

$res = TenantManager::registerTenant($hotelName, $slug, $ownerName, $username, $password);

if (!$res['success']) {
    sendJson(['status' => 'error', 'message' => $res['message']], 400);
}

$loginRes = login($hotelName, $username, $password);

sendJson([
    'status' => 'success',
    'message' => 'Hotel account created',
    'tenant' => [
        'id' => $res['tenant']['id'],
        'slug' => $res['tenant']['slug'],
        'name' => $res['tenant']['name'],
    ],
    'logged_in' => $loginRes['success'],
    'redirect' => $loginRes['success'] ? 'admin.php?welcome=1' : 'login.php',
], 201);
