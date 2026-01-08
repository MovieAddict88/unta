<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once '../config.php';
require_once 'tmdb_handler.php';

$contentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

if (isset($_SESSION['status_message'])) {
    $message = $_SESSION['status_message'];
    unset($_SESSION['status_message']);
}
if ($contentId === 0) die("Invalid content ID.");
$pdo = connect_db();
if (!$pdo) die("Database connection failed.");

$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'auto_embed_servers'");
$stmt->execute();
$all_configured_servers_json = $stmt->fetchColumn();
$all_configured_servers = $all_configured_servers_json ? json_decode($all_configured_servers_json, true) : [];
$enabled_servers = array_filter($all_configured_servers, function($server){ return !empty($server['enabled']); });
$enabled_server_urls = array_column($enabled_servers, 'url');

function parse_server_url($url, $all_servers) {
    if (empty($all_servers) || !is_array($all_servers)) return ['base' => 'custom', 'path' => $url];
    $all_server_urls = array_column($all_servers, 'url');
    foreach ($all_server_urls as $server_base) {
        if (strpos($url, $server_base) === 0) return ['base' => $server_base, 'path' => substr($url, strlen($server_base))];
    }
    return ['base' => 'custom', 'path' => $url];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? 'save';
    try {
        $pdo->beginTransaction();
        if ($action === 'save') {
            $stmt = $pdo->prepare("UPDATE content SET title = ?, description = ?, release_year = ?, poster_url = ? WHERE id = ?");
            $stmt->execute([$_POST['title'], $_POST['description'], $_POST['release_year'], $_POST['poster_url'], $contentId]);

            $process_servers = function($submitted_servers, $content_id = null, $episode_id = null) use ($pdo) {
                $id_col = $episode_id ? 'episode_id' : 'content_id';
                $id_val = $episode_id ?: $content_id;

                $deleteStmt = $pdo->prepare("DELETE FROM servers WHERE {$id_col} = ?");
                $deleteStmt->execute([$id_val]);

                if (empty($submitted_servers)) return;

                $insertStmt = $pdo->prepare("INSERT INTO servers (content_id, episode_id, name, url, drm, license_url) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($submitted_servers as $server) {
                    if (empty($server['path'])) continue;
                    $final_url = ($server['base'] === 'custom') ? $server['path'] : $server['base'] . $server['path'];
                    $name = parse_url($final_url, PHP_URL_HOST) ?? 'Custom Server';
                    $drm = isset($server['drm']) && $server['drm'] == '1';
                    $license_url = $drm ? ($server['license_url'] ?? null) : null;

                    $insertStmt->execute([$content_id, $episode_id, $name, $final_url, $drm, $license_url]);
                }
            };
            if (isset($_POST['servers'])) $process_servers($_POST['servers'], $contentId, null);
            if (isset($_POST['seasons'])) {
                foreach ($_POST['seasons'] as $season_id => $season_data) {
                     foreach ($season_data['episodes'] as $episode_id => $episode_data) {
                        if (isset($episode_data['servers'])) $process_servers($episode_data['servers'], null, $episode_id);
                     }
                }
            }
            $pdo->commit();
            $message = "Content updated successfully!";

        } elseif ($action === 'apply_servers') {
            // Logic for applying servers remains the same, as it doesn't set DRM fields.
            $content_info = $pdo->query("SELECT type, tmdb_id FROM content WHERE id = $contentId")->fetch();
            $links_added = 0;
            if ($content_info['type'] === 'movie') {
                $existing_urls = $pdo->query("SELECT url FROM servers WHERE content_id = $contentId")->fetchAll(PDO::FETCH_COLUMN, 0);
                foreach ($enabled_servers as $server) {
                    $expected_url = generate_final_url($server['url'], 'movie', $content_info['tmdb_id']);
                    if (!in_array($expected_url, $existing_urls)) {
                        $name = parse_url($server['url'], PHP_URL_HOST);
                        $pdo->prepare("INSERT INTO servers (content_id, name, url) VALUES (?, ?, ?)")->execute([$contentId, $name, $expected_url]);
                        $links_added++;
                    }
                }
            } elseif ($content_info['type'] === 'series') {
                $seasons = $pdo->query("SELECT id, season_number FROM seasons WHERE content_id = $contentId")->fetchAll();
                foreach($seasons as $season) {
                    $episodes = $pdo->query("SELECT id, episode_number FROM episodes WHERE season_id = {$season['id']}")->fetchAll();
                    foreach($episodes as $episode) {
                        $existing_urls = $pdo->query("SELECT url FROM servers WHERE episode_id = {$episode['id']}")->fetchAll(PDO::FETCH_COLUMN, 0);
                        foreach ($enabled_servers as $server) {
                            $expected_url = generate_final_url($server['url'], 'tv', $content_info['tmdb_id'], $season['season_number'], $episode['episode_number']);
                            if (!in_array($expected_url, $existing_urls)) {
                                $name = parse_url($server['url'], PHP_URL_HOST);
                                $pdo->prepare("INSERT INTO servers (episode_id, name, url) VALUES (?, ?, ?)")->execute([$episode['id'], $name, $expected_url]);
                                $links_added++;
                            }
                        }
                    }
                }
            }
            $pdo->commit();
            $_SESSION['status_message'] = "Operation successful. Added {$links_added} new server link(s) from enabled servers.";
            header("Location: edit_content.php?id=$contentId");
            exit;
        }
    } catch (Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        $error = "An error occurred: " . $e->getMessage();
    }
}

