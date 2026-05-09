-- Grant the application user CREATE/DROP on databases matching the project
-- naming convention. Pest 4 with --parallel spins up `webte2_test_1`,
-- `webte2_test_2`, ... per worker; without these grants the parallel runner
-- fails with "Access denied ... to database 'webte2_test_2'".
--
-- The grant is intentionally narrow: only databases starting with `webte2`.
-- Mounted into /docker-entrypoint-initdb.d/ — runs only on first volume
-- initialisation. Existing dev volumes need to drop and recreate (or apply
-- this SQL manually) for the change to take effect.

GRANT CREATE, DROP, ALTER, INDEX, REFERENCES,
      SELECT, INSERT, UPDATE, DELETE,
      CREATE VIEW, SHOW VIEW,
      CREATE ROUTINE, ALTER ROUTINE, EXECUTE,
      EVENT, TRIGGER, LOCK TABLES
  ON `webte2%`.*
  TO 'webte2'@'%';

FLUSH PRIVILEGES;
