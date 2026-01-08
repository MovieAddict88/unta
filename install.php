<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

$configFile = 'config.php';
$installLockFile = 'install.lock';
$errors = [];
$success_message = '';

// Check if installation is already complete
if (file_exists($installLockFile)) {
    die("Installation is already complete. Please delete 'install.lock' to reinstall. For security, it's also recommended to delete 'install.php'.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- 1. Get Form Data ---
    $db_host = trim($_POST['db_host']);
    $db_name = trim($_POST['db_name']);
    $db_user = trim($_POST['db_user']);
    $db_pass = trim($_POST['db_pass']);
    $admin_user = trim($_POST['admin_user']);
    $admin_pass = trim($_POST['admin_pass']);

    // --- Validation ---
    if (empty($db_host)) $errors[] = "Database host is required.";
    if (empty($db_name)) $errors[] = "Database name is required.";
    if (empty($db_user)) $errors[] = "Database user is required.";
    if (empty($admin_user)) $errors[] = "Admin username is required.";
    if (empty($admin_pass)) $errors[] = "Admin password is required.";

    if (empty($errors)) {
        // --- 2. Test Database Connection ---
        try {
            $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Create database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $pdo->exec("USE `$db_name`");

        } catch (PDOException $e) {
            $errors[] = "Database connection failed: " . $e->getMessage();
        }

        if (empty($errors)) {
            // --- 3. Write config.php ---
            try {
                $configTemplate = file_get_contents($configFile);
                if ($configTemplate === false) {
                    throw new Exception("Could not read config.php template.");
                }
                $configContent = str_replace(
                    ['{{DB_HOST}}', '{{DB_USERNAME}}', '{{DB_PASSWORD}}', '{{DB_NAME}}'],
                    [$db_host, $db_user, $db_pass, $db_name],
                    $configTemplate
                );
                file_put_contents($configFile, $configContent);

            } catch (Exception $e) {
                $errors[] = "Failed to write to config.php: " . $e->getMessage();
            }
        }

        if (empty($errors)) {
            // --- 4. Create Database Tables ---
            try {
                $sql = "
                CREATE TABLE IF NOT EXISTS `users` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `username` varchar(50) NOT NULL,
                  `password` varchar(255) NOT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `username` (`username`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS `genres` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `name` varchar(100) NOT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `name` (`name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS `content` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `tmdb_id` varchar(20) DEFAULT NULL,
                  `type` enum('movie','series','live') NOT NULL,
                  `title` varchar(255) NOT NULL,
                  `description` text,
                  `poster_url` varchar(255) DEFAULT NULL,
                  `thumbnail_url` varchar(255) DEFAULT NULL,
                  `release_year` int(4) DEFAULT NULL,
                  `rating` decimal(3,1) DEFAULT NULL,
                  `duration` varchar(50) DEFAULT NULL,
                  `parental_rating` varchar(50) DEFAULT NULL,
                  `trailer_url` varchar(255) DEFAULT NULL,
                  `trailer_type` varchar(50) DEFAULT NULL,
                  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                   PRIMARY KEY (`id`),
                   UNIQUE KEY `tmdb_id_type` (`tmdb_id`, `type`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS `content_genres` (
                  `content_id` int(11) NOT NULL,
                  `genre_id` int(11) NOT NULL,
                  PRIMARY KEY (`content_id`,`genre_id`),
                  KEY `genre_id` (`genre_id`),
                  CONSTRAINT `content_genres_ibfk_1` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE,
                  CONSTRAINT `content_genres_ibfk_2` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS `seasons` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `content_id` int(11) NOT NULL,
                  `season_number` int(11) NOT NULL,
                  `title` VARCHAR(255) NULL,
                  `poster_url` varchar(255) DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `content_season` (`content_id`,`season_number`),
                  CONSTRAINT `seasons_ibfk_1` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS `episodes` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `season_id` int(11) NOT NULL,
                  `episode_number` int(11) NOT NULL,
                  `title` varchar(255) NOT NULL,
                  `description` text,
                  `thumbnail_url` varchar(255) DEFAULT NULL,
                  `duration` varchar(50) DEFAULT NULL,
                  `trailer_url` varchar(255) DEFAULT NULL,
                  `trailer_type` varchar(50) DEFAULT NULL,
                   PRIMARY KEY (`id`),
                   UNIQUE KEY `season_episode` (`season_id`, `episode_number`),
                   CONSTRAINT `episodes_ibfk_1` FOREIGN KEY (`season_id`) REFERENCES `seasons` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS `servers` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `content_id` int(11) DEFAULT NULL,
                  `episode_id` int(11) DEFAULT NULL,
                  `name` varchar(100) NOT NULL,
                  `url` text NOT NULL,
                  `quality` varchar(50) DEFAULT NULL,
                  `drm` tinyint(1) NOT NULL DEFAULT '0',
                  `license_url` text,
                  PRIMARY KEY (`id`),
                  KEY `content_id` (`content_id`),
                  KEY `episode_id` (`episode_id`),
                  CONSTRAINT `servers_ibfk_1` FOREIGN KEY (`content_id`) REFERENCES `content` (`id`) ON DELETE CASCADE,
                  CONSTRAINT `servers_ibfk_2` FOREIGN KEY (`episode_id`) REFERENCES `episodes` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

                CREATE TABLE IF NOT EXISTS `settings` (
                  `setting_key` varchar(100) NOT NULL,
                  `setting_value` text,
                  PRIMARY KEY (`setting_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ";

                $pdo->exec($sql);

                // Insert default settings
                $servers = [
                    ["url" => "https://vidsrc.net/embed", "enabled" => true],
                    ["url" => "https://vidjoy.pro/embed", "enabled" => true],
                    ["url" => "https://multiembed.mov/directstream.php", "enabled" => true],
                    ["url" => "https://embed.su/embed", "enabled" => true],
                    ["url" => "https://vidsrc.me/embed", "enabled" => true],
                    ["url" => "https://player.autoembed.cc/embed", "enabled" => true],
                    ["url" => "https://vidsrc.win", "enabled" => true],
                    ["url" => "https://vidsrc.to/embed", "enabled" => true],
                    ["url" => "https://vidsrc.xyz/embed", "enabled" => true],
                    ["url" => "https://www.embedsoap.com/embed", "enabled" => true],
                    ["url" => "https://moviesapi.club/movie", "enabled" => true],
                    ["url" => "https://dbgo.fun/movie", "enabled" => true],
                    ["url" => "https://flixhq.to/watch", "enabled" => true],
                    ["url" => "https://gomovies.sx/watch", "enabled" => true],
                    ["url" => "https://www.showbox.media/embed", "enabled" => true],
                    ["url" => "https://primewire.mx/embed", "enabled" => true],
                    ["url" => "https://hdtoday.tv/embed", "enabled" => true],
                    ["url" => "https://vidcloud.to/embed", "enabled" => true],
                    ["url" => "https://streamwish.to/e", "enabled" => true],
                    ["url" => "https://doodstream.com/e", "enabled" => true],
                    ["url" => "https://player.vidplus.to/embed", "enabled" => true],
                    ["url" => "https://www.2embed.stream/embed", "enabled" => true],
                    ["url" => "https://player.videasy.net", "enabled" => true],
                    ["url" => "https://vidfast.pro", "enabled" => true],
                    ["url" => "https://godriveplayer.com/embed", "enabled" => true],
                    ["url" => "https://2embed.cc/embed", "enabled" => true],
                    ["url" => "https://vidlink.pro", "enabled" => true]
                ];
                $servers_json = json_encode($servers);
                $stmt = $pdo->prepare("INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES ('auto_embed_servers', ?) ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`)");
                $stmt->execute([$servers_json]);

            } catch (PDOException $e) {
                $errors[] = "Table creation failed: " . $e->getMessage();
            }
        }

        if (empty($errors)) {
            // --- 5. Create Admin User ---
            try {
                $hashed_password = password_hash($admin_pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $stmt->execute([$admin_user, $hashed_password]);
            } catch (PDOException $e) {
                $errors[] = "Failed to create admin user: " . $e->getMessage();
            }
        }

        if (empty($errors)) {
            // --- 6. Finalize Installation ---
            file_put_contents($installLockFile, 'Installation completed on ' . date('Y-m-d H:i:s'));
            $success_message = "Installation successful! For security, please delete this 'install.php' file now.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineCraze Installation</title>
    <style>
        :root {
            --primary: #e50914;
            --background: #141414;
            --surface: #1f1f1f;
            --text: #ffffff;
            --text-secondary: #b3b3b3;
            --success: #46d369;
            --danger: #f40612;
            --border-radius: 8px;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--background);
            color: var(--text);
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .installer-container {
            background-color: var(--surface);
            padding: 40px;
            border-radius: var(--border-radius);
            width: 100%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        h1 {
            color: var(--primary);
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px;
            background-color: var(--background);
            border: 1px solid #333;
            border-radius: var(--border-radius);
            color: var(--text);
            font-size: 16px;
            box-sizing: border-box;
        }
        input:focus {
            outline: none;
            border-color: var(--primary);
        }
        .btn {
            width: 100%;
            padding: 15px;
            background-color: var(--primary);
            border: none;
            border-radius: var(--border-radius);
            color: var(--text);
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #b20710;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            text-align: center;
        }
        .message.error {
            background-color: rgba(244, 6, 18, 0.2);
            color: var(--danger);
            border: 1px solid var(--danger);
        }
        .message.success {
            background-color: rgba(70, 211, 105, 0.2);
            color: var(--success);
            border: 1px solid var(--success);
        }
        .message a {
            color: var(--success);
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="installer-container">
        <h1>CineCraze Installation</h1>

        <?php if (!empty($errors)): ?>
            <div class="message error">
                <strong>Installation Failed!</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="message success">
                <?php echo htmlspecialchars($success_message); ?><br><br>
                <a href="admin/login.php">Go to Admin Login</a>
            </div>
        <?php else: ?>
            <form action="install.php" method="POST">
                <fieldset>
                    <legend><h3>Database Details</h3></legend>
                    <div class="form-group">
                        <label for="db_host">Database Host</label>
                        <input type="text" id="db_host" name="db_host" value="localhost" required>
                    </div>
                    <div class="form-group">
                        <label for="db_name">Database Name</label>
                        <input type="text" id="db_name" name="db_name" required>
                    </div>
                    <div class="form-group">
                        <label for="db_user">Database Username</label>
                        <input type="text" id="db_user" name="db_user" required>
                    </div>
                    <div class="form-group">
                        <label for="db_pass">Database Password</label>
                        <input type="password" id="db_pass" name="db_pass">
                    </div>
                </fieldset>

                <fieldset>
                    <legend><h3>Admin Account</h3></legend>
                    <div class="form-group">
                        <label for="admin_user">Admin Username</label>
                        <input type="text" id="admin_user" name="admin_user" required>
                    </div>
                    <div class="form-group">
                        <label for="admin_pass">Admin Password</label>
                        <input type="password" id="admin_pass" name="admin_pass" required>
                    </div>
                </fieldset>

                <button type="submit" class="btn">Install Now</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
