-- Extensions the catalog depends on. Created once at first container start.
-- postgis      : bbox / radius queries for the map, nearby objects, distance filters
-- pg_trgm      : fuzzy matching on object and territory names
-- unaccent     : diacritic-insensitive search (Romanian, Ukrainian)
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
