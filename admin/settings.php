<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once '../config.php';

$pdo = connect_db();
if (!$pdo) {
    die("Database connection failed. Please check your configuration.");
}

// Fetch initial server list
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'auto_embed_servers'");
$stmt->execute();
$result = $stmt->fetchColumn();
$servers = $result ? json_decode($result, true) : [];

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
    <title>Settings - CineCraze Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                    <a href="settings.php" class="nav-item active" aria-label="Settings">
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
                    <a href="change_password.php" class="icon-btn" title="Change Password" aria-label="Change Password"><i class="fas fa-key"></i></a>
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
                    <h1>Settings</h1>
                    <div class="subtitle">Configure your admin panel and auto-embed servers</div>
                </div>
                <div class="header-actions">
                    <a href="index.php" class="icon-btn" title="Back to Dashboard" aria-label="Back to Dashboard"><i class="fas fa-home"></i></a>
                    <a href="logout.php" class="icon-btn" title="Logout" aria-label="Logout"><i class="fas fa-right-from-bracket"></i></a>
                </div>
            </header>

            <div class="content-wrapper">
                <div id="status-container"></div>

        <div class="card">
            <h2><i class="fas fa-server"></i> Auto-Embed Server Management</h2>
            <p>Add and manage the base URLs for your video servers. Enable or disable servers that will be used to automatically generate embed links for new content.</p>

            <form id="add-server-form" class="form-inline">
                <div class="form-group">
                    <label for="server-url">New Server URL</label>
                    <input type="text" id="server-url" placeholder="e.g., https://vidsrc.to/embed" required>
                </div>
                <button type="submit" class="btn btn-primary">Add Server</button>
            </form>

            <h3 style="margin-top: 30px;">Configured Servers</h3>
            <ul id="server-list" class="styled-list">
                <?php if (empty($servers)): ?>
                    <li id="no-servers-message">No servers configured yet.</li>
                <?php else: ?>
                    <?php foreach ($servers as $server): ?>
                        <li data-url="<?php echo htmlspecialchars($server['url']); ?>">
                            <label class="switch">
                                <input type="checkbox" <?php echo $server['enabled'] ? 'checked' : ''; ?>>
                                <span class="slider round"></span>
                            </label>
                            <span><?php echo htmlspecialchars($server['url']); ?></span>
                            <button class="btn btn-danger btn-small delete-server-btn">Delete</button>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
            <div style="margin-top: 20px;">
                <button id="save-settings-btn" class="btn btn-primary">Save Settings</button>
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

    <script>
        // --- Add Server ---
        document.getElementById('add-server-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const serverUrlInput = document.getElementById('server-url');
            const url = serverUrlInput.value.trim();
            if (!url) return;

            const formData = new FormData();
            formData.append('url', url);

            try {
                const response = await fetch('settings_api.php?action=add_server', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    showStatus('success', result.message);
                    addServerToList({ url: url, enabled: true });
                    serverUrlInput.value = '';
                } else {
                    showStatus('error', result.message || 'An unknown error occurred.');
                }
            } catch (error) {
                showStatus('error', 'Request failed: ' + error.toString());
            }
        });

        // --- Delete Server ---
        document.getElementById('server-list').addEventListener('click', async function(e) {
            if (e.target.classList.contains('delete-server-btn')) {
                const listItem = e.target.closest('li');
                const url = listItem.dataset.url;
                if (!confirm(`Are you sure you want to delete the server: ${url}?`)) return;

                const formData = new FormData();
                formData.append('url', url);

                try {
                    const response = await fetch('settings_api.php?action=delete_server', { method: 'POST', body: formData });
                    const result = await response.json();
                    if (result.success) {
                        showStatus('success', result.message);
                        listItem.remove();
                        if (document.getElementById('server-list').children.length === 0) {
                             document.getElementById('server-list').innerHTML = '<li id="no-servers-message">No servers configured yet.</li>';
                        }
                    } else {
                        showStatus('error', result.message || 'An unknown error occurred.');
                    }
                } catch (error) {
                    showStatus('error', 'Request failed: ' + error.toString());
                }
            }
        });

        // --- Save All Settings ---
        document.getElementById('save-settings-btn').addEventListener('click', async function() {
            const serverItems = document.querySelectorAll('#server-list li');
            const servers = [];
            serverItems.forEach(item => {
                const url = item.dataset.url;
                const enabled = item.querySelector('input[type="checkbox"]').checked;
                servers.push({ url, enabled });
            });

            const formData = new FormData();
            formData.append('servers', JSON.stringify(servers));

            try {
                const response = await fetch('settings_api.php?action=update_servers', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    showStatus('success', result.message);
                } else {
                    showStatus('error', result.message || 'An unknown error occurred.');
                }
            } catch (error) {
                showStatus('error', 'Request failed: ' + error.toString());
            }
        });

        function addServerToList(server) {
            const noServersMessage = document.getElementById('no-servers-message');
            if (noServersMessage) noServersMessage.remove();

            const listItem = document.createElement('li');
            listItem.dataset.url = server.url;
            listItem.innerHTML = `
                <label class="switch">
                    <input type="checkbox" ${server.enabled ? 'checked' : ''}>
                    <span class="slider round"></span>
                </label>
                <span>${escapeHTML(server.url)}</span>
                <button class="btn btn-danger btn-small delete-server-btn">Delete</button>
            `;
            document.getElementById('server-list').appendChild(listItem);
        }

        function showStatus(type, message) {
            const statusContainer = document.getElementById('status-container');
            statusContainer.innerHTML = `<div class="status ${type}">${escapeHTML(message)}</div>`;
            setTimeout(() => { statusContainer.innerHTML = ''; }, 4000);
        }

        function escapeHTML(str) {
            const p = document.createElement('p');
            p.appendChild(document.createTextNode(str));
            return p.innerHTML;
        }
    </script>
</body>
</html>
