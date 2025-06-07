-- Create database
CREATE DATABASE IF NOT EXISTS evsu_voice;
USE evsu_voice;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role ENUM('student', 'admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Suggestions table
CREATE TABLE suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL, -- NULL for anonymous suggestions
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(100) NOT NULL,
    status ENUM('pending', 'new', 'under_review', 'in_progress', 'rejected', 'implemented') DEFAULT 'pending',
    is_anonymous BOOLEAN DEFAULT FALSE,
    upvotes_count INT DEFAULT 0,
    admin_response TEXT NULL,
    admin_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Votes table (to track who voted for what)
CREATE TABLE votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    suggestion_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vote (user_id, suggestion_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (suggestion_id) REFERENCES suggestions(id) ON DELETE CASCADE
);

-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Archive table for deleted suggestions
CREATE TABLE archived_suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_id INT NOT NULL, -- Original suggestion ID
    user_id INT NULL, -- Original user who created the suggestion
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(100) NOT NULL,
    status ENUM('pending', 'new', 'under_review', 'in_progress', 'rejected', 'implemented') NOT NULL,
    is_anonymous BOOLEAN DEFAULT FALSE,
    upvotes_count INT DEFAULT 0,
    admin_response TEXT NULL,
    admin_id INT NULL, -- Admin who last modified the suggestion
    original_created_at TIMESTAMP NOT NULL, -- When the suggestion was originally created
    original_updated_at TIMESTAMP NOT NULL, -- When the suggestion was last updated
    deleted_by INT NOT NULL, -- User ID who deleted the suggestion
    deleted_by_role ENUM('student', 'admin') NOT NULL, -- Role of the person who deleted
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- When it was deleted
    deletion_reason VARCHAR(255) NULL, -- Optional reason for deletion
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_deleted_by (deleted_by),
    INDEX idx_deleted_at (deleted_at),
    INDEX idx_original_id (original_id)
);

-- Insert default categories
INSERT INTO categories (name, description) VALUES
('Academic Affairs', 'Suggestions related to curriculum, teaching, and academic programs'),
('Student Services', 'Suggestions about student support services and facilities'),
('Campus Facilities', 'Suggestions for improving campus infrastructure and facilities'),
('Technology', 'Suggestions about IT services, systems, and digital resources'),
('Student Life', 'Suggestions related to extracurricular activities and student organizations'),
('Administration', 'Suggestions about administrative processes and policies'),
('Library Services', 'Suggestions for improving library resources and services'),
('Health and Safety', 'Suggestions related to campus health and safety measures'),
('Transportation', 'Suggestions about campus transportation and parking'),
('Food Services', 'Suggestions about cafeteria and food-related services'),
('Other', 'General suggestions that don\'t fit other categories');

-- Insert default admin user
INSERT INTO users (email, password, first_name, last_name, role) 
VALUES ('admin@evsu.edu.ph', 'admin', 'Admin', 'User', 'admin');

INSERT INTO categories (name, description) VALUES
('Academic', 'Suggestions related to academic programs and curriculum'),
('Facilities', 'Suggestions about campus facilities and infrastructure'),
('Student Services', 'Suggestions about student support services'),
('Technology', 'Suggestions about IT services and technology'),
('Campus Life', 'Suggestions about student activities and campus environment'),
('Other', 'Other suggestions that don\'t fit in specific categories'); 