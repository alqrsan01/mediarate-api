<?php
require_once '../config.php';

$sort = $_GET['sort_by'] ?? 'popularity.desc';
$page = intval($_GET['page'] ?? 1);
$genre = $_GET['genre'] ?? '';
$year = $_GET['year'] ?? '';

$allowed_sorts = [
  'popularity.desc', 'popularity.asc',
  'vote_average.desc', 'vote_average.asc',
  'first_air_date.desc', 'first_air_date.asc',
  'name.asc', 'name.desc',
];

if (!in_array($sort, $allowed_sorts)) $sort = 'popularity.desc';
if ($page < 1) $page = 1;

$url = "https://api.themoviedb.org/3/discover/tv?language=en-US&sort_by={$sort}&page={$page}&vote_count.gte=50";
if ($genre) $url .= "&with_genres={$genre}";
if ($year) $url .= "&first_air_date_year={$year}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . TMDB_TOKEN,
    'Accept: application/json',
]);
$response = curl_exec($ch);
curl_close($ch);

echo $response;