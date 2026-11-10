USE trabooks_db;

INSERT INTO admins (username, email, password)
SELECT 'admin', 'admin@gmail.com', '$2y$10$QIv0o1hlPbTZ6hRxNd4dv.iz2lKl/S6HpuRqCQEfNSRmIh3GViM4u'
WHERE NOT EXISTS (SELECT 1 FROM admins WHERE username = 'admin');

-- Akun awal admin: username admin | password admin123
