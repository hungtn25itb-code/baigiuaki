CREATE DATABASE IF NOT EXISTS `QLHocPhan`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `QLHocPhan`;

CREATE TABLE IF NOT EXISTS `GiangVien` (
    `IDGV` VARCHAR(20) NOT NULL,
    `Fullname` VARCHAR(150) NOT NULL,
    `Image` VARCHAR(255) DEFAULT NULL,
    `SumClass` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`IDGV`),
    KEY `idx_giangvien_fullname` (`Fullname`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `GiangVien` (`IDGV`, `Fullname`, `Image`, `SumClass`) VALUES
    ('GV001', 'Nguyễn Minh Anh', NULL, 4),
    ('GV002', 'Trần Quốc Bảo', NULL, 3),
    ('GV003', 'Lê Thu Hà', NULL, 5),
    ('GV004', 'Phạm Hoàng Nam', NULL, 2),
    ('GV005', 'Võ Ngọc Lan', NULL, 4),
    ('GV006', 'Đặng Thành Đạt', NULL, 3),
    ('GV007', 'Bùi Phương Thảo', NULL, 6),
    ('GV008', 'Hoàng Đức Huy', NULL, 2),
    ('GV009', 'Đỗ Khánh Linh', NULL, 5),
    ('GV010', 'Phan Gia Hưng', NULL, 3);
