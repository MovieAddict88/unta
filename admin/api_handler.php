<?php
header('Content-Type: application/json');
require_once 'tmdb_handler.php'; // Re-use the fetchTMDB function

// Simple router for API actions
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'search_tmdb':
        handle_search_tmdb();
        break;
    case 'browse_regional':
        handle_browse_regional();
        break;
    default:
        echo json_encode(['error' => 'Invalid API action']);
        http_response_code(400);
        break;
}

function handle_search_tmdb() {
    $query = isset($_GET['query']) ? trim($_GET['query']) : '';
    $type = isset($_GET['type']) ? trim($_GET['type']) : 'multi';
    $apiKey = isset($_GET['apiKey']) ? trim($_GET['apiKey']) : 'ec926176bf467b3f7735e3154238c161';

    if (empty($query)) {
        echo json_encode(['error' => 'Search query is required.']);
        http_response_code(400);
        return;
    }

    $endpoint = "/search/{$type}";
    $params = ['query' => $query];

    $results = fetchTMDB($endpoint, $apiKey, $params);

    if (isset($results['results'])) {
        echo json_encode($results['results']);
    } else {
        echo json_encode(['error' => 'Failed to fetch search results from TMDB.', 'details' => $results]);
    }
}

function handle_browse_regional() {
    $region = isset($_GET['region']) ? trim($_GET['region']) : 'hollywood';
    $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    $type = isset($_GET['type']) ? trim($_GET['type']) : 'movie';
    $apiKey = isset($_GET['apiKey']) ? trim($_GET['apiKey']) : 'ec926176bf467b3f7735e3154238c161';
    $max_pages_to_fetch = 10; // Set the number of pages to fetch

    $regionalConfigs = [
        'hollywood' => ['with_original_language' => 'en'],
        'anime' => ['with_genres' => 16, 'with_original_language' => 'ja'],
        'animation' => ['with_genres' => 16],
        'kdrama' => ['with_genres' => 18, 'with_original_language' => 'ko'],
        'cdrama' => ['with_genres' => 18, 'with_original_language' => 'zh'],
        'jdrama' => ['with_genres' => 18, 'with_original_language' => 'ja'],
        'pinoy' => ['with_origin_country' => 'PH', 'with_original_language' => 'tl'],
        'thai' => ['with_origin_country' => 'TH', 'with_original_language' => 'th'],
        'indian' => ['with_origin_country' => 'IN', 'with_original_language' => 'hi'],
        'turkish' => ['with_origin_country' => 'TR', 'with_original_language' => 'tr'],
    ];

    $base_params = ['sort_by' => 'popularity.desc'];
    if (isset($regionalConfigs[$region])) {
        $base_params = array_merge($base_params, $regionalConfigs[$region]);
    }

    $results = [];
    $typesToFetch = ($type === 'multi') ? ['movie', 'tv'] : [$type];

    foreach ($typesToFetch as $fetchType) {
        $endpoint = "/discover/{$fetchType}";
        $queryParams = $base_params;
        if ($year) {
            if ($fetchType === 'tv') {
                $queryParams['first_air_date_year'] = $year;
            } else {
                $queryParams['primary_release_year'] = $year;
            }
        }

        for ($page = 1; $page <= $max_pages_to_fetch; $page++) {
            $queryParams['page'] = $page;
            $data = fetchTMDB($endpoint, $apiKey, $queryParams);

            if (isset($data['results']) && !empty($data['results'])) {
                foreach ($data['results'] as &$item) {
                    $item['media_type'] = $fetchType;
                }
                $results = array_merge($results, $data['results']);
            } else {
                // If a page returns no results, stop fetching for this type
                break;
            }
        }
    }

    usort($results, function($a, $b) {
        return ($b['popularity'] ?? 0) <=> ($a['popularity'] ?? 0);
    });

    echo json_encode($results);
}
?>
