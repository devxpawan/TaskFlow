<?php
/*
Run once: http://localhost/taskflow/includes/schema.php
Then DELETE this file.
*/
require_once 'db.php';

$queries = [
    // Categories (if not already created)
    "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        color VARCHAR(7) NOT NULL DEFAULT '#6366f1',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_category_per_user (user_id, name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Task-Category pivot
    "CREATE TABLE IF NOT EXISTS task_categories (
        task_id INT NOT NULL,
        category_id INT NOT NULL,
        PRIMARY KEY (task_id, category_id),
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Position column for task reordering
    "ALTER TABLE tasks ADD COLUMN IF NOT EXISTS position INT DEFAULT 0",

    // Recurrence column for tasks
    "ALTER TABLE tasks ADD COLUMN IF NOT EXISTS recurrence VARCHAR(20) DEFAULT 'none'",

    // Subtasks table
    "CREATE TABLE IF NOT EXISTS subtasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        completed TINYINT(1) DEFAULT 0,
        position INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

echo "<h2>TaskFlow Schema Setup</h2>";
foreach ($queries as $sql) {
    if ($conn->query($sql)) {
        echo "<p style='color:green;'>✓ OK</p>";
    } else {
        // "IF NOT EXISTS" on ALTER ADD COLUMN fails differently; ignore dup column errors
        if (strpos($conn->error, 'Duplicate column') !== false) {
            echo "<p style='color:orange;'>⚠ Already exists</p>";
        } else {
            echo "<p style='color:red;'>✗ " . $conn->error . "</p>";
        }
    }
}
echo "<p><strong>Done.</strong> Delete this file now.</p>";
$conn->close();