$stmt = $pdo->prepare("SELECT * FROM content WHERE id = ?"); $stmt->execute([$contentId]);
$content = $stmt->fetch();
if (!$content) die("Content not found.");
if ($content['type'] === 'series') {
    $content['seasons'] = $pdo->query("SELECT * FROM seasons WHERE content_id = $contentId ORDER BY season_number")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($content['seasons'] as &$season) {
        $season['episodes'] = $pdo->query("SELECT * FROM episodes WHERE season_id = {$season['id']} ORDER BY episode_number")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($season['episodes'] as &$episode) {
            $episode['servers'] = $pdo->query("SELECT * FROM servers WHERE episode_id = {$episode['id']}")->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} else {
    $content['servers'] = $pdo->query("SELECT * FROM servers WHERE content_id = $contentId")->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Edit Content - <?php echo htmlspecialchars($content['title']); ?></title>
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
                <a href="index.php#data-management" class="nav-item active" aria-label="Data Management">
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
                <h1>Edit Content</h1>
                <div class="subtitle"><?php echo htmlspecialchars($content['title']); ?></div>
            </div>
            <div class="header-actions">
                <a href="index.php#data-management" class="icon-btn" title="Back to Dashboard" aria-label="Back to Dashboard"><i class="fas fa-arrow-left"></i></a>
                <a href="logout.php" class="icon-btn" title="Logout" aria-label="Logout"><i class="fas fa-right-from-bracket"></i></a>
            </div>
        </header>

        <div class="content-wrapper">
            <?php if ($message): ?><div class="status success" id="status-message"><?php echo $message; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="status error" id="status-message"><?php echo $error; ?></div><?php endif; ?>
            
            <div class="card">
        <form action="edit_content.php?id=<?php echo $contentId; ?>&action=save" method="POST">
            <div class="form-group"><label>Title</label><input type="text" name="title" value="<?php echo htmlspecialchars($content['title']); ?>"></div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="5"><?php echo htmlspecialchars($content['description']); ?></textarea></div>
            <div class="grid grid-2">
                <div class="form-group"><label>Year</label><input type="number" name="release_year" value="<?php echo htmlspecialchars($content['release_year']); ?>"></div>
                <div class="form-group"><label>Poster URL</label><input type="url" name="poster_url" value="<?php echo htmlspecialchars($content['poster_url']); ?>"></div>
            </div>
            <?php
            function render_server_inputs($servers, $name_prefix, $all_servers, $enabled_server_urls, $contentId) {
                echo "<div class='servers-header'><h3><i class='fas fa-link'></i> Servers</h3><button type='submit' formaction='edit_content.php?id={$contentId}&action=apply_servers' class='btn btn-secondary btn-small'>Apply Configured Servers</button></div>";
                echo "<div class='servers-container' id='servers-{$name_prefix}'>";
                if (empty($servers)) echo "<p>No servers attached.</p>";
                foreach ($servers as $server) {
                    $parsed = parse_server_url($server['url'], $all_servers);
                    $id = $server['id'];
                    $drm_checked = !empty($server['drm']) ? 'checked' : '';
                    echo "<div class='server-item' id='server-item-{$id}'>";
                    echo "<div class='server-main-controls'><select name='{$name_prefix}[{$id}][base]'>";
                    foreach ($enabled_server_urls as $base_url) {
                        $selected = ($parsed['base'] === $base_url) ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($base_url) . "' {$selected}>" . htmlspecialchars(parse_url($base_url, PHP_URL_HOST)) . "</option>";
                    }
                    $custom_selected = ($parsed['base'] === 'custom' || !in_array($parsed['base'], $enabled_server_urls)) ? 'selected' : '';
                    echo "<option value='custom' {$custom_selected}>Custom URL</option>";
                    echo "</select><input type='text' name='{$name_prefix}[{$id}][path]' value='" . htmlspecialchars($parsed['path']) . "' placeholder='Video ID/Path or Full URL'><button type='button' class='btn btn-danger btn-small' onclick='this.closest(\".server-item\").remove()'>Remove</button></div>";
                    echo "<div class='server-extra-controls'><label class='drm-label'><input type='checkbox' name='{$name_prefix}[{$id}][drm]' value='1' {$drm_checked}> DRM</label><input type='text' name='{$name_prefix}[{$id}][license_url]' value='" . htmlspecialchars($server['license_url'] ?? '') . "' placeholder='License URL (if DRM enabled)'></div>";
                    echo "</div>";
                }
                echo "</div>";
                echo "<button type='button' class='btn btn-secondary btn-small' onclick='addServer(\"servers-{$name_prefix}\", \"{$name_prefix}\")'>+ Add Server</button>";
            }
            ?>
            <?php if ($content['type'] !== 'series'): ?>
                <?php render_server_inputs($content['servers'], 'servers', $all_configured_servers, $enabled_server_urls, $contentId); ?>
            <?php else: ?>
            <div class="form-group">
                 <div class='servers-header'><h3><i class="fas fa-list-ul"></i> Seasons & Episodes</h3><button type='submit' formaction='edit_content.php?id=<?php echo $contentId; ?>&action=apply_servers' class='btn btn-secondary btn-small'>Apply Configured Servers to All Episodes</button></div>
                <?php foreach ($content['seasons'] as $season): ?>
                <div class="season-group"><h4>Season <?php echo $season['season_number']; ?></h4>
                    <?php foreach ($season['episodes'] as $episode): ?>
                    <div class="episode-group"><h5>Episode <?php echo $episode['episode_number']; ?>: <?php echo htmlspecialchars($episode['title']); ?></h5>
                        <?php
                        $name_prefix = "seasons[{$season['id']}][episodes][{$episode['id']}][servers]";
                        echo "<div class='servers-container' id='servers-{$name_prefix}'>";
                        if (empty($episode['servers'])) echo "<p>No servers attached.</p>";
                        foreach ($episode['servers'] as $server) {
                            $parsed = parse_server_url($server['url'], $all_configured_servers);
                            $id = $server['id'];
                            $drm_checked = !empty($server['drm']) ? 'checked' : '';
                            echo "<div class='server-item' id='server-item-{$id}'>";
                            echo "<div class='server-main-controls'><select name='{$name_prefix}[{$id}][base]'>";
                            foreach ($enabled_server_urls as $base_url) {
                                $selected = ($parsed['base'] === $base_url) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($base_url) . "' {$selected}>" . htmlspecialchars(parse_url($base_url, PHP_URL_HOST)) . "</option>";
                            }
                            $custom_selected = ($parsed['base'] === 'custom' || !in_array($parsed['base'], $enabled_server_urls)) ? 'selected' : '';
                            echo "<option value='custom' {$custom_selected}>Custom URL</option>";
                            echo "</select><input type='text' name='{$name_prefix}[{$id}][path]' value='" . htmlspecialchars($parsed['path']) . "' placeholder='Video ID/Path or Full URL'><button type='button' class='btn btn-danger btn-small' onclick='this.closest(\".server-item\").remove()'>Remove</button></div>";
                            echo "<div class='server-extra-controls'><label class='drm-label'><input type='checkbox' name='{$name_prefix}[{$id}][drm]' value='1' {$drm_checked}> DRM</label><input type='text' name='{$name_prefix}[{$id}][license_url]' value='" . htmlspecialchars($server['license_url'] ?? '') . "' placeholder='License URL (if DRM enabled)'></div>";
                            echo "</div>";
                        }
                        echo "</div>";
                        echo "<button type='button' class='btn btn-secondary btn-small' onclick='addServer(\"servers-{$name_prefix}\", \"{$name_prefix}\")'>+ Add Server</button>";
                        ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary" style="margin-top: 20px;">Save All Changes</button>
        </form>
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

const enabledServers = <?php echo json_encode($enabled_server_urls); ?>;
function addServer(containerId, namePrefix) {
    const container = document.getElementById(containerId);
    const newItem = document.createElement('div');
    newItem.className = 'server-item';
    const newIndex = 'new_' + Date.now();
    let optionsHtml = enabledServers.map(url => `<option value="${escapeHTML(url)}">${escapeHTML(new URL(url).hostname)}</option>`).join('');
    optionsHtml += `<option value="custom">Custom URL</option>`;
    newItem.innerHTML = `
        <div class="server-main-controls">
            <select name="${namePrefix}[${newIndex}][base]">${optionsHtml}</select>
            <input type="text" name="${namePrefix}[${newIndex}][path]" placeholder="Video ID/Path or Full URL">
            <button type="button" class="btn btn-danger btn-small" onclick="this.closest('.server-item').remove()">Remove</button>
        </div>
        <div class="server-extra-controls">
            <label class="drm-label"><input type="checkbox" name="${namePrefix}[${newIndex}][drm]" value="1"> DRM</label>
            <input type="text" name="${namePrefix}[${newIndex}][license_url]" placeholder="License URL (if DRM enabled)">
        </div>
    `;
    container.appendChild(newItem);
}
function escapeHTML(str) { return str ? str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;') : ''; }
const statusMessage = document.getElementById('status-message');
if (statusMessage) { setTimeout(() => { statusMessage.style.display = 'none'; }, 5000); }
</script>
</body>
</html>
