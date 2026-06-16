<?php
require_once '../config.php';
require_once '../auth/jwt.php';

$user_id = get_token_user_id();
if ($user_id === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$q = trim($_GET['q'] ?? '');
if (!$q) {
    echo json_encode(['users' => []]);
    exit();
}

$stmt = $pdo->prepare('
    SELECT u.id, u.username, u.avatar_url,
           EXISTS(SELECT 1 FROM follows WHERE follower_id = ? AND following_id = u.id)::int as is_following
    FROM users u
    WHERE u.username ILIKE ? AND u.id != ?
    LIMIT 20
');
$stmt->execute([$user_id, "%$q%", $user_id]);
echo json_encode(['users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
