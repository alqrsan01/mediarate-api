<?php
// ── Source: local PostgreSQL ──────────────────────────────────────────────
$srcDsn  = 'pgsql:host=localhost;port=5433;dbname=mediarate';
$srcUser = 'postgres';
$srcPass = 'Has107jam';

// ── Target: Supabase ──────────────────────────────────────────────────────
// Replace YOUR_PASSWORD with your actual Supabase password
$tgtDsn  = 'pgsql:host=aws-1-ap-northeast-1.pooler.supabase.com;port=5432;dbname=postgres;sslmode=require';
$tgtUser = 'postgres.fuipxhnupscfwengplxa';
$tgtPass = 'As7StWFY1MQ7by3o';

try {
    $src = new PDO($srcDsn, $srcUser, $srcPass);
    $src->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected to local PostgreSQL\n";

    $tgt = new PDO($tgtDsn, $tgtUser, $tgtPass);
    $tgt->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected to Supabase\n";
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

// ── Create schema on Supabase ─────────────────────────────────────────────
$tgt->exec("
    CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        avatar_url TEXT,
        created_at TIMESTAMP DEFAULT NOW()
    )
");
$tgt->exec("
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
$tgt->exec("
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
echo "✓ Schema ready on Supabase\n";

// ── Wipe existing data (order matters due to FK constraints) ──────────────
$tgt->exec("TRUNCATE TABLE user_episode_ratings, user_media, users RESTART IDENTITY CASCADE");
echo "✓ Wiped all existing Supabase data\n";

// ── Migrate users ─────────────────────────────────────────────────────────
$users = $src->query("SELECT * FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$ins = $tgt->prepare("
    INSERT INTO users (id, username, email, password_hash, avatar_url, created_at)
    VALUES (?, ?, ?, ?, ?, ?)
    ON CONFLICT (id) DO UPDATE SET
        username = EXCLUDED.username,
        email = EXCLUDED.email,
        password_hash = EXCLUDED.password_hash,
        avatar_url = EXCLUDED.avatar_url
");
foreach ($users as $r) {
    $ins->execute([$r['id'], $r['username'], $r['email'], $r['password_hash'], $r['avatar_url'], $r['created_at']]);
}
$tgt->exec("SELECT setval('users_id_seq', (SELECT MAX(id) FROM users))");
echo "✓ Migrated " . count($users) . " users\n";

// ── Migrate user_media ────────────────────────────────────────────────────
$media = $src->query("SELECT * FROM user_media ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$ins = $tgt->prepare("
    INSERT INTO user_media (id, user_id, tmdb_id, media_type, status, rating, review, title, poster_path, runtime, season_counts, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?)
    ON CONFLICT (user_id, media_type, tmdb_id) DO UPDATE SET
        status = EXCLUDED.status,
        rating = EXCLUDED.rating,
        review = EXCLUDED.review,
        title  = EXCLUDED.title,
        poster_path   = EXCLUDED.poster_path,
        runtime       = EXCLUDED.runtime,
        season_counts = EXCLUDED.season_counts,
        updated_at    = EXCLUDED.updated_at
");
foreach ($media as $r) {
    $ins->execute([
        $r['id'], $r['user_id'], $r['tmdb_id'], $r['media_type'],
        $r['status'], $r['rating'], $r['review'], $r['title'],
        $r['poster_path'], $r['runtime'], $r['season_counts'], $r['updated_at']
    ]);
}
$tgt->exec("SELECT setval('user_media_id_seq', (SELECT MAX(id) FROM user_media))");
echo "✓ Migrated " . count($media) . " media items\n";

// ── Migrate user_episode_ratings ──────────────────────────────────────────
$eps = $src->query("SELECT * FROM user_episode_ratings")->fetchAll(PDO::FETCH_ASSOC);
$ins = $tgt->prepare("
    INSERT INTO user_episode_ratings (user_id, show_id, season_number, episode_number, rating, watched, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON CONFLICT (user_id, show_id, season_number, episode_number) DO UPDATE SET
        rating     = EXCLUDED.rating,
        watched    = EXCLUDED.watched,
        updated_at = EXCLUDED.updated_at
");
foreach ($eps as $r) {
    $ins->execute([
        $r['user_id'], $r['show_id'], $r['season_number'],
        $r['episode_number'], $r['rating'],
        ($r['watched'] === 't' || $r['watched'] === true || $r['watched'] === 1) ? 'true' : 'false',
        $r['updated_at']
    ]);
}
echo "✓ Migrated " . count($eps) . " episode ratings\n";

echo "\n✅ Migration complete!\n";
