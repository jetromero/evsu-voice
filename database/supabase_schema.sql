-- Supabase PostgreSQL Schema for EVSU Voice
-- Run this in Supabase SQL Editor

-- Enable Row Level Security (RLS) extension if not already enabled
-- This is automatically enabled in Supabase

-- Users table
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role VARCHAR(20) DEFAULT 'student' CHECK (role IN ('student', 'admin')),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Create trigger for updated_at
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Suggestions table
CREATE TABLE suggestions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(100) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'new', 'under_review', 'in_progress', 'rejected', 'implemented')),
    is_anonymous BOOLEAN DEFAULT FALSE,
    upvotes_count INTEGER DEFAULT 0,
    admin_response TEXT,
    admin_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER update_suggestions_updated_at BEFORE UPDATE ON suggestions
FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Votes table
CREATE TABLE votes (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    suggestion_id INTEGER NOT NULL REFERENCES suggestions(id) ON DELETE CASCADE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_vote UNIQUE (user_id, suggestion_id)
);

-- Categories table
CREATE TABLE categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Archive table for deleted suggestions
CREATE TABLE archived_suggestions (
    id SERIAL PRIMARY KEY,
    original_id INTEGER NOT NULL,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL CHECK (status IN ('pending', 'new', 'under_review', 'in_progress', 'rejected', 'implemented')),
    is_anonymous BOOLEAN DEFAULT FALSE,
    upvotes_count INTEGER DEFAULT 0,
    admin_response TEXT,
    admin_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    original_created_at TIMESTAMP WITH TIME ZONE NOT NULL,
    original_updated_at TIMESTAMP WITH TIME ZONE NOT NULL,
    deleted_by INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    deleted_by_role VARCHAR(20) NOT NULL CHECK (deleted_by_role IN ('student', 'admin')),
    deleted_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    deletion_reason VARCHAR(255)
);

-- Create indexes for better performance
CREATE INDEX idx_archived_user_id ON archived_suggestions(user_id);
CREATE INDEX idx_archived_deleted_by ON archived_suggestions(deleted_by);
CREATE INDEX idx_archived_deleted_at ON archived_suggestions(deleted_at);
CREATE INDEX idx_archived_original_id ON archived_suggestions(original_id);
CREATE INDEX idx_suggestions_user_id ON suggestions(user_id);
CREATE INDEX idx_suggestions_status ON suggestions(status);
CREATE INDEX idx_suggestions_category ON suggestions(category);
CREATE INDEX idx_votes_suggestion_id ON votes(suggestion_id);

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
('Other', 'General suggestions that don''t fit other categories');

-- Insert default admin user
INSERT INTO users (email, password, first_name, last_name, role) 
VALUES ('evsu.admin@evsu.edu.ph', 'admin', 'EVSU', 'Admin', 'admin');

-- Row Level Security (RLS) Policies
-- Enable RLS on all tables
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE suggestions ENABLE ROW LEVEL SECURITY;
ALTER TABLE votes ENABLE ROW LEVEL SECURITY;
ALTER TABLE categories ENABLE ROW LEVEL SECURITY;
ALTER TABLE archived_suggestions ENABLE ROW LEVEL SECURITY;

-- RLS Policies for users table
CREATE POLICY "Users can view their own data" ON users
    FOR SELECT USING (true); -- Allow reading for now, can be restricted later

CREATE POLICY "Users can update their own data" ON users
    FOR UPDATE USING (true); -- Allow updates for now

-- RLS Policies for suggestions table
CREATE POLICY "Anyone can view published suggestions" ON suggestions
    FOR SELECT USING (status != 'pending' OR status = 'pending');

CREATE POLICY "Users can insert suggestions" ON suggestions
    FOR INSERT WITH CHECK (true);

CREATE POLICY "Users can update their own suggestions" ON suggestions
    FOR UPDATE USING (true);

-- RLS Policies for votes table
CREATE POLICY "Users can view votes" ON votes
    FOR SELECT USING (true);

CREATE POLICY "Users can insert votes" ON votes
    FOR INSERT WITH CHECK (true);

CREATE POLICY "Users can delete their own votes" ON votes
    FOR DELETE USING (true);

-- RLS Policies for categories table
CREATE POLICY "Anyone can view categories" ON categories
    FOR SELECT USING (true);

-- RLS Policies for archived_suggestions table
CREATE POLICY "Anyone can view archived suggestions" ON archived_suggestions
    FOR SELECT USING (true);

-- Create a function to handle vote counting
CREATE OR REPLACE FUNCTION update_suggestion_vote_count()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        UPDATE suggestions SET upvotes_count = upvotes_count + 1 WHERE id = NEW.suggestion_id;
        RETURN NEW;
    ELSIF TG_OP = 'DELETE' THEN
        UPDATE suggestions SET upvotes_count = upvotes_count - 1 WHERE id = OLD.suggestion_id;
        RETURN OLD;
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- Create triggers for vote counting
CREATE TRIGGER trigger_update_vote_count_insert
    AFTER INSERT ON votes
    FOR EACH ROW EXECUTE FUNCTION update_suggestion_vote_count();

CREATE TRIGGER trigger_update_vote_count_delete
    AFTER DELETE ON votes
    FOR EACH ROW EXECUTE FUNCTION update_suggestion_vote_count(); 