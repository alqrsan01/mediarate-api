<?php
require_once '../config.php';

$id = intval($_GET['id'] ?? 0);
if (! $id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing person id']);
    exit();
}

$url = "https://api.themoviedb.org/3/person/{$id}?language=en-US&append_to_response=movie_credits,tv_credits,external_ids";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . TMDB_TOKEN,
    'Accept: application/json',
]);
$response = curl_exec($ch);
curl_close($ch);

echo $response;