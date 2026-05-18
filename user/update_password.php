<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Not authenticated']);
  exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$current = $data['current_password'] ?? '';
$new = $data['new_password'] ?? '';

if (!$current || !$new) {
  http_response_code(400);
  echo json_encode(['error' => 'All fields are required']);
  exit();
}

if (strlen($new) < 6) {
  http_response_code(400);
  echo json_encode(['error' => 'New password must be at least 6 characters']);
  exit();
} 

$stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!password_verify($current, $user['password_hash'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Current password is incorrect']);
  exit();
}

$hash = password_hash($new, PASSWORD_BCRYPT);
$stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$stmt->execute([$hash, $_SESSION['user_id']]);

echo json_encode(['message' => 'Password updated successfully']);