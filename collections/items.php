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
    $collection_id = intval($_GET['collection_id'] ?? 0);
    if (!$collection_id) { http_response_code(400); echo json_encode(['error' => 'Invalid']); exit(); }

    // Verify ownership
    $own = $pdo->prepare('SELECT id, name, description FROM collections WHERE id = ? AND user_id = ?');
    $own->execute([$collection_id, $user_id]);
    $col = $own->fetch(PDO::FETCH_ASSOC);
    if (!$col) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit(); }

    $stmt = $pdo->prepare('SELECT * FROM collection_items WHERE collection_id = ? ORDER BY added_at DESC');
    $stmt->execute([$collection_id]);
    echo json_encode(['collection' => $col, 'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

} elseif ($method === 'POST') {
    $data          = json_decode(file_get_contents('php://input'), true);
    $collection_id = intval($data['collection_id'] ?? 0);
    $tmdb_id       = intval($data['tmdb_id'] ?? 0);
    $media_type    = $data['media_type'] ?? 'movie';
    $title         = $data['title'] ?? null;
    $poster_path   = $data['poster_path'] ?? null;
    if (!$collection_id || !$tmdb_id) { http_response_code(400); echo json_encode(['error' => 'Invalid']); exit(); }

    // Verify ownership
    $own = $pdo->prepare('SELECT id FROM collections WHERE id = ? AND user_id = ?');
    $own->execute([$collection_id, $user_id]);
    if (!$own->fetch()) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit(); }

    $stmt = $pdo->prepare('
        INSERT INTO collection_items (collection_id, tmdb_id, media_type, title, poster_path)
        VALUES (?, ?, ?, ?, ?)
        ON CONFLICT (collection_id, tmdb_id, media_type) DO NOTHING
    ');
    $stmt->execute([$collection_id, $tmdb_id, $media_type, $title, $poster_path]);

    // bump updated_at on collection
    $pdo->prepare('UPDATE collections SET updated_at = NOW() WHERE id = ?')->execute([$collection_id]);
    echo json_encode(['message' => 'Added']);

} elseif ($method === 'DELETE') {
    $data          = json_decode(file_get_contents('php://input'), true);
    $collection_id = intval($data['collection_id'] ?? 0);
    $tmdb_id       = intval($data['tmdb_id'] ?? 0);
    $media_type    = $data['media_type'] ?? 'movie';
    if (!$collection_id || !$tmdb_id) { http_response_code(400); echo json_encode(['error' => 'Invalid']); exit(); }

    $own = $pdo->prepare('SELECT id FROM collections WHERE id = ? AND user_id = ?');
    $own->execute([$collection_id, $user_id]);
    if (!$own->fetch()) { http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit(); }

    $pdo->prepare('DELETE FROM collection_items WHERE collection_id = ? AND tmdb_id = ? AND media_type = ?')
        ->execute([$collection_id, $tmdb_id, $media_type]);
    $pdo->prepare('UPDATE collections SET updated_at = NOW() WHERE id = ?')->execute([$collection_id]);
    echo json_encode(['message' => 'Removed']);
}
