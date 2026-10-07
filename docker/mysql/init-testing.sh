#!/bin/bash
set -euo pipefail
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS course_system_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS course_system_browser CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON course_system_testing.* TO '${MYSQL_USER}'@'%';
GRANT ALL PRIVILEGES ON course_system_browser.* TO '${MYSQL_USER}'@'%';
SQL
