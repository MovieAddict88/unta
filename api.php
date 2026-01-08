<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once 'config.php';

// A simple routing mechanism for API actions
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'get_all_content':
        getAllContent();
        break;
    case 'get_content_paginated':
        getContentPaginated();
        break;
    case 'get_content_version':
        getContentVersion();
        break;
    case 'get_content_count':
        getContentCount();
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function getAllContent() {
    $pdo = connect_db();
    if (!$pdo) {
        echo json_encode(['error' => 'Database connection failed. Please run the installer.']);
        return;
    }

    try {
        // The final structure we want to build
        $output = [
            'Categories' => []
        ];

        // A map to hold categories while we build them
        $categoriesMap = [];

        // Fetch all content items
        $contentStmt = $pdo->query("SELECT * FROM content ORDER BY type, title");
        while ($content = $contentStmt->fetch()) {
            $mainCategoryName = '';
            if ($content['type'] === 'movie') {
                $mainCategoryName = 'Movies';
            } elseif ($content['type'] === 'series') {
                $mainCategoryName = 'TV Series';
            } elseif ($content['type'] === 'live') {
                $mainCategoryName = 'Live TV';
            }

            if (!isset($categoriesMap[$mainCategoryName])) {
                $categoriesMap[$mainCategoryName] = [
                    'MainCategory' => $mainCategoryName,
                    'SubCategories' => [], // We can populate this later if needed
                    'Entries' => []
                ];
            }

            // Build the entry for the current content item
            $entry = [
                'Title' => $content['title'],
                'Description' => $content['description'],
                'Poster' => $content['poster_url'],
                'Thumbnail' => $content['thumbnail_url'],
                'Rating' => $content['rating'],
                'Duration' => $content['duration'],
                'Year' => $content['release_year'],
                'parentalRating' => $content['parental_rating'],
                'Trailer' => [
                    'url' => $content['trailer_url'],
                    'type' => $content['trailer_type']
                ],
                'Servers' => [],
                'Seasons' => []
            ];

            if ($content['type'] === 'movie' || $content['type'] === 'live') {
                // Fetch servers for movies and live TV
                $serverStmt = $pdo->prepare("SELECT name, url, drm, license_url as license FROM servers WHERE content_id = ?");
                $serverStmt->execute([$content['id']]);
                $entry['Servers'] = $serverStmt->fetchAll(PDO::FETCH_ASSOC);
                // Cast drm to boolean
                foreach ($entry['Servers'] as &$server) {
                    $server['drm'] = (bool)$server['drm'];
                }
            } elseif ($content['type'] === 'series') {
                // Fetch seasons and episodes for series
                $seasonStmt = $pdo->prepare("SELECT id, season_number, title, poster_url FROM seasons WHERE content_id = ? ORDER BY season_number");
                $seasonStmt->execute([$content['id']]);

                while ($season = $seasonStmt->fetch()) {
                    $seasonEntry = [
                        'Season' => $season['season_number'],
                        'SeasonPoster' => $season['poster_url'],
                        'Episodes' => []
                    ];

                    // Fetch episodes for this season
                    $episodeStmt = $pdo->prepare("SELECT * FROM episodes WHERE season_id = ? ORDER BY episode_number");
                    $episodeStmt->execute([$season['id']]);

                    while ($episode = $episodeStmt->fetch()) {
                        $episodeEntry = [
                            'Episode' => $episode['episode_number'],
                            'Title' => $episode['title'],
                            'Duration' => $episode['duration'],
                            'Description' => $episode['description'],
                            'Thumbnail' => $episode['thumbnail_url'],
                            'Servers' => []
                        ];

                        // Fetch servers for this episode
                        $epServerStmt = $pdo->prepare("SELECT name, url, drm, license_url as license FROM servers WHERE episode_id = ?");
                        $epServerStmt->execute([$episode['id']]);
                        $episodeEntry['Servers'] = $epServerStmt->fetchAll(PDO::FETCH_ASSOC);
                        // Cast drm to boolean
                        foreach ($episodeEntry['Servers'] as &$server) {
                            $server['drm'] = (bool)$server['drm'];
                        }

                        $seasonEntry['Episodes'][] = $episodeEntry;
                    }
                    $entry['Seasons'][] = $seasonEntry;
                }
            }

            // Add the fully built entry to the correct category
            $categoriesMap[$mainCategoryName]['Entries'][] = $entry;
        }

        // Convert the map to the final array structure
        $output['Categories'] = array_values($categoriesMap);

        echo json_encode($output, JSON_PRETTY_PRINT);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'An error occurred while fetching data: ' . $e->getMessage()]);
    }
}

