-- QA observer role for existing MotoTrack databases.
-- The account can browse all panels, while application guards reject writes.

ALTER TABLE `users`
  MODIFY COLUMN `role`
    ENUM('admin','staff','technician','customer','qa') NOT NULL DEFAULT 'customer';

INSERT INTO `users`
  (`name`, `email`, `password`, `phone`, `auth_provider`, `role`, `is_active`)
SELECT
  'QA Tester',
  'qa@mototrack.com',
  '$2y$10$nXytpUF7XgSnpX0V0qKH.u.etOL2XimCQW4lhUOh.Q/m3PToHePey',
  '09001234570',
  'local',
  'qa',
  1
WHERE NOT EXISTS (
  SELECT 1 FROM `users` WHERE `email` = 'qa@mototrack.com'
);
