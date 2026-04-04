<?php
/**
 * Migration Script: Create password_reset_tokens table
 *
 * This script creates the required table for password reset functionality.
 * Run this ONCE on your Railway production database.
 *
 * Usage: Visit this file in browser or run via CLI: php migrate_password_reset.php
 */

require_once __DIR__ . '/includes/config.php';

// Prevent direct access from unauthorized users
if (php_sapi_name() === 'cli' || isset($_GET['secret']) && $_GET['secret'] === 'migrate123') {

    try {
        echo "Starting migration...\n\n";

        // Check if table already exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'password_reset_tokens'");
        $tableExists = $stmt->fetch();

        if ($tableExists) {
            echo "✅ Table 'password_reset_tokens' already exists.\n";
            echo "Migration skipped.\n";

            // Check if indexes exist
            $indexes = $pdo->query("SHOW INDEX FROM password_reset_tokens")->fetchAll();
            $indexNames = array_column($indexes, 'Key_name');

            $requiredIndexes = [
                'idx_password_reset_token',
                'idx_password_reset_emp_id',
                'idx_password_reset_expires'
            ];

            $missingIndexes = array_diff($requiredIndexes, $indexNames);

            if (empty($missingIndexes)) {
                echo "✅ All required indexes exist.\n";
            } else {
                echo "⚠️  Missing indexes: " . implode(', ', $missingIndexes) . "\n";
                echo "Creating missing indexes...\n";

                $sqlIndexes = [
                    "CREATE INDEX idx_password_reset_token ON password_reset_tokens(token)",
                    "CREATE INDEX idx_password_reset_emp_id ON password_reset_tokens(emp_id)",
                    "CREATE INDEX idx_password_reset_expires ON password_reset_tokens(expires_at)"
                ];

                foreach ($sqlIndexes as $sql) {
                    try {
                        $pdo->exec($sql);
                        echo "   ✅ Created: " . str_replace('CREATE INDEX ', '', $sql) . "\n";
                    } catch (PDOException $e) {
                        echo "   ❌ Failed: " . $e->getMessage() . "\n";
                    }
                }
            }

            exit(0);
        }

        // Create table
        echo "Creating table 'password_reset_tokens'...\n";

        $sql = "
        CREATE TABLE password_reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            emp_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (emp_id) REFERENCES employees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        $pdo->exec($sql);
        echo "   ✅ Table created successfully.\n";

        // Create indexes
        echo "Creating indexes...\n";

        $indexes = [
            "CREATE INDEX idx_password_reset_token ON password_reset_tokens(token)",
            "CREATE INDEX idx_password_reset_emp_id ON password_reset_tokens(emp_id)",
            "CREATE INDEX idx_password_reset_expires ON password_reset_tokens(expires_at)"
        ];

        foreach ($indexes as $sql) {
            $pdo->exec($sql);
            echo "   ✅ " . str_replace('CREATE INDEX ', '', $sql) . "\n";
        }

        echo "\n✅ Migration completed successfully!\n";
        echo "The password reset feature is now ready to use.\n";

        // Display stats
        $count = $pdo->query("SELECT COUNT(*) as cnt FROM password_reset_tokens")->fetch();
        echo "\n📊 Table stats: {$count['cnt']} rows\n";

    } catch (PDOException $e) {
        $env = getenv('RAILWAY_ENVIRONMENT') ? 'Production (Railway)' : 'Localhost';
        echo "\n❌ MIGRATION FAILED\n";
        echo "Environment: $env\n";
        echo "Error: " . $e->getMessage() . "\n";
        echo "SQLSTATE: " . $e->getCode() . "\n\n";
        echo "Troubleshooting:\n";
        echo "1. Check database connection credentials in config.php\n";
        echo "2. Ensure 'employees' table exists (required for foreign key)\n";
        echo "3. Check user has CREATE privileges\n";

        exit(1);
    }

} else {
    // Web access - show simple HTML page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
        <title>Database Migration</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: #f0f2f5;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
            }
            .container {
                background: white;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                padding: 40px;
                max-width: 600px;
                width: 100%;
            }
            h1 {
                color: #333;
                margin-bottom: 20px;
                font-size: 24px;
            }
            .status {
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                font-family: 'Courier New', monospace;
                white-space: pre-wrap;
                background: #1a1a2e;
                color: #00ff88;
                font-size: 14px;
                line-height: 1.6;
                max-height: 400px;
                overflow-y: auto;
            }
            .btn {
                background: linear-gradient(135deg, #5552DD, #7B7AFF);
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 8px;
                font-size: 16px;
                cursor: pointer;
                margin-bottom: 20px;
            }
            .btn:hover {
                opacity: 0.9;
            }
            .info {
                background: #e3f2fd;
                border-left: 4px solid #2196F3;
                padding: 15px;
                margin: 20px 0;
                font-size: 14px;
                line-height: 1.6;
            }
        </style>
    </head>
    <body>
    <div class="container">
        <h1>🗄️ Database Migration</h1>

        <div class="info">
            <strong>Purpose:</strong> This script creates the <code>password_reset_tokens</code> table
            required for the password reset feature. It's safe to run multiple times.
        </div>

        <button class="btn" onclick="runMigration()">🚀 Run Migration</button>

        <div id="output" class="status" style="display:none;"></div>

        <div class="info">
            <strong>What it does:</strong>
            <ul>
                <li>Checks if table already exists</li>
                <li>Creates table if missing</li>
                <li>Creates required indexes for performance</li>
                <li>Safe to run multiple times</li>
            </ul>
        </div>

        <div class="info">
            <strong>After migration:</strong><br>
            Test the password reset feature:
            <ol>
                <li>Visit <a href="<?= SITE_URL ?>/forgot_password.php">forgot_password.php</a></li>
                <li>Enter your email/employee ID</li>
                <li>Check email for reset link</li>
                <li>Set new password and login</li>
            </ol>
        </div>
    </div>

    <script>
        function runMigration() {
            const output = document.getElementById('output');
            output.style.display = 'block';
            output.textContent = 'Running migration...';

            fetch('?secret=migrate123')
                .then(res => res.text())
                .then(text => {
                    output.textContent = text;
                })
                .catch(err => {
                    output.textContent = '❌ Error: ' + err;
                    output.style.color = '#ff6b6b';
                });
        }
    </script>
    </body>
    </html>
    <?php
}
