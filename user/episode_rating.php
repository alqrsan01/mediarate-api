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
    // Get all episode ratings for a show+season
    $show_id       = intval($_GET['show_id'] ?? 0);
    $season_number = intval($_GET['season'] ?? 0);

    if (!$show_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing show_id']);
        exit();
    }

    $stmt = $pdo->prepare('
        SELECT episode_number, rating, CASE WHEN watched THEN 1 ELSE 0 END AS watched
        FROM user_episode_ratings
        WHERE user_id = ? AND show_id = ? AND season_number = ?
    ');
    $stmt->execute([$user_id, $show_id, $season_number]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Key by episode_number for easy frontend lookup
    $result = [];
    foreach ($rows as $row) {
        $result[(int)$row['episode_number']] = [
            'rating'  => $row['rating'] !== null ? (int)$row['rating'] : null,
            'watched' => (bool)$row['watched'],
        ];
    }
    echo json_encode(['ratings' => $result]);

} elseif ($method === 'POST') {
    $data           = json_decode(file_get_contents('php://input'), true);
    $show_id        = intval($data['show_id'] ?? 0);
    $season_number  = intval($data['season_number'] ?? 0);
    $episode_number = intval($data['episode_number'] ?? 0);
    $rating         = isset($data['rating']) && $data['rating'] !== null ? intval($data['rating']) : null;
    $watched        = isset($data['watched']) ? (int)(bool)$data['watched'] : null;

    if (!$show_id || !$episode_number) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit();
    }

    // Build dynamic update
    if ($rating !== null && $watched !== null) {
        $stmt = $pdo->prepare('
            INSERT INTO user_episode_ratings (user_id, show_id, season_number, episode_number, rating, watched)
            VALUES (?, ?, ?, ?, ?, ?)
            ON CONFLICT (user_id, show_id, season_number, episode_number) DO UPDATE SET
              rating = EXCLUDED.rating, watched = EXCLUDED.watched, updated_at = NOW()
        ');
        $stmt->execute([$user_id, $show_id, $season_number, $episode_number, $rating, $watched]);
    } elseif ($rating !== null) {
        $stmt = $pdo->prepare('
            INSERT INTO user_episode_ratings (user_id, show_id, season_number, episode_number, rating)
            VALUES (?, ?, ?, ?, ?)
            ON CONFLICT (user_id, show_id, season_number, episode_number) DO UPDATE SET
              rating = EXCLUDED.rating, updated_at = NOW()
        ');
        $stmt->execute([$user_id, $show_id, $season_number, $episode_number, $rating]);
    } else {
        // Only watched toggle
        $stmt = $pdo->prepare('
            INSERT INTO user_episode_ratings (user_id, show_id, season_number, episode_number, watched)
            VALUES (?, ?, ?, ?, ?)
            ON CONFLICT (user_id, show_id, season_number, episode_number) DO UPDATE SET
              watched = EXCLUDED.watched, updated_at = NOW()
        ');
        $stmt->execute([$user_id, $show_id, $season_number, $episode_number, $watched ?? 0]);
    }

    echo json_encode(['message' => 'Saved']);

} elseif ($method === 'DELETE') {
    $data           = json_decode(file_get_contents('php://input'), true);
    $show_id        = intval($data['show_id'] ?? 0);
    $season_number  = intval($data['season_number'] ?? 0);
    $episode_number = intval($data['episode_number'] ?? 0);

    $stmt = $pdo->prepare('
        DELETE FROM user_episode_ratings
        WHERE user_id = ? AND show_id = ? AND season_number = ? AND episode_number = ?
    ');
    $stmt->execute([$user_id, $show_id, $season_number, $episode_number]);
    echo json_encode(['message' => 'Removed']);
}
