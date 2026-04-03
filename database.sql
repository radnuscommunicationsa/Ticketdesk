  CREATE TABLE IF NOT EXISTS notifications (
      id INT AUTO_INCREMENT PRIMARY KEY,
      emp_id INT NOT NULL,
      ticket_id INT,
      message TEXT NOT NULL,
      is_read TINYINT(1) DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (emp_id) REFERENCES employees(id) ON DELETE CASCADE,       
      FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE       
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;        

  CREATE INDEX idx_notifications_emp_id ON notifications(emp_id);
  CREATE INDEX idx_notifications_is_read ON notifications(is_read);
  CREATE INDEX idx_notifications_created_at ON notifications(created_at      
  DESC);

  -- Password Reset Tokens table
  CREATE TABLE IF NOT EXISTS password_reset_tokens (
      id INT AUTO_INCREMENT PRIMARY KEY,
      emp_id INT NOT NULL,
      token VARCHAR(64) NOT NULL UNIQUE,
      expires_at DATETIME NOT NULL,
      used TINYINT(1) DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (emp_id) REFERENCES employees(id) ON DELETE CASCADE        
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;        

  CREATE INDEX idx_password_reset_token ON password_reset_tokens(token);     
  CREATE INDEX idx_password_reset_emp_id ON password_reset_tokens(emp_id);   
  CREATE INDEX idx_password_reset_expires ON
  password_reset_tokens(expires_at);