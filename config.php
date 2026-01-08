<?php

// --- Database Configuration ---
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'cinecraze');

/**
 * Establishes a database connection using PDO.
 * @return PDO|null Returns a PDO object on success, or null on failure.
 */
function connect_db() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        return new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
    } catch (PDOException $e) {
        // In a real application, you would log this error instead of echoing it.
        // For this project, returning null and letting the API handle the JSON response is sufficient.
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}
