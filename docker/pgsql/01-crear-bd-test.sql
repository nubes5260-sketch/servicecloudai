-- Se ejecuta solo la primera vez que se crea el volumen de PostgreSQL.
-- Crea la base que usan los tests (php artisan test).
CREATE DATABASE servicecloud_ai_test OWNER servicecloud;
