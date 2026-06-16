<?php
require_once '../config.php';
require_once '../auth/jwt.php';

$viewer_id = get_token_user_id();
if ($viewer_id === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = intval($_GET['user_id'] ?? $viewer_id);

$stmt = $pdo->prepare('
    SELECT u.id, u.username, u.avatar_url,
           EXISTS(SELECT 1 FROM follows WHERE follower_id = ? AND following_id = u.id)::int as is_following
    FROM follows f
    JOIN users u ON u.id = f.follower_id
    WHERE f.following_id = ?
');
$stmt->execute([$viewer_id, $user_id]);
echo json_encode(['followers' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
