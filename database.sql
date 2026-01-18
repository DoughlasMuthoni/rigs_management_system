-- Create Database
CREATE DATABASE IF NOT EXISTS waterliftsolar_rigs;
USE waterliftsolar_rigs;

-- 1. RIGS TABLE
CREATE TABLE rigs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rig_name VARCHAR(50) NOT NULL,
    rig_code VARCHAR(10) UNIQUE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. PROJECTS TABLE
CREATE TABLE projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_code VARCHAR(20) UNIQUE NOT NULL,
    project_name VARCHAR(200) NOT NULL,
    client_name VARCHAR(200),
    rig_id INT,
    contract_amount DECIMAL(15,2) NOT NULL,
    payment_received DECIMAL(15,2) DEFAULT 0,
    start_date DATE,
    completion_date DATE,
    payment_date DATE,
    status ENUM('pending', 'completed', 'paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rig_id) REFERENCES rigs(id) ON DELETE SET NULL
);

-- 3. FIXED EXPENSES TABLE
CREATE TABLE fixed_expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT,
    salaries DECIMAL(15,2) DEFAULT 0,
    fuel_rig DECIMAL(15,2) DEFAULT 0,
    fuel_truck DECIMAL(15,2) DEFAULT 0,
    fuel_pump DECIMAL(15,2) DEFAULT 0,
    fuel_hired DECIMAL(15,2) DEFAULT 0,
    casing_surface DECIMAL(15,2) DEFAULT 0,
    casing_screened DECIMAL(15,2) DEFAULT 0,
    casing_plain DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- 4. CONSUMABLES ITEMS TABLE
CREATE TABLE consumables (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT,
    item_name VARCHAR(200) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- 5. MISCELLANEOUS ITEMS TABLE
CREATE TABLE miscellaneous (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT,
    item_name VARCHAR(200) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- 6. USERS TABLE (for login)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('admin', 'manager', 'finance') DEFAULT 'manager',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default user (password: admin123)
INSERT INTO users (username, password, full_name, role) 
VALUES ('admin', '$2y$10$YourHashedPasswordHere', 'System Administrator', 'admin');

-- Insert sample rigs
INSERT INTO rigs (rig_name, rig_code) VALUES
('Rig Alpha', 'RA'),
('Rig Beta', 'RB'),
('Rig Gamma', 'RG');