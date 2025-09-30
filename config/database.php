<?php
$host = 'localhost';
$dbname = 'healthpatient';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create tables if they don't exist
    $tables = [
        "CREATE TABLE IF NOT EXISTS admin (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS sitio1_staff (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            position VARCHAR(100),
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES admin(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS sitio1_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            age INT,
            address TEXT,
            contact VARCHAR(20),
            approved BOOLEAN DEFAULT FALSE,
            approved_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (approved_by) REFERENCES sitio1_staff(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS sitio1_patients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            full_name VARCHAR(100) NOT NULL,
            age INT,
            address TEXT,
            disease VARCHAR(255),
            contact VARCHAR(20),
            last_checkup DATE,
            medical_history TEXT,
            added_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES sitio1_users(id),
            FOREIGN KEY (added_by) REFERENCES sitio1_staff(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS sitio1_consultations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            question TEXT NOT NULL,
            response TEXT,
            responded_by INT,
            is_custom BOOLEAN DEFAULT FALSE,
            status ENUM('pending', 'responded') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            responded_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES sitio1_users(id),
            FOREIGN KEY (responded_by) REFERENCES sitio1_staff(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS sitio1_appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            staff_id INT NOT NULL,
            date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            max_slots INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (staff_id) REFERENCES sitio1_staff(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS user_appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            appointment_id INT NOT NULL,
            status ENUM('pending', 'approved', 'completed', 'rejected') DEFAULT 'pending',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES sitio1_users(id),
            FOREIGN KEY (appointment_id) REFERENCES sitio1_appointments(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS sitio1_announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            staff_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            post_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (staff_id) REFERENCES sitio1_staff(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS user_announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            announcement_id INT NOT NULL,
            status ENUM('accepted', 'dismissed') NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES sitio1_users(id),
            FOREIGN KEY (announcement_id) REFERENCES sitio1_announcements(id),
            UNIQUE KEY unique_user_announcement (user_id, announcement_id)
        )"
    ];
    
    foreach ($tables as $table) {
        $pdo->exec($table);
    }
    
    // Insert default admin if not exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO admin (username, password, full_name) VALUES ('admin', '$hashedPassword', 'System Administrator')");
    }
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>