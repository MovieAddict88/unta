<?php
session_start();
require_once '../config.php';

// If user is already logged in, redirect to admin dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } else {
        try {
            $pdo = connect_db();
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Password is correct, start session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    header("Location: index.php"); // Redirect to a new admin dashboard page
                    exit;
                } else {
                    $error = 'Invalid username or password.';
                }
            } else {
                $error = 'Database is not configured. Please run the installer.';
            }
        } catch (Exception $e) {
            $error = 'An error occurred: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - CineCraze</title>
    <style>
        :root {
            --primary: #e50914;
            --background: #141414;
            --surface: #1f1f1f;
            --text: #ffffff;
            --danger: #f40612;
            --border-radius: 10px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--background);
            color: var(--text);
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: clamp(16px, 4vw, 40px);
            font-size: clamp(14px, 1.2vw, 18px);
        }

        .login-container {
            background-color: var(--surface);
            padding: clamp(20px, 4vw, 44px);
            border-radius: var(--border-radius);
            width: 100%;
            max-width: min(420px, 100%);
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        h1 {
            color: var(--primary);
            text-align: center;
            margin-bottom: clamp(18px, 3vw, 30px);
            font-size: clamp(1.4rem, 2vw, 1.9rem);
        }

        .form-group {
            margin-bottom: clamp(14px, 2vw, 20px);
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: clamp(10px, 1.4vw, 14px);
            background-color: var(--background);
            border: 1px solid #333;
            border-radius: var(--border-radius);
            color: var(--text);
            font-size: clamp(14px, 1.1vw, 16px);
            box-sizing: border-box;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn {
            width: 100%;
            padding: clamp(12px, 1.6vw, 16px);
            background-color: var(--primary);
            border: none;
            border-radius: var(--border-radius);
            color: var(--text);
            font-size: clamp(16px, 1.3vw, 18px);
            font-weight: 800;
            cursor: pointer;
            transition: background-color 0.2s;
            min-height: 44px;
        }

        .btn:hover {
            background-color: #b20710;
        }

        .error-message {
            background-color: rgba(244, 6, 18, 0.2);
            color: var(--danger);
            border: 1px solid var(--danger);
            padding: clamp(12px, 1.6vw, 16px);
            margin-bottom: clamp(14px, 2vw, 20px);
            border-radius: var(--border-radius);
            text-align: center;
        }

        @media (max-width: 360px) {
            .login-container {
                padding: 18px;
            }
        }

        @media (min-width: 1600px) {
            .login-container {
                max-width: 520px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Admin Login</h1>
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">Log In</button>
        </form>
    </div>
</body>
</html>
