<?php
require_once '../config.php';
require_once '../auth/jwt.php';

$user_id = get_token_user_id();
if ($user_id === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // list all collections for the user
    $stmt = $pdo->prepare('
        SELECT c.*, COUNT(ci.id) AS item_count
        FROM collections c
        LEFT JOIN collection_items ci ON ci.collection_id = c.id
        WHERE c.user_id = ?
        GROUP BY c.id
        ORDER BY c.updated_at DESC
    ');
    $stmt->execute([$user_id]);
    echo json_encode(['collections' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $name = trim($data['name'] ?? '');
    $desc = trim($data['description'] ?? '');
    if (!$name) { http_response_code(400); echo json_encode(['error' => 'Name required']); exit(); }

    $stmt = $pdo->prepare('INSERT INTO collections (user_id, name, description) VALUES (?, ?, ?) RETURNING id');
    $stmt->execute([$user_id, $name, $desc ?: null]);
    $id = $stmt->fetchColumn();
    echo json_encode(['id' => (int)$id, 'name' => $name, 'message' => 'Created']);

} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id   = intval($data['id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $desc = trim($data['description'] ?? '');
    if (!$id || !$name) { http_response_code(400); echo json_encode(['error' => 'Invalid']); exit(); }

    $stmt = $pdo->prepare('UPDATE collections SET name = ?, description = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');
    $stmt->execute([$name, $desc ?: null, $id, $user_id]);
    echo json_encode(['message' => 'Updated']);

} elseif ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id   = intval($data['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'Invalid']); exit(); }

    $pdo->prepare('DELETE FROM collection_items WHERE collection_id IN (SELECT id FROM collections WHERE id = ? AND user_id = ?)')->execute([$id, $user_id]);
    $pdo->prepare('DELETE FROM collections WHERE id = ? AND user_id = ?')->execute([$id, $user_id]);
    echo json_encode(['message' => 'Deleted']);
}
