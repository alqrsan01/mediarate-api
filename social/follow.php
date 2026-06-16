<?php
require_once '../config.php';
require_once '../auth/jwt.php';

$user_id = get_token_user_id();
if ($user_id === null) {
  http_response_code(401);
  echo json_encode(['error' => 'Not authenticated']);
  exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$following_id = intval($data['user_id'] ?? 0);

if (!$following_id || $following_id === $user_id) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid user']);
  exit();
}

$stmt = $pdo->prepare('INSERT INTO follows (follower_id, following_id) VALUES (?, ?) ON CONFLICT DO NOTHING');
$stmt->execute([$user_id, $following_id]);
echo json_encode(['message' => 'Followed']);
