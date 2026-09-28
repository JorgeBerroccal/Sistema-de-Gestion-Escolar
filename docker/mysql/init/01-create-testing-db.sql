CREATE DATABASE IF NOT EXISTS `sge_testing`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `sge_testing`.* TO 'sge'@'%';

FLUSH PRIVILEGES;
