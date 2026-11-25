-- Chay cac cau SELECT truoc de xem du lieu can xu ly.
-- Thuc hien cac khoi ALTER tung buoc; khong chay UNIQUE cho den khi het du lieu trung.
SELECT id, email, CHAR_LENGTH(matkhau) AS do_dai_hash
FROM users
WHERE matkhau IS NULL OR CHAR_LENGTH(matkhau) < 60;

SELECT email, COUNT(*) AS so_tai_khoan
FROM users
WHERE email IS NOT NULL AND email <> ''
GROUP BY email
HAVING COUNT(*) > 1;

SELECT sdt, COUNT(*) AS so_tai_khoan
FROM users
WHERE sdt IS NOT NULL
GROUP BY sdt
HAVING COUNT(*) > 1;

-- Kiem tra trung so sau khi bo khoang trang, cham, gach va chuan hoa dau +84/84.
SELECT so_chuan_hoa, COUNT(*) AS so_tai_khoan
FROM (
    SELECT CASE
        WHEN so_sach LIKE '+84%' THEN CONCAT('0', SUBSTRING(so_sach, 4))
        WHEN so_sach LIKE '84%' THEN CONCAT('0', SUBSTRING(so_sach, 3))
        ELSE so_sach
    END AS so_chuan_hoa
    FROM (
        SELECT REPLACE(REPLACE(REPLACE(TRIM(sdt), ' ', ''), '.', ''), '-', '') AS so_sach
        FROM users
        WHERE sdt IS NOT NULL AND sdt <> ''
    ) AS so_da_lam_sach
) AS so_da_chuan_hoa
GROUP BY so_chuan_hoa
HAVING COUNT(*) > 1;

-- Neu co hash thieu do dai, khong xoa tai khoan; yeu cau chu tai khoan dat lai mat khau.
-- Chi chay phan ALTER ben duoi khi cac dong matkhau NULL da duoc xu ly.
ALTER TABLE users
    MODIFY COLUMN matkhau VARCHAR(255) NOT NULL;

-- Database hien tai thieu ca bon cot thong tin cong ty.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS ten_cong_ty VARCHAR(150) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS ma_so_thue VARCHAR(14) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS dia_chi_cty VARCHAR(255) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS email_cty VARCHAR(100) NULL DEFAULT NULL;

-- Kiem tra du lieu trung sau khi them cot, truoc khi tao UNIQUE.
SELECT sdt, COUNT(*) AS so_tai_khoan
FROM users
WHERE sdt IS NOT NULL
GROUP BY sdt
HAVING COUNT(*) > 1;

SELECT ma_so_thue, COUNT(*) AS so_tai_khoan
FROM users
WHERE ma_so_thue IS NOT NULL AND ma_so_thue <> ''
GROUP BY ma_so_thue
HAVING COUNT(*) > 1;

SELECT email_cty, COUNT(*) AS so_tai_khoan
FROM users
WHERE email_cty IS NOT NULL AND email_cty <> ''
GROUP BY email_cty
HAVING COUNT(*) > 1;

-- Chi chay cac cau ALTER UNIQUE sau khi cac SELECT trung deu khong tra ve dong nao.
-- Database hien tai co cac so trung; sua du lieu truoc, neu khong UNIQUE se bao loi.
ALTER TABLE users
    ADD UNIQUE KEY IF NOT EXISTS email (email);

ALTER TABLE users
    ADD UNIQUE KEY IF NOT EXISTS uq_users_sdt (sdt);

ALTER TABLE users
    ADD UNIQUE KEY IF NOT EXISTS uq_users_ma_so_thue (ma_so_thue);

ALTER TABLE users
    ADD UNIQUE KEY IF NOT EXISTS uq_users_email_cty (email_cty);

-- Cot email da co UNIQUE KEY ten la email; cau lenh IF NOT EXISTS o tren se khong tao lai index.
