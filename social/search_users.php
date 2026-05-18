<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
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
           EXISTS(SELECT 1 FROM follows WHERE follower_id = ? AND following_id = u.id) as is_following
    FROM users u
    WHERE u.username LIKE ? AND u.id != ?
    LIMIT 20
');
$stmt->execute([$_SESSION['user_id'], "%$q%", $_SESSION['user_id']]);
echo json_encode(['users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
