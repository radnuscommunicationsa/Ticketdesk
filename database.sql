-- TicketDesk IT Support System — Database Schema
-- Run this SQL in your MySQL/MariaDB database first


-- Employees table
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emp_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    department VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    role ENUM('employee','admin') DEFAULT 'employee',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tickets table
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_no VARCHAR(20) UNIQUE NOT NULL,
    emp_id INT NOT NULL,
    category VARCHAR(100) NOT NULL,
    priority ENUM('critical','high','medium','low') NOT NULL,
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    asset VARCHAR(100),
    contact_pref ENUM('Email','Phone','Slack') DEFAULT 'Email',
    status ENUM('open','in-progress','resolved','closed') DEFAULT 'open',
    assigned_to INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at DATETIME DEFAULT NULL,
    FOREIGN KEY (emp_id) REFERENCES employees(id),
    FOREIGN KEY (assigned_to) REFERENCES employees(id) ON DELETE SET NULL
);

-- Ticket activity log
CREATE TABLE IF NOT EXISTS ticket_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    done_by INT NOT NULL,
    note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (done_by) REFERENCES employees(id)
);

-- Insert default admin account (password: Admin@1234)
INSERT INTO employees (emp_id, name, email, password, department, role) VALUES
('ADMIN-001', 'IT Administrator', 'admin@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'IT', 'admin');

-- Insert sample employees (password for all: Pass@1234)
INSERT INTO employees (emp_id, name, email, password, department, phone, role) VALUES
('EMP-0011', 'Sarah Mitchell', 's.mitchell@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Engineering', '9876543210', 'employee'),
('EMP-0023', 'James Park', 'j.park@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Finance', '9876543211', 'employee'),
('EMP-0034', 'Priya Nair', 'p.nair@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'HR', '9876543212', 'employee'),
('EMP-0045', 'Tom Walsh', 't.walsh@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Marketing', '9876543213', 'employee'),
('EMP-0056', 'Carlos Ruiz', 'c.ruiz@company.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Operations', '9876543214', 'employee');

-- Insert sample tickets
INSERT INTO tickets (ticket_no, emp_id, category, priority, subject, description, status) VALUES
('TKT-1001', 2, 'Network / Connectivity', 'critical', 'VPN not connecting after Windows update', 'VPN client fails to connect after latest Windows update KB5034441. Error code 619 appears.', 'open'),
('TKT-1002', 3, 'Software / Application', 'high', 'Outlook crashes on startup', 'Microsoft Outlook crashes immediately on launch. Event Viewer shows application error.', 'in-progress'),
('TKT-1003', 4, 'Access / Permissions', 'low', 'Password reset request', 'Locked out of account after too many login attempts. Needs password reset.', 'resolved'),
('TKT-1004', 5, 'Hardware Issue', 'medium', 'Laptop screen flickering', 'Dell XPS 15 screen flickering at random intervals. Happens on battery and plugged in.', 'in-progress'),
('TKT-1005', 6, 'Security Incident', 'critical', 'Phishing email received', 'Received suspicious email mimicking CEO. Credentials may have been entered.', 'open');

-- ══════════════════════════════════════
-- ASSET MANAGEMENT TABLES
-- ══════════════════════════════════════

-- Assets table
CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_code VARCHAR(30) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    category ENUM('Laptop','Desktop','Monitor','Keyboard','Mouse','Printer','Phone','Server','Network Device','Other') NOT NULL,
    brand VARCHAR(100),
    model VARCHAR(100),
    serial_no VARCHAR(100),
    purchase_date DATE,
    warranty_until DATE,
    status ENUM('Available','Assigned','Under Repair','Damaged','Retired') DEFAULT 'Available',
    location VARCHAR(100),
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Asset assignments
CREATE TABLE IF NOT EXISTS asset_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    emp_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    returned_at DATETIME DEFAULT NULL,
    notes TEXT,
    FOREIGN KEY (asset_id) REFERENCES assets(id),
    FOREIGN KEY (emp_id) REFERENCES employees(id),
    FOREIGN KEY (assigned_by) REFERENCES employees(id)
);

-- Asset history log
CREATE TABLE IF NOT EXISTS asset_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    done_by INT NOT NULL,
    note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id),
    FOREIGN KEY (done_by) REFERENCES employees(id)
);

-- Sample assets
INSERT INTO assets (asset_code, name, category, brand, model, serial_no, purchase_date, warranty_until, status, location) VALUES
('AST-1001', 'Dell Laptop XPS 15', 'Laptop', 'Dell', 'XPS 15 9530', 'DL2024XP001', '2024-01-15', '2027-01-15', 'Assigned', 'Floor 2'),
('AST-1002', 'HP LaserJet Printer', 'Printer', 'HP', 'LaserJet Pro M404n', 'HP2023LJ002', '2023-06-10', '2025-06-10', 'Under Repair', 'Floor 3'),
('AST-1003', 'MacBook Pro 14', 'Laptop', 'Apple', 'MacBook Pro M3', 'AP2024MB003', '2024-03-01', '2027-03-01', 'Available', 'IT Storage'),
('AST-1004', 'Dell Monitor 27"', 'Monitor', 'Dell', 'UltraSharp U2722D', 'DL2023MN004', '2023-08-20', '2026-08-20', 'Assigned', 'Floor 1'),
('AST-1005', 'Cisco Switch 24-Port', 'Network Device', 'Cisco', 'Catalyst 2960', 'CS2022SW005', '2022-11-05', '2025-11-05', 'Available', 'Server Room'),
('AST-1006', 'ThinkPad X1 Carbon', 'Laptop', 'Lenovo', 'X1 Carbon Gen 11', 'LN2024X1006', '2024-02-14', '2027-02-14', 'Damaged', 'Floor 2');
