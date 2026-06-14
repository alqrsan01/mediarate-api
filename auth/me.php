<?php
require_once '../config.php';
require_once 'jwt.php';

$userId = get_token_user_id();
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$stmt = $pdo->prepare('SELECT id, username, email, avatar_url FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['user' => $user]);