function getContentCount() {
    $pdo = connect_db();
    if (!$pdo) {
        echo json_encode(['error' => 'Database connection failed. Please run the installer.']);
        return;
    }

    try {
        $queries = [
            'content' => "SELECT COUNT(*) AS total FROM content",
            'movies' => "SELECT COUNT(*) AS total FROM content WHERE type = 'movie'",
            'series' => "SELECT COUNT(*) AS total FROM content WHERE type = 'series'",
            'live' => "SELECT COUNT(*) AS total FROM content WHERE type = 'live'"
        ];

        $counts = [];
        foreach ($queries as $table => $sql) {
            $stmt = $pdo->query($sql);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $counts[$table] = $result['total'];
        }

        echo json_encode($counts);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'An error occurred while fetching count: ' . $e->getMessage()]);
    }
}

function getContentPaginated() {
    $pdo = connect_db();
    if (!$pdo) {
        echo json_encode(['error' => 'Database connection failed. Please run the installer.']);
        return;
    }

    try {
        // Get pagination parameters
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = max(1, min(50, intval($_GET['limit'] ?? 20))); // Max 50 items per page
        $type = $_GET['type'] ?? ''; // 'movie', 'series', 'live', or ''
        $offset = ($page - 1) * $limit;

        // Build the base query
        $whereClause = '';
        $params = [];
        
        if (!empty($type) && in_array($type, ['movie', 'series', 'live'])) {
            $whereClause = ' WHERE type = ?';
            $params[] = $type;
        }

        // Get total count for pagination
        $countSql = "SELECT COUNT(*) as total FROM content" . $whereClause;
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Build the main query with pagination
        $sql = "SELECT * FROM content" . $whereClause . " ORDER BY type, title LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $contentStmt = $pdo->prepare($sql);
        $contentStmt->execute($params);

        $output = [
            'Categories' => [],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalCount,
                'total_pages' => ceil($totalCount / $limit),
                'has_next' => $page < ceil($totalCount / $limit),
                'has_prev' => $page > 1
            ]
        ];

        // A map to hold categories while we build them
        $categoriesMap = [];

        while ($content = $contentStmt->fetch()) {
            $mainCategoryName = '';
            if ($content['type'] === 'movie') {
                $mainCategoryName = 'Movies';
            } elseif ($content['type'] === 'series') {
                $mainCategoryName = 'TV Series';
            } elseif ($content['type'] === 'live') {
                $mainCategoryName = 'Live TV';
            }

            if (!isset($categoriesMap[$mainCategoryName])) {
                $categoriesMap[$mainCategoryName] = [
                    'MainCategory' => $mainCategoryName,
                    'SubCategories' => [],
                    'Entries' => []
                ];
            }

            // Build the entry for the current content item
            $entry = [
                'Title' => $content['title'],
                'Description' => $content['description'],
                'Poster' => $content['poster_url'],
                'Thumbnail' => $content['thumbnail_url'],
                'Rating' => $content['rating'],
                'Duration' => $content['duration'],
                'Year' => $content['release_year'],
                'parentalRating' => $content['parental_rating'],
                'Trailer' => [
                    'url' => $content['trailer_url'],
                    'type' => $content['trailer_type']
                ],
                'Servers' => [],
                'Seasons' => []
            ];

            if ($content['type'] === 'movie' || $content['type'] === 'live') {
                // Fetch servers for movies and live TV
                $serverStmt = $pdo->prepare("SELECT name, url, drm, license_url as license FROM servers WHERE content_id = ?");
                $serverStmt->execute([$content['id']]);
                $entry['Servers'] = $serverStmt->fetchAll(PDO::FETCH_ASSOC);
                // Cast drm to boolean
                foreach ($entry['Servers'] as &$server) {
                    $server['drm'] = (bool)$server['drm'];
                }
            } elseif ($content['type'] === 'series') {
                // Fetch seasons and episodes for series
                $seasonStmt = $pdo->prepare("SELECT id, season_number, title, poster_url FROM seasons WHERE content_id = ? ORDER BY season_number");
                $seasonStmt->execute([$content['id']]);

                while ($season = $seasonStmt->fetch()) {
                    $seasonEntry = [
                        'Season' => $season['season_number'],
                        'SeasonPoster' => $season['poster_url'],
                        'Episodes' => []
                    ];

                    // Fetch episodes for this season
                    $episodeStmt = $pdo->prepare("SELECT * FROM episodes WHERE season_id = ? ORDER BY episode_number");
                    $episodeStmt->execute([$season['id']]);

                    while ($episode = $episodeStmt->fetch()) {
                        $episodeEntry = [
                            'Episode' => $episode['episode_number'],
                            'Title' => $episode['title'],
                            'Duration' => $episode['duration'],
                            'Description' => $episode['description'],
                            'Thumbnail' => $episode['thumbnail_url'],
                            'Servers' => []
                        ];

                        // Fetch servers for this episode
                        $epServerStmt = $pdo->prepare("SELECT name, url, drm, license_url as license FROM servers WHERE episode_id = ?");
                        $epServerStmt->execute([$episode['id']]);
                        $episodeEntry['Servers'] = $epServerStmt->fetchAll(PDO::FETCH_ASSOC);
                        // Cast drm to boolean
                        foreach ($episodeEntry['Servers'] as &$server) {
                            $server['drm'] = (bool)$server['drm'];
                        }

                        $seasonEntry['Episodes'][] = $episodeEntry;
                    }
                    $entry['Seasons'][] = $seasonEntry;
                }
            }

            // Add the fully built entry to the correct category
            $categoriesMap[$mainCategoryName]['Entries'][] = $entry;
        }

        // Convert the map to the final array structure
        $output['Categories'] = array_values($categoriesMap);

        echo json_encode($output, JSON_PRETTY_PRINT);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'An error occurred while fetching paginated data: ' . $e->getMessage()]);
    }
}

