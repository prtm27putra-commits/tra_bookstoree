USE trabooks_db;

ALTER TABLE admins ADD COLUMN email VARCHAR(150) NOT NULL UNIQUE AFTER username;
UPDATE admins SET email = 'admin@gmail.com' WHERE username = 'admin';
