CREATE DATABASE IF NOT EXISTS books_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE books_api;

DROP TABLE IF EXISTS books;

CREATE TABLE books(
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    author VARCHAR(150) NOT NULL,
    year SMALLINT NOT NULL,
    genre VARCHAR(80) NOT NULL DEFAULT 'Uncategorised',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO books(title, author, year, genre) VALUES 
('Clean Code', 'Robert C. Martin', 2008, 'Software Engineering'), 
('Eloquent JavaScript', 'Marijn Haverbeke', 2018, 'Programming'), 
('Vue.js 3 By Example', 'John Au-Yeung', 2021, 'Web Development');