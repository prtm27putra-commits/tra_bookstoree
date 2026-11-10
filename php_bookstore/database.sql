CREATE DATABASE IF NOT EXISTS trabooks_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE trabooks_db;

CREATE TABLE books (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    author VARCHAR(120) NOT NULL,
    category VARCHAR(100) NOT NULL,
    price INT UNSIGNED NOT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    cover VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    buyer_name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    address TEXT NOT NULL,
    order_code VARCHAR(30) NOT NULL UNIQUE,
    total INT UNSIGNED NOT NULL,
    payment_method VARCHAR(30) NOT NULL DEFAULT 'COD',
    payment_status ENUM('menunggu', 'menunggu_verifikasi', 'lunas') NOT NULL DEFAULT 'menunggu',
    payment_proof VARCHAR(255) NULL,
    status ENUM('baru', 'diproses', 'selesai', 'dibatalkan') NOT NULL DEFAULT 'baru',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    book_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    price INT UNSIGNED NOT NULL,
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_order_items_book
        FOREIGN KEY (book_id) REFERENCES books(id)
        ON DELETE RESTRICT
);

INSERT INTO books (title, author, category, price, cover, stock) VALUES
('Laut Bercerita', 'Leila S. Chudori', 'Fiksi', 89000, 'images/laut-bercerita.jpg', 10),
('Pulang', 'Leila S. Chudori', 'Fiksi', 79000, 'images/pulang.jpg', 10),
('Filosofi Teras', 'Henry Manampiring', 'Nonfiksi', 98000, 'images/filosofi-teras.jpg', 10),
('Atomic Habits', 'James Clear', 'Nonfiksi', 108000, 'images/atomic-habits.jpg', 10);

INSERT INTO categories (name) VALUES ('Fiksi'), ('Nonfiksi');
