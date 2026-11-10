-- Database lengkap untuk aplikasi TraaBooks.
-- Import file ini lewat phpMyAdmin atau MySQL. Database trabooks_db akan dibuat otomatis.

CREATE DATABASE IF NOT EXISTS trabooks_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE trabooks_db;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS books (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    author VARCHAR(120) NOT NULL,
    category VARCHAR(100) NOT NULL,
    price INT UNSIGNED NOT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    cover VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_books_category (category)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    buyer_name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    address TEXT NOT NULL,
    order_code VARCHAR(30) NOT NULL UNIQUE,
    total INT UNSIGNED NOT NULL,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'COD',
    payment_proof VARCHAR(255) NULL,
    payment_status ENUM('menunggu', 'menunggu_verifikasi', 'lunas') NOT NULL DEFAULT 'menunggu',
    status ENUM('baru', 'diproses', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'baru',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_orders_user_id (user_id),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    book_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    price INT UNSIGNED NOT NULL,
    INDEX idx_order_items_book_id (book_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT IGNORE INTO categories (name) VALUES ('Fiksi'), ('Nonfiksi');

INSERT INTO books (title, author, category, price, stock, cover)
SELECT seed.title, seed.author, seed.category, seed.price, seed.stock, seed.cover
FROM (
    SELECT 'Laut Bercerita' AS title, 'Leila S. Chudori' AS author, 'Fiksi' AS category, 89000 AS price, 10 AS stock, 'images/laut-bercerita.jpg' AS cover
    UNION ALL SELECT 'Pulang', 'Leila S. Chudori', 'Fiksi', 79000, 10, 'images/pulang.jpg'
    UNION ALL SELECT 'Filosofi Teras', 'Henry Manampiring', 'Nonfiksi', 98000, 10, 'images/filosofi-teras.jpg'
    UNION ALL SELECT 'Atomic Habits', 'James Clear', 'Nonfiksi', 108000, 10, 'images/atomic-habits.jpg'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM books WHERE books.title = seed.title);

-- Akun awal: username admin, email admin@gmail.com, password admin123.
INSERT IGNORE INTO admins (username, email, password)
VALUES ('admin', 'admin@gmail.com', '$2y$10$QIv0o1hlPbTZ6hRxNd4dv.iz2lKl/S6HpuRqCQEfNSRmIh3GViM4u');
