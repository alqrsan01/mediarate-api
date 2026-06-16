<?php
define('TMDB_TOKEN', getenv('TMDB_TOKEN') ?: 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiI0OGM5ZmU1OTdlNWZiNjBiMDc1MDhkMjQyOTM3YTE0NCIsIm5iZiI6MTc2NTAzODAxOC4xMTEsInN1YiI6IjY5MzQ1N2MyMDc4OTgwZWEyNWQxZjkzOCIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.FnIBD-e1Wwo5f3m-Lx7rk6P3zwdNioWQgEyeBw2MoRs');

// ── CORS (must be first — before any DB work so failures don't mask as CORS errors) ──
$allowedOrigins = array_filter(array_map('trim', explode(',', getenv('FRONTEND_URL') ?: 'http://localhost:5173')));
$requestOrigin  = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigin  = in_array($requestOrigin, $allowedOrigins) ? $requestOrigin : ($allowedOrigins[0] ?? '*');

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: $allowedOrigin");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ── Database connection ───────────────────────────────────────────────────
if (getenv('DB_HOST')) {
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ?: 5432;
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $name = getenv('DB_NAME') ?: 'postgres';
    $dsn  = "pgsql:host=$host;port=$port;dbname=$name;sslmode=require";
} else {
    $host = 'localhost';
    $port = 5433;
    $user = 'postgres';
    $pass = 'Has107jam';
    $name = 'mediarate';
    $dsn  = "pgsql:host=$host;port=$port;dbname=$name";
}

$pdo = new PDO($dsn, $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ── Schema ────────────────────────────────────────────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        avatar_url TEXT,
        created_at TIMESTAMP DEFAULT NOW()
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS user_media (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        tmdb_id INT NOT NULL,
        media_type VARCHAR(10) NOT NULL,
        status VARCHAR(20) NOT NULL,
        rating INT,
        review TEXT,
        title VARCHAR(255),
        poster_path VARCHAR(255),
        runtime INT,
        season_counts JSONB,
        updated_at TIMESTAMP DEFAULT NOW(),
        UNIQUE(user_id, media_type, tmdb_id)
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS user_episode_ratings (
        user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        show_id INT NOT NULL,
        season_number INT NOT NULL,
        episode_number INT NOT NULL,
        rating INT,
        watched BOOLEAN DEFAULT FALSE,
        updated_at TIMESTAMP DEFAULT NOW(),
        PRIMARY KEY (user_id, show_id, season_number, episode_number)
    )
");

// Stats: genre/decade breakdown columns on user_media
$pdo->exec("ALTER TABLE user_media ADD COLUMN IF NOT EXISTS release_year SMALLINT");
$pdo->exec("ALTER TABLE user_media ADD COLUMN IF NOT EXISTS genres VARCHAR(500)");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS collections (
        id          SERIAL PRIMARY KEY,
        user_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        name        VARCHAR(200) NOT NULL,
        description TEXT,
        created_at  TIMESTAMP DEFAULT NOW(),
        updated_at  TIMESTAMP DEFAULT NOW()
    )
");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_collections_user ON collections(user_id)");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS collection_items (
        id            SERIAL PRIMARY KEY,
        collection_id INT NOT NULL REFERENCES collections(id) ON DELETE CASCADE,
        tmdb_id       INT NOT NULL,
        media_type    VARCHAR(10) NOT NULL DEFAULT 'movie',
        title         VARCHAR(500),
        poster_path   VARCHAR(200),
        added_at      TIMESTAMP DEFAULT NOW(),
        UNIQUE(collection_id, tmdb_id, media_type)
    )
");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_collection_items_collection ON collection_items(collection_id)");

// Social: follows + activity feed
$pdo->exec("
    CREATE TABLE IF NOT EXISTS follows (
        follower_id  INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        following_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        created_at   TIMESTAMP DEFAULT NOW(),
        PRIMARY KEY (follower_id, following_id)
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS activity (
        id          SERIAL PRIMARY KEY,
        user_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        type        VARCHAR(20) NOT NULL,
        media_type  VARCHAR(10) NOT NULL,
        tmdb_id     INT NOT NULL,
        title       VARCHAR(255),
        poster_path VARCHAR(255),
        rating      INT,
        created_at  TIMESTAMP DEFAULT NOW()
    )
");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_activity_user ON activity(user_id)");
