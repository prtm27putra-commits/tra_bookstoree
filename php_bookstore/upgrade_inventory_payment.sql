USE trabooks_db;

ALTER TABLE books ADD COLUMN stock INT UNSIGNED NOT NULL DEFAULT 0 AFTER price;
ALTER TABLE orders ADD COLUMN payment_proof VARCHAR(255) NULL AFTER payment_method;
ALTER TABLE orders ADD COLUMN payment_status ENUM('menunggu','menunggu_verifikasi','lunas') NOT NULL DEFAULT 'menunggu' AFTER payment_proof;
UPDATE books SET stock = 10 WHERE stock = 0;
