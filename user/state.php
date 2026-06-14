<?php

require_once '../config.php';
require_once '../auth/jwt.php';

if (get_token_user_id() === null) {
  http_response_code(401);
  echo json_encode(['error' => 'Not authenticated']);
  exit();
}

$user_id = get_token_user_id();

$stmt = $pdo->prepare('
  SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status = \'watched\'  THEN 1 ELSE 0 END) as watched,
    SUM(CASE WHEN status = \'watching\' THEN 1 ELSE 0 END) as watching,
    SUM(CASE WHEN status = \'wishlist\' THEN 1 ELSE 0 END) as wishlist,
    SUM(CASE WHEN status = \'dropped\'  THEN 1 ELSE 0 END) as dropped,
    ROUND(AVG(CASE WHEN rating IS NOT NULL THEN rating END), 1) as avg_rating,
    COUNT(rating) as rated_count
  FROM user_media
  WHERE user_id = ? AND media_type = \'movie\'
');
$stmt->execute([$user_id]);
$state = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['state' => $state]);
