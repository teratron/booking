-- A dedicated test database, separate from the development one. The Pest
-- suite runs against real PostgreSQL/PostGIS rather than SQLite — the schema
-- uses PostGIS geography columns and Postgres-only partial indexes that
-- SQLite cannot represent at all, so a SQLite test connection would silently
-- test nothing about the actual database engine this portal ships on.
-- Sharing the development database instead was rejected: RefreshDatabase
-- wipes its target on every test run, which would destroy local dev data.
CREATE DATABASE booking_testing;
