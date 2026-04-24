CREATE DATABASE IF NOT EXISTS submeet_auth
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS submeet_event
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'diploma'@'%' IDENTIFIED BY 'secret';

GRANT ALL PRIVILEGES ON submeet_auth.* TO 'diploma'@'%';
GRANT ALL PRIVILEGES ON submeet_event.* TO 'diploma'@'%';

FLUSH PRIVILEGES;
