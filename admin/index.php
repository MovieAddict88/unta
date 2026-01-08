<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once '../config.php';
require_once 'tmdb_handler.php';

$status_message = '';
$status_type = 'info';
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action === 'import_json' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once 'import_handler.php';
        if (isset($_FILES['json_file'])) {
            $result = handle_json_import($_FILES['json_file']);
            $status_message = $result;
        } else {
            $status_message = "Error: No file was uploaded.";
        }
    } elseif (($action === 'add_movie' || $action === 'add_series') && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['tmdb_id'])) {
        $tmdbId = trim($_POST['tmdb_id']);
        if ($action === 'add_movie') {
            $result = addMovieFromTmdb($tmdbId);
        } else {
            $seasons = isset($_POST['seasons']) ? trim($_POST['seasons']) : '';
            $result = addSeriesFromTmdb($tmdbId, $seasons);
        }
        $status_message = $result;
    }

    if (strpos($status_message, 'Success') === 0) {
        $status_type = 'success';
    } elseif (strpos($status_message, 'Info') === 0) {
        $status_type = 'info';
    } else {
        $status_type = 'error';
    }

    if ($is_ajax && $action !== 'import_json') {
        header('Content-Type: application/json');
        echo json_encode(['status' => $status_type, 'message' => $status_message]);
        exit;
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
    <title>Admin Dashboard - CineCraze</title>
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
                    <div class="nav-item active" data-tab="tmdb-generator" role="button" tabindex="0" aria-label="TMDB Generator">
                        <i class="fas fa-film"></i>
                        <span class="nav-label">TMDB Generator</span>
                    </div>
                    <div class="nav-item" data-tab="youtube-search" role="button" tabindex="0" aria-label="YouTube Search">
                        <i class="fab fa-youtube"></i>
                        <span class="nav-label">YouTube Search</span>
                    </div>
                    <div class="nav-item" data-tab="data-management" role="button" tabindex="0" aria-label="Data Management">
                        <i class="fas fa-database"></i>
                        <span class="nav-label">Data Management</span>
                    </div>
                    <div class="nav-item" data-tab="manual-input" role="button" tabindex="0" aria-label="Manual Input">
                        <i class="fas fa-pen"></i>
                        <span class="nav-label">Manual Input</span>
                    </div>
                    <div class="nav-item" data-tab="bulk-operations" role="button" tabindex="0" aria-label="Bulk Operations">
                        <i class="fas fa-box"></i>
                        <span class="nav-label">Bulk Operations</span>
                    </div>
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
                    <h1>Admin Dashboard</h1>
                    <div class="subtitle">Manage catalog generation, imports, and content</div>
                </div>
                <div class="header-actions">
                    <a href="settings.php" class="icon-btn" title="Settings" aria-label="Settings"><i class="fas fa-gear"></i></a>
                    <a href="logout.php" class="icon-btn" title="Logout" aria-label="Logout"><i class="fas fa-right-from-bracket"></i></a>
                </div>
            </header>

            <div class="content-wrapper">
                <div id="status-container">
                    <?php if ($status_message && !$is_ajax): ?>
                        <div class="status <?php echo $status_type; ?>"><?php echo htmlspecialchars($status_message); ?></div>
                    <?php endif; ?>
                </div>

        <div id="tmdb-generator" class="tab-content active">
            <div class="card">
                <h2><i class="fas fa-key"></i> API Key Management</h2>
                <div class="form-group">
                    <label for="api-key-select">Select TMDB API Key</label>
                    <select id="api-key-select">
                        <option value="ec926176bf467b3f7735e3154238c161">Primary Key (***c161)</option>
                        <option value="bb51e18edb221e87a05f90c2eb456069">Backup Key 1 (***6069)</option>
                        <option value="4a1f2e8c9d3b5a7e6f9c2d1e8b4a5c3f">Backup Key 2 (***a5c3f)</option>
                        <option value="7d9a2b1e4f6c8e5a3b7d9f2e1c4a6b8d">Backup Key 3 (***a6b8d)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-2">
                <div class="card">
                    <h2><i class="fas fa-film"></i> Generate Movie by ID</h2>
                    <form id="generate-movie-form">
                        <div class="form-group">
                            <label for="movie-tmdb-id">TMDB Movie ID</label>
                            <input type="number" id="movie-tmdb-id" name="tmdb_id" placeholder="e.g., 550" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Generate Movie</button>
                    </form>
                </div>

                <div class="card">
                    <h2><i class="fas fa-tv"></i> Generate Series by ID</h2>
                    <form id="generate-series-form">
                        <div class="form-group">
                            <label for="series-tmdb-id">TMDB TV Series ID</label>
                            <input type="number" id="series-tmdb-id" name="tmdb_id" placeholder="e.g., 1399" required>
                        </div>
                        <div class="form-group">
                            <label for="series-seasons">Seasons (optional, comma-separated)</label>
                            <input type="text" id="series-seasons" name="seasons" placeholder="e.g., 1,3 or leave empty for all">
                        </div>
                        <button type="submit" class="btn btn-primary">Generate Series</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <h2><i class="fas fa-search"></i> Advanced Search & Browse</h2>
                <div class="grid">
                    <div class="form-group">
                        <label for="search-mode">Mode</label>
                        <select id="search-mode">
                            <option value="search">🔍 Search Mode</option>
                            <option value="hollywood">🎬 Hollywood</option>
                            <option value="anime">🇯🇵 Anime</option>
                            <option value="animation">🎨 Animation</option>
                            <option value="kdrama">🇰🇷 K-Drama</option>
                            <option value="cdrama">🇨🇳 C-Drama</option>
                            <option value="jdrama">🇯🇵 J-Drama</option>
                            <option value="pinoy">🇵🇭 Pinoy Series</option>
                            <option value="thai">🇹🇭 Thai Drama</option>
                            <option value="indian">🇮🇳 Indian Series</option>
                            <option value="turkish">🇹🇷 Turkish Drama</option>
                        </select>
                    </div>
                    <div class="form-group" id="search-query-container">
                        <label for="tmdb-search-query">Search Query</label>
                        <input type="text" id="tmdb-search-query" placeholder="e.g., The Matrix">
                    </div>
                    <div class="form-group" id="content-type-container">
                        <label for="content-type">Content Type</label>
                        <select id="content-type">
                            <option value="multi">All</option>
                            <option value="movie">Movies</option>
                            <option value="tv">TV Shows</option>
                        </select>
                    </div>
                    <div class="form-group" id="year-container" style="display: none;">
                        <label for="browse-year">Year</label>
                        <input type="number" id="browse-year" placeholder="e.g., 2023" value="<?php echo date("Y"); ?>">
                    </div>
                </div>
                <button id="execute-search-btn" class="btn btn-primary"><i class="fas fa-search"></i> Execute</button>
                <div id="search-results" class="preview-grid"></div>
            </div>
        </div>

        <div id="data-management" class="tab-content">
            <div class="card">
                <h2><i class="fas fa-file-import"></i> Import from JSON</h2>
                <form action="index.php?action=import_json" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="json-file">Select playlist.json file</label>
                        <input type="file" name="json_file" id="json-file" accept=".json" required style="padding: 10px; background-color: var(--surface-light);">
                    </div>
                    <button type="submit" class="btn btn-primary">Import Data</button>
                </form>
            </div>

            <div class="card">
                <h2><i class="fas fa-list-alt"></i> Content Management</h2>
                <div class="grid">
                    <div class="form-group">
                        <label for="content-search">Search by Title</label>
                        <input type="text" id="content-search" placeholder="Enter title...">
                    </div>
                    <div class="form-group">
                        <label for="content-type-filter">Filter by Type</label>
                        <select id="content-type-filter">
                            <option value="all">All</option>
                            <option value="movie">Movies</option>
                            <option value="series">TV Series</option>
                            <option value="live">Live TV</option>
                        </select>
                    </div>
                </div>
                <div id="content-management-grid" class="preview-grid"></div>
                <div id="content-pagination" class="pagination" aria-label="Content pagination"></div>
            </div>
        </div>

        <div id="youtube-search" class="tab-content">
            <div class="card">
                <h2><i class="fab fa-youtube"></i> YouTube API Key</h2>
                <div class="form-group">
                    <label for="youtube-api-key">YouTube API Key</label>
                    <input type="text" id="youtube-api-key" value="AIzaSyCuDFW3lSVrvc-nGUeQOkM7h_f_MA90NwY" placeholder="Enter YouTube API Key">
                </div>
            </div>

            <div class="card">
                <h2><i class="fas fa-search"></i> Search YouTube Content</h2>
                <div class="grid">
                    <div class="form-group">
                        <label for="youtube-search-query">Search Query</label>
                        <input type="text" id="youtube-search-query" placeholder="e.g., Movie name, Series name, Live TV">
                    </div>
                    <div class="form-group">
                        <label for="youtube-content-type">Content Type</label>
                        <select id="youtube-content-type">
                            <option value="movie">Movie</option>
                            <option value="series">Series</option>
                            <option value="live">Live TV</option>
                        </select>
                    </div>
                </div>
                <button id="search-youtube-btn" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search YouTube
                </button>
                <div id="youtube-results" class="preview-grid"></div>
            </div>
        </div>

        <div id="manual-input" class="tab-content">
            <div class="card">
                <h2><i class="fas fa-pen"></i> Manual Input</h2>
                <p>This feature will be implemented in a future update.</p>
            </div>
        </div>

        <div id="bulk-operations" class="tab-content">
            <div class="card">
                <h2><i class="fas fa-box"></i> Bulk Operations</h2>
                <p>This feature will be implemented in a future update.</p>
            </div>
            </div>
        </main>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach((c) => c.classList.remove('active'));
            document.querySelectorAll('.sidebar .nav-item[data-tab]').forEach((n) => n.classList.remove('active'));

            const tabEl = document.getElementById(tabName);
            if (tabEl) {
                tabEl.classList.add('active');
            }

            const navEl = document.querySelector(`.sidebar .nav-item[data-tab="${tabName}"]`);
            if (navEl) {
                navEl.classList.add('active');
            }

            if (history.replaceState) {
                history.replaceState(null, '', `#${tabName}`);
            }

            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        }

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

            document.querySelectorAll('.sidebar .nav-item[role="button"]').forEach((item) => {
                item.addEventListener('click', () => {
                    if (item.dataset.tab) {
                        switchTab(item.dataset.tab);
                    }
                });

                item.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        if (item.dataset.tab) {
                            switchTab(item.dataset.tab);
                        }
                    }
                });
            });

            if (window.location.hash) {
                const tab = window.location.hash.replace('#', '');
                if (document.getElementById(tab)) {
                    switchTab(tab);
                }
            }

            const statusContainer = document.getElementById('status-container');

            // --- TMDB Generator Logic ---
            const modeSelect = document.getElementById('search-mode');
            const queryContainer = document.getElementById('search-query-container');
            const typeContainer = document.getElementById('content-type-container');
            const yearContainer = document.getElementById('year-container');
            const executeBtn = document.getElementById('execute-search-btn');
            const tmdbResultsContainer = document.getElementById('search-results');

            function toggleFilters() {
                const mode = modeSelect.value;
                if (mode === 'search') {
                    queryContainer.style.display = 'block';
                    typeContainer.style.display = 'block';
                    yearContainer.style.display = 'none';
                    typeContainer.querySelector('select').value = 'multi';
                } else {
                    queryContainer.style.display = 'none';
                    typeContainer.style.display = 'block';
                    yearContainer.style.display = 'block';
                }
            }

            modeSelect.addEventListener('change', toggleFilters);
            executeBtn.addEventListener('click', executeTmdbSearch);

            async function executeTmdbSearch() {
                const apiKey = document.getElementById('api-key-select').value;
                const mode = modeSelect.value;
                let url = '';

                if (mode === 'search') {
                    const query = document.getElementById('tmdb-search-query').value;
                    const type = document.getElementById('content-type').value;
                    if (!query) {
                        alert('Please enter a search query.');
                        return;
                    }
                    url = `api_handler.php?action=search_tmdb&query=${encodeURIComponent(query)}&type=${type}&apiKey=${apiKey}`;
                } else {
                    const year = document.getElementById('browse-year').value;
                    const type = document.getElementById('content-type').value;
                    url = `api_handler.php?action=browse_regional&region=${mode}&year=${year}&type=${type}&apiKey=${apiKey}`;
                }

                tmdbResultsContainer.innerHTML = '<p>Loading...</p>';
                try {
                    const response = await fetch(url);
                    const results = await response.json();
                    if (results.error) throw new Error(results.error);
                    renderTmdbResults(results);
                } catch (error) {
                    tmdbResultsContainer.innerHTML = `<p style="color: var(--danger);">Error: ${error.message}</p>`;
                }
            }

            function renderTmdbResults(results) {
                tmdbResultsContainer.innerHTML = '';
                if (!results || results.length === 0) {
                    tmdbResultsContainer.innerHTML = '<p>No results found.</p>';
                    return;
                }
                results.forEach((item) => {
                    const type = item.media_type || (item.title ? 'movie' : 'tv');
                    if (type === 'person') return;
                    const title = item.title || item.name;
                    const year = (item.release_date || item.first_air_date || '').substring(0, 4);
                    const posterPath = item.poster_path
                        ? `https://image.tmdb.org/t/p/w200${item.poster_path}`
                        : 'https://via.placeholder.com/200x300?text=No+Image';
                    const card = document.createElement('div');
                    card.className = 'preview-item';
                    card.innerHTML = `
                        <img src="${posterPath}" alt="${title}">
                        <div class="info">
                            <div class="title">${title}</div>
                            <div class="meta">${year} &bull; ${type.toUpperCase()}</div>
                            <form class="generate-form" data-action="index.php?action=${type === 'movie' ? 'add_movie' : 'add_series'}">
                                <input type="hidden" name="tmdb_id" value="${item.id}">
                                <button type="submit" class="btn btn-primary btn-small">Generate</button>
                            </form>
                        </div>
                    `;
                    tmdbResultsContainer.appendChild(card);
                });
            }

            document.body.addEventListener('submit', function(e) {
                if (e.target.classList.contains('generate-form')) {
                    handleSimpleGenerate(e, e.target.dataset.action, new FormData(e.target));
                }
                if (e.target.id === 'generate-movie-form' || e.target.id === 'generate-series-form') {
                    const action = e.target.id === 'generate-movie-form' ? 'add_movie' : 'add_series';
                    handleSimpleGenerate(e, `index.php?action=${action}`, new FormData(e.target));
                }
            });

            async function handleSimpleGenerate(e, action, formData) {
                e.preventDefault();
                const btn = e.target.querySelector('button');
                const initialBtnText = btn.textContent;
                btn.textContent = 'Generating...';
                btn.disabled = true;

                try {
                    const response = await fetch(action, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await response.json();
                    showStatus(data.status, data.message);
                    if (e.target.id) e.target.reset();
                    loadManagedContent();
                } catch (error) {
                    showStatus('error', 'An unexpected error occurred.');
                } finally {
                    btn.textContent = initialBtnText;
                    btn.disabled = false;
                }
            }

            function showStatus(type, message) {
                statusContainer.innerHTML = `<div class="status ${type}">${message}</div>`;
                setTimeout(() => {
                    statusContainer.innerHTML = '';
                }, 5000);
            }

            toggleFilters();

            // --- Content Management Logic ---
            const contentSearchInput = document.getElementById('content-search');
            const contentTypeFilter = document.getElementById('content-type-filter');
            const contentGrid = document.getElementById('content-management-grid');
            const paginationContainer = document.getElementById('content-pagination');
            let currentContentPage = 1;

            async function loadManagedContent() {
                const search = contentSearchInput.value;
                const type = contentTypeFilter.value;
                const url = `content_manager_api.php?action=get_content&page=${currentContentPage}&search=${encodeURIComponent(search)}&type=${type}`;

                contentGrid.innerHTML = '<p>Loading content...</p>';
                try {
                    const response = await fetch(url);
                    const data = await response.json();
                    if (data.error) throw new Error(data.error);

                    renderManagedContent(data.content);
                    renderPagination(data.pagination);
                } catch (error) {
                    contentGrid.innerHTML = `<p style="color: var(--danger);">Error: ${error.message}</p>`;
                }
            }

            function renderManagedContent(content) {
                contentGrid.innerHTML = '';
                if (content.length === 0) {
                    contentGrid.innerHTML = '<p>No content found.</p>';
                    return;
                }
                content.forEach((item) => {
                    const card = document.createElement('div');
                    card.className = 'preview-item';
                    card.innerHTML = `
                        <img src="${item.poster_url || 'https://via.placeholder.com/200x300?text=No+Image'}" alt="${item.title}">
                        <div class="info">
                            <div class="title">${item.title}</div>
                            <div class="meta">${item.release_year} &bull; ${item.type.toUpperCase()}</div>
                            <div style="margin-top: 10px;">
                                <a href="edit_content.php?id=${item.id}" class="btn btn-secondary btn-small">Edit</a>
                                <button class="btn btn-danger btn-small delete-content-btn" data-id="${item.id}">Delete</button>
                            </div>
                        </div>
                    `;
                    contentGrid.appendChild(card);
                });
            }

            function renderPagination(pagination) {
                paginationContainer.innerHTML = '';
                if (pagination.totalPages <= 1) return;

                for (let i = 1; i <= pagination.totalPages; i++) {
                    const pageBtn = document.createElement('button');
                    pageBtn.className = 'btn btn-secondary btn-small';
                    pageBtn.textContent = i;
                    if (i === pagination.currentPage) {
                        pageBtn.disabled = true;
                        pageBtn.style.backgroundColor = 'var(--primary)';
                    }
                    pageBtn.addEventListener('click', () => {
                        currentContentPage = i;
                        loadManagedContent();
                    });
                    paginationContainer.appendChild(pageBtn);
                }
            }

            const debounce = (func, delay) => {
                let timeout;
                return function(...args) {
                    const context = this;
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(context, args), delay);
                };
            };

            contentGrid.addEventListener('click', async function(e) {
                if (e.target.classList.contains('delete-content-btn')) {
                    const btn = e.target;
                    const contentId = btn.dataset.id;
                    if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                        try {
                            const response = await fetch('content_manager_api.php?action=delete_content', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ id: contentId }),
                            });
                            const data = await response.json();
                            if (data.error) throw new Error(data.error);
                            showStatus('success', data.message);
                            loadManagedContent();
                        } catch (error) {
                            showStatus('error', error.message);
                        }
                    }
                }
            });

            contentSearchInput.addEventListener('keyup', debounce(loadManagedContent, 500));
            contentTypeFilter.addEventListener('change', loadManagedContent);

            loadManagedContent();

            // --- YouTube Search Logic ---
            const youtubeSearchBtn = document.getElementById('search-youtube-btn');
            const youtubeResultsContainer = document.getElementById('youtube-results');
            const youtubeSearchQuery = document.getElementById('youtube-search-query');
            const youtubeContentType = document.getElementById('youtube-content-type');
            const youtubeApiKey = document.getElementById('youtube-api-key');

            if (youtubeSearchBtn) {
                youtubeSearchBtn.addEventListener('click', executeYouTubeSearch);
            }

            async function executeYouTubeSearch() {
                const query = youtubeSearchQuery.value.trim();
                const contentType = youtubeContentType.value;
                const apiKey = youtubeApiKey.value.trim();

                if (!query) {
                    alert('Please enter a search query.');
                    return;
                }

                if (!apiKey) {
                    alert('Please enter a YouTube API key.');
                    return;
                }

                const url = `youtube_api.php?action=search_youtube&query=${encodeURIComponent(query)}&type=${contentType}&apiKey=${apiKey}`;
                youtubeResultsContainer.innerHTML = '<p>Searching YouTube...</p>';

                try {
                    const response = await fetch(url);
                    const results = await response.json();
                    
                    if (results.error) {
                        throw new Error(results.error);
                    }
                    
                    renderYouTubeResults(results, contentType, apiKey);
                } catch (error) {
                    youtubeResultsContainer.innerHTML = `<p style="color: var(--danger);">Error: ${error.message}</p>`;
                }
            }

            function renderYouTubeResults(results, contentType, apiKey) {
                youtubeResultsContainer.innerHTML = '';
                
                if (!results || results.length === 0) {
                    youtubeResultsContainer.innerHTML = '<p>No results found.</p>';
                    return;
                }

                results.forEach((item) => {
                    const card = document.createElement('div');
                    card.className = 'preview-item';
                    
                    const duration = formatDuration(item.duration);
                    const publishedDate = new Date(item.publishedAt).getFullYear();
                    
                    card.innerHTML = `
                        <img src="${item.thumbnail || 'https://via.placeholder.com/200x300?text=No+Image'}" alt="${item.title}">
                        <div class="info">
                            <div class="title" style="font-size: 14px; line-height: 1.4;">${item.title}</div>
                            <div class="meta" style="font-size: 12px;">${publishedDate} &bull; ${duration} &bull; ${item.channelTitle}</div>
                            <form class="youtube-add-form" style="margin-top: 10px;">
                                <input type="hidden" name="video_id" value="${item.id}">
                                <input type="hidden" name="content_type" value="${contentType}">
                                <input type="hidden" name="apiKey" value="${apiKey}">
                                <button type="submit" class="btn btn-primary btn-small">Add to Database</button>
                            </form>
                        </div>
                    `;
                    
                    youtubeResultsContainer.appendChild(card);
                });
            }

            function formatDuration(isoDuration) {
                if (!isoDuration) return '0:00';
                
                const match = isoDuration.match(/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/);
                if (!match) return '0:00';
                
                const hours = parseInt(match[1]) || 0;
                const minutes = parseInt(match[2]) || 0;
                const seconds = parseInt(match[3]) || 0;
                
                if (hours > 0) {
                    return `${hours}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                }
                return `${minutes}:${seconds.toString().padStart(2, '0')}`;
            }

            document.body.addEventListener('submit', function(e) {
                if (e.target.classList.contains('youtube-add-form')) {
                    handleYouTubeAdd(e);
                }
            });

            async function handleYouTubeAdd(e) {
                e.preventDefault();
                const form = e.target;
                const btn = form.querySelector('button');
                const initialBtnText = btn.textContent;
                
                btn.textContent = 'Adding...';
                btn.disabled = true;

                try {
                    const formData = new FormData(form);
                    const response = await fetch('youtube_api.php?action=add_youtube_content', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    showStatus(data.status, data.message);
                    
                    if (data.status === 'success') {
                        loadManagedContent();
                    }
                } catch (error) {
                    showStatus('error', 'An unexpected error occurred: ' + error.message);
                } finally {
                    btn.textContent = initialBtnText;
                    btn.disabled = false;
                }
            }
        });
    </script>
</body>
</html>
