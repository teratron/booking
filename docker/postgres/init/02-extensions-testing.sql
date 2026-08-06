-- Same extensions as the development database (see 01-extensions.sql),
-- applied to the test database created in 00-create-testing-database.sql.
-- \c is a psql meta-command, valid here because the init entrypoint runs
-- these files through psql itself, not a bare SQL driver.
\c booking_testing
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
