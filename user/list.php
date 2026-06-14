<?php

require_once '../config.php';
require_once '../auth/jwt.php';

if (get_token_user_id() === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$user_id = get_token_user_id();
$method  = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (!empty($_GET['media_type'])) {
        $stmt = $pdo->prepare('SELECT * FROM user_media WHERE user_id = ? AND media_type = ? ORDER BY updated_at DESC');
        $stmt->execute([$user_id, $_GET['media_type']]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM user_media WHERE user_id = ? ORDER BY updated_at DESC');
        $stmt->execute([$user_id]);
    }
    echo json_encode(['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

} elseif ($method === 'POST') {
    $data       = json_decode(file_get_contents('php://input'), true);
    $tmdb_id     = intval($data['tmdb_id'] ?? 0);
    $media_type  = $data['media_type'] ?? 'movie';
    $status      = $data['status'] ?? 'wishlist';
    $rating      = isset($data['rating']) ? intval($data['rating']) : null;
    $review      = $data['review'] ?? null;
    $title         = $data['title'] ?? null;
    $poster_path   = $data['poster_path'] ?? null;
    $runtime       = isset($data['runtime']) ? intval($data['runtime']) : null;
    $season_counts = isset($data['season_counts']) ? json_encode($data['season_counts']) : null;

    $stmt = $pdo->prepare('
    INSERT INTO user_media (user_id, media_type, tmdb_id, status, rating, review, title, poster_path, runtime, season_counts)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON CONFLICT (user_id, media_type, tmdb_id) DO UPDATE SET
      status = EXCLUDED.status,
      rating = EXCLUDED.rating,
      review = EXCLUDED.review,
      title = COALESCE(EXCLUDED.title, user_media.title),
      poster_path = COALESCE(EXCLUDED.poster_path, user_media.poster_path),
      runtime = COALESCE(EXCLUDED.runtime, user_media.runtime),
      season_counts = COALESCE(EXCLUDED.season_counts, user_media.season_counts),
      updated_at = NOW()
    ');
    $stmt->execute([$user_id, $media_type, $tmdb_id, $status, $rating, $review, $title, $poster_path, $runtime, $season_counts]);
    echo json_encode(['message' => 'Saved']);

} elseif ($method === 'DELETE') {
    $data       = json_decode(file_get_contents('php://input'), true);
    $tmdb_id    = intval($data['tmdb_id'] ?? 0);
    $media_type = $data['media_type'] ?? 'movie';

    $stmt = $pdo->prepare('DELETE FROM user_media WHERE user_id = ? AND media_type = ? AND tmdb_id = ?');
    $stmt->execute([$user_id, $media_type, $tmdb_id]);
    echo json_encode(['message' => 'Removed']);
}

