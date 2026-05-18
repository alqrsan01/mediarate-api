<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
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
$stmt->execute([$_SESSION['user_id']]);
echo json_encode(['feed' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
