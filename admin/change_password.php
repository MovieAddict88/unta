<?php
session_start();
require_once '../config.php';

// If the user is not logged in, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $user_id = $_SESSION['user_id'];

    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } else {
        try {
            $pdo = connect_db();
            if ($pdo) {
                // Get current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                if ($user && password_verify($old_password, $user['password'])) {
                    // Old password is correct, update to new password
                    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $update_stmt->execute([$new_hashed_password, $user_id]);
                    $message = "Password changed successfully.";
                } else {
                    $error = "Incorrect old password.";
                }
            } else {
                $error = 'Database connection failed.';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#e50914">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title>Change Password - CineCraze Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #e50914;
            --background: #141414;
            --surface: #1f1f1f;
            --text: #ffffff;
            --text-secondary: #b3b3b3;
            --success: #46d369;
            --danger: #f40612;
            --border-radius: 10px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--background);
            color: var(--text);
            margin: 0;
            padding: clamp(12px, 3vw, 28px);
            font-size: clamp(14px, 1.2vw, 18px);
        }

        .container {
            max-width: min(620px, 100%);
            margin: clamp(18px, 4vw, 60px) auto;
            padding: clamp(18px, 4vw, 44px);
            background-color: var(--surface);
            border-radius: var(--border-radius);
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
            min-height: 44px;
        }

        .message {
            padding: clamp(12px, 1.6vw, 16px);
            margin-bottom: clamp(14px, 2vw, 20px);
            border-radius: var(--border-radius);
            text-align: center;
        }

        .message.error {
            background-color: rgba(244, 6, 18, 0.2);
            color: var(--danger);
        }

        .message.success {
            background-color: rgba(70, 211, 105, 0.2);
            color: var(--success);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: clamp(14px, 2vw, 20px);
            color: var(--text-secondary);
        }

        @media (max-width: 420px) {
            .container {
                margin: 16px auto;
            }
        }

        @media (min-width: 1600px) {
            .container {
                max-width: 760px;
            }
        }
        .password-container {
            max-width: min(620px, 100%);
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <div class="sidebar-overlay" id="sidebar-overlay"></div>
        
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="sidebar-logo">
                    <i class="fas fa-clapperboard"></i>
                    <span>CineCraze Admin</span>
                </a>
            </div>

            <nav class="sidebar-nav" role="navigation" aria-label="Admin navigation">
                <div class="nav-section">
                    <div class="nav-section-title">Content Management</div>
                    <a href="index.php#tmdb-generator" class="nav-item" aria-label="TMDB Generator">
                        <i class="fas fa-film"></i>
                        <span class="nav-label">TMDB Generator</span>
                    </a>
                    <a href="index.php#data-management" class="nav-item" aria-label="Data Management">
                        <i class="fas fa-database"></i>
                        <span class="nav-label">Data Management</span>
                    </a>
                    <a href="index.php#manual-input" class="nav-item" aria-label="Manual Input">
                        <i class="fas fa-pen"></i>
                        <span class="nav-label">Manual Input</span>
                    </a>
                    <a href="index.php#bulk-operations" class="nav-item" aria-label="Bulk Operations">
                        <i class="fas fa-box"></i>
                        <span class="nav-label">Bulk Operations</span>
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="settings.php" class="nav-item" aria-label="Settings">
                        <i class="fas fa-gear"></i>
                        <span class="nav-label">Settings</span>
                    </a>
                </div>
            </nav>

            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
                <div class="user-actions">
                    <a href="change_password.php" class="icon-btn active" title="Change Password" aria-label="Change Password"><i class="fas fa-key"></i></a>
                    <a href="settings.php" class="icon-btn" title="Settings" aria-label="Settings"><i class="fas fa-gear"></i></a>
                    <a href="logout.php" class="icon-btn" title="Logout" aria-label="Logout"><i class="fas fa-right-from-bracket"></i></a>
                </div>
            </div>
        </aside>

        <main class="main-content">
            <header class="top-header">
                <button class="mobile-menu-toggle" id="mobile-menu-toggle" aria-label="Toggle menu">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="page-title">
                    <h1>Change Password</h1>
                    <div class="subtitle">Update your account password</div>
                </div>
                <div class="header-actions">
                    <a href="index.php" class="icon-btn" title="Back to Dashboard" aria-label="Back to Dashboard"><i class="fas fa-home"></i></a>
                    <a href="logout.php" class="icon-btn" title="Logout" aria-label="Logout"><i class="fas fa-right-from-bracket"></i></a>
                </div>
            </header>

            <div class="content-wrapper">
                <div class="password-container">
                    <div class="card">
                        <h2><i class="fas fa-key"></i> Change Password</h2>
                        <?php if ($error): ?>
                            <div class="status error"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($message): ?>
                            <div class="status success"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>
                        <form action="change_password.php" method="POST">
                            <div class="form-group">
                                <label for="old_password">Old Password</label>
                                <input type="password" id="old_password" name="old_password" required>
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" required>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Change Password</button>
                            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const sidebarOverlay = document.getElementById('sidebar-overlay');

            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', toggleSidebar);
            }

            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeSidebar);
            }
        });
    </script>
</body>
</html>
