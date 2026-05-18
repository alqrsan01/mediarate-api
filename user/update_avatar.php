<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Not authenticated']);
  exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$avatar_url = trim($data['avatar_url'] ?? '');

if (!$avatar_url) {
  http_response_code(400);
  echo json_encode(['error' => 'Avatar URL is required']);
  exit();
}

if  (!filter_var($avatar_url, FILTER_VALIDATE_URL)) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid URL']);
  exit();
}

$stmt = $pdo->prepare('UPDATE users SET avatar_url = ? WHERE id = ?');
$stmt->execute([$avatar_url, $_SESSION['user_id']]);

$stmt = $pdo->prepare('SELECT id, username, email, avatar_url FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['user' => $user]);