<?php
require_once '../config.php';
require_once '../auth/jwt.php';

$user_id = get_token_user_id();
if ($user_id === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$stmt = $pdo->prepare('
    SELECT a.*, u.username, u.avatar_url
    FROM activity a
    JOIN users u ON u.id = a.user_id
    WHERE a.user_id IN (
        SELECT following_id FROM follows WHERE follower_id = ?
    )
    ORDER BY a.created_at DESC
    LIMIT 50
');
$stmt->execute([$user_id]);
echo json_encode(['feed' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