function getContentVersion() {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $pdo = connect_db();
    if (!$pdo) {
        echo json_encode(['error' => 'Database connection failed. Please run the installer.']);
        return;
    }

    try {
        $queries = [
            'content' => "SELECT COUNT(*) AS row_count,
                COALESCE(MAX(id), 0) AS max_id,
                COALESCE(BIT_XOR(CRC32(CONCAT_WS('#',
                    id, IFNULL(tmdb_id, ''), type, title,
                    IFNULL(description, ''),
                    IFNULL(poster_url, ''),
                    IFNULL(thumbnail_url, ''),
                    IFNULL(release_year, ''),
                    IFNULL(rating, ''),
                    IFNULL(duration, ''),
                    IFNULL(parental_rating, ''),
                    IFNULL(trailer_url, ''),
                    IFNULL(trailer_type, ''),
                    IFNULL(created_at, '')
                ))), 0) AS checksum
                FROM content",
            'seasons' => "SELECT COUNT(*) AS row_count,
                COALESCE(MAX(id), 0) AS max_id,
                COALESCE(BIT_XOR(CRC32(CONCAT_WS('#',
                    id, content_id, season_number,
                    IFNULL(title, ''),
                    IFNULL(poster_url, '')
                ))), 0) AS checksum
                FROM seasons",
            'episodes' => "SELECT COUNT(*) AS row_count,
                COALESCE(MAX(id), 0) AS max_id,
                COALESCE(BIT_XOR(CRC32(CONCAT_WS('#',
                    id, season_id, episode_number,
                    title,
                    IFNULL(description, ''),
                    IFNULL(thumbnail_url, ''),
                    IFNULL(duration, ''),
                    IFNULL(trailer_url, ''),
                    IFNULL(trailer_type, '')
                ))), 0) AS checksum
                FROM episodes",
            'servers' => "SELECT COUNT(*) AS row_count,
                COALESCE(MAX(id), 0) AS max_id,
                COALESCE(BIT_XOR(CRC32(CONCAT_WS('#',
                    id,
                    IFNULL(content_id, 0),
                    IFNULL(episode_id, 0),
                    name,
                    IFNULL(url, ''),
                    IFNULL(quality, ''),
                    drm,
                    IFNULL(license_url, '')
                ))), 0) AS checksum
                FROM servers"
        ];

        $stats = [];
        foreach ($queries as $table => $sql) {
            $stmt = $pdo->query($sql);
            $stats[$table] = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $version = hash('sha256', json_encode($stats));
        echo json_encode(['version' => $version]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'An error occurred while fetching version: ' . $e->getMessage()]);
    }
}
?>
