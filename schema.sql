-- PostgreSQL schema for MediaRate
-- Run once: psql -U postgres -d mediarate -f schema.sql

CREATE TABLE IF NOT EXISTS users (
    id            SERIAL PRIMARY KEY,
    username      VARCHAR(100) UNIQUE NOT NULL,
    email         VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar_url    TEXT,
    created_at    TIMESTAMP DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS user_media (
    id           SERIAL PRIMARY KEY,
    user_id      INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    tmdb_id      INT NOT NULL,
    media_type   VARCHAR(10) NOT NULL,
    status       VARCHAR(20) NOT NULL,
    rating       INT,
    review       TEXT,
    title        VARCHAR(255),
    poster_path  VARCHAR(255),
    runtime      INT,
    season_counts JSONB,
    updated_at   TIMESTAMP DEFAULT NOW(),
    UNIQUE(user_id, media_type, tmdb_id)
);

CREATE TABLE IF NOT EXISTS user_episode_ratings (
    user_id        INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    show_id        INT NOT NULL,
    season_number  INT NOT NULL,
    episode_number INT NOT NULL,
    rating         INT,
    watched        BOOLEAN DEFAULT FALSE,
    updated_at     TIMESTAMP DEFAULT NOW(),
    PRIMARY KEY (user_id, show_id, season_number, episode_number)
);
