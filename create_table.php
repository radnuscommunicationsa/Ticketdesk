require_once 'includes/config.php';

  try {
      echo "<h2>Creating password_reset_tokens table...</h2>";        

      $sql = "CREATE TABLE IF NOT EXISTS password_reset_tokens (      
          id INT AUTO_INCREMENT PRIMARY KEY,
          emp_id INT NOT NULL,
          token VARCHAR(64) NOT NULL UNIQUE,
          expires_at DATETIME NOT NULL,
          used TINYINT(1) DEFAULT 0,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (emp_id) REFERENCES employees(id) ON DELETE     
  CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci";

      $pdo->exec($sql);

      echo "<p style='color: green; font-weight: bold;'>✅ Table      
  created successfully!</p>";

      // Show the table structure
      $stmt = $pdo->query("DESCRIBE password_reset_tokens");
      echo "<h3>Table Structure:</h3>";
      echo "<table border='1' style='border-collapse: collapse;'>";   
      echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><t
  h>Default</th><th>Extra</th></tr>";
      while ($row = $stmt->fetch()) {
          echo "<tr>";
          echo "<td>{$row['Field']}</td>";
          echo "<td>{$row['Type']}</td>";
          echo "<td>{$row['Null']}</td>";
          echo "<td>{$row['Key']}</td>";
          echo "<td>{$row['Default']}</td>";
          echo "<td>{$row['Extra']}</td>";
          echo "</tr>";
      }
      echo "</table>";

      // Count records
      $stmt = $pdo->query("SELECT COUNT(*) as total FROM
  password_reset_tokens");
      $count = $stmt->fetchColumn();
      echo "<p>Current records in table: <strong>$count</strong></p>";

  } catch (PDOException $e) {
      echo "<h2 style='color: red;'>❌ ERROR:</h2>";
      echo "<p style='color: red; font-weight: bold;'>" .
  $e->getMessage() . "</p>";
      echo "<pre>SQL was:<br>$sql</pre>";
  }
  ?>