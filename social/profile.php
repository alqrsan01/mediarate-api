<?php
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$username = trim($_GET['username'] ?? '');
if (!$username) {
    http_response_code(400);
    echo json_encode(['error' => 'Username required']);
    exit();
}

$stmt = $pdo->prepare('SELECT id, username, avatar_url, created_at FROM users WHERE username = ?');
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit();
}

$uid = $user['id'];

$stmt = $pdo->prepare('SELECT COUNT(*) FROM follows WHERE following_id = ?');
$stmt->execute([$uid]);
$user['followers_count'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM follows WHERE follower_id = ?');
$stmt->execute([$uid]);
$user['following_count'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT EXISTS(SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ?)');
$stmt->execute([$_SESSION['user_id'], $uid]);
$user['is_following'] = (bool)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT * FROM user_media WHERE user_id = ? ORDER BY updated_at DESC');
$stmt->execute([$uid]);
$user['media'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['profile' => $user]);
