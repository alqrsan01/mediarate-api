<?php

require_once '../config.php';
require_once '../auth/jwt.php';

if (get_token_user_id() === null) {
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
$stmt->execute([$avatar_url, get_token_user_id()]);

$stmt = $pdo->prepare('SELECT id, username, email, avatar_url FROM users WHERE id = ?');
$stmt->execute([get_token_user_id()]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['user' => $user]);