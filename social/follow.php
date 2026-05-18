<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Not authenticated']);
  exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$following_id = intval($data['user_id'] ?? 0);

if (!$following_id || $following_id === $_SESSION['user_id']) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid user']);
  exit();
}

$stmt = $pdo->prepare('INSERT IGNORE INTO follows (follower_id, following_id) VALUES (?, ?)');
$stmt->execute([$_SESSION['user_id'], $following_id]);
echo json_encode(['message' => 'Followed']);