<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Not authenticated']);
  exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare('
  SELECT
    COUNT(*) as total,
    SUM(status = "watched") as watched,
    SUM(status = "watching") as watching,
    SUM(status = "wishlist") as wishlist,
    SUM(status = "dropped") as dropped,
    ROUND(AVG(CASE WHEN rating IS NOT NULL THEN rating END), 1) as avg_rating,
    COUNT(CASE WHEN rating IS NOT NULL THEN 1 END) as rated_count
  FROM user_media
  WHERE user_id = ? AND media_type = "movie"
');
$stmt->execute([$user_id]);
$state = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['state' => $state]);