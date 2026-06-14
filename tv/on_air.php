<?php
require_once '../config.php';

$page = intval($_GET['page'] ?? 1);
$url  = "https://api.themoviedb.org/3/tv/on_the_air?language=en-US&page={$page}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . TMDB_TOKEN,
    'Accept: application/json',
]);
$response = curl_exec($ch);
curl_close($ch);

echo $response;