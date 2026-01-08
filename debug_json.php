<?php
require_once 'config.php';

function findExactProblem() {
    $pdo = connect_db();
    
    echo "Building complete JSON structure to find exact problem...\n\n";
    
    // Build the exact same structure as the API
    $output = ['Categories' => []];
    $categoriesMap = [];
    
    $contentStmt = $pdo->query("SELECT * FROM content ORDER BY id");
    $currentLength = 0;
    $problemPosition = 10097152;
    
    while ($content = $contentStmt->fetch()) {
        $mainCategoryName = getCategoryName($content['type']);
        
        if (!isset($categoriesMap[$mainCategoryName])) {
            $categoriesMap[$mainCategoryName] = [
                'MainCategory' => $mainCategoryName,
                'Entries' => []
            ];
        }

        $entry = buildContentEntry($pdo, $content);
        
        // Test this specific entry
        $testEntry = json_encode($entry, JSON_PRETTY_PRINT);
        if ($testEntry === false) {
            echo "=== PROBLEM IN CONTENT ID: {$content['id']} ===\n";
            echo "Title: {$content['title']}\n";
            echo "JSON Error: " . json_last_error_msg() . "\n";
            findProblemInEntry($entry);
            return;
        }
        
        $categoriesMap[$mainCategoryName]['Entries'][] = $entry;
        
        // Test the current state
        $testOutput = ['Categories' => array_values($categoriesMap)];
        $testJson = json_encode($testOutput, JSON_PRETTY_PRINT);
        $currentLength = strlen($testJson);
        
        echo "Processed content ID {$content['id']} - JSON length: $currentLength\n";
        
        if ($currentLength >= $problemPosition - 1000 && $currentLength <= $problemPosition + 1000) {
            echo "=== CLOSE TO PROBLEM POSITION ===\n";
            echo "Current length: $currentLength\n";
            echo "Problem position: $problemPosition\n";
            
            if ($testJson === false) {
                echo "JSON ERROR at this point!\n";
                echo "Error: " . json_last_error_msg() . "\n";
                return;
            }
        }
        
        if ($currentLength > $problemPosition + 5000) {
            echo "Passed problem position without finding issue. The problem might be in the JSON structure itself.\n";
            break;
        }
    }
    
    echo "Finished processing all content. Final JSON length: $currentLength\n";
}

function buildContentEntry($pdo, $content) {
    $entry = [
        'Title' => cleanString($content['title']),
        'Description' => cleanString($content['description']),
        'Poster' => cleanString($content['poster_url']),
        'Thumbnail' => cleanString($content['thumbnail_url']),
        'Rating' => cleanString($content['rating']),
        'Duration' => cleanString($content['duration']),
        'Year' => cleanString($content['release_year']),
        'parentalRating' => cleanString($content['parental_rating']),
        'Servers' => [],
        'Seasons' => []
    ];

    if ($content['type'] === 'movie' || $content['type'] === 'live') {
        $serverStmt = $pdo->prepare("SELECT name, url, drm, license_url as license FROM servers WHERE content_id = ?");
        $serverStmt->execute([$content['id']]);
        $servers = $serverStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($servers as $server) {
            $entry['Servers'][] = [
                'name' => cleanString($server['name']),
                'url' => cleanString($server['url']),
                'license' => cleanString($server['license']),
                'drm' => (bool)$server['drm']
            ];
        }
    } elseif ($content['type'] === 'series') {
        $seasonStmt = $pdo->prepare("SELECT id, season_number, title, poster_url FROM seasons WHERE content_id = ? ORDER BY season_number");
        $seasonStmt->execute([$content['id']]);

        while ($season = $seasonStmt->fetch()) {
            $seasonEntry = [
                'Season' => (int)$season['season_number'],
                'SeasonPoster' => cleanString($season['poster_url']),
                'Episodes' => []
            ];

            $episodeStmt = $pdo->prepare("SELECT * FROM episodes WHERE season_id = ? ORDER BY episode_number");
            $episodeStmt->execute([$season['id']]);

            while ($episode = $episodeStmt->fetch()) {
                $episodeEntry = [
                    'Episode' => (int)$episode['episode_number'],
                    'Title' => cleanString($episode['title']),
                    'Duration' => cleanString($episode['duration']),
                    'Description' => cleanString($episode['description']),
                    'Thumbnail' => cleanString($episode['thumbnail_url']),
                    'Servers' => []
                ];

                $epServerStmt = $pdo->prepare("SELECT name, url, drm, license_url as license FROM servers WHERE episode_id = ?");
                $epServerStmt->execute([$episode['id']]);
                $episodeServers = $epServerStmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($episodeServers as $server) {
                    $episodeEntry['Servers'][] = [
                        'name' => cleanString($server['name']),
                        'url' => cleanString($server['url']),
                        'license' => cleanString($server['license']),
                        'drm' => (bool)$server['drm']
                    ];
                }

                $seasonEntry['Episodes'][] = $episodeEntry;
            }
            $entry['Seasons'][] = $seasonEntry;
        }
    }

    return $entry;
}

function findProblemInEntry($entry) {
    echo "Searching for problem in entry...\n";
    
    foreach ($entry as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $index => $item) {
                $test = json_encode([$key => [$index => $item]]);
                if ($test === false) {
                    echo "Problem in $key at index $index\n";
                    var_dump($item);
                    return;
                }
            }
        } else {
            $test = json_encode([$key => $value]);
            if ($test === false) {
                echo "Problem in field: $key\n";
                echo "Value: " . substr($value, 0, 200) . "\n";
                return;
            }
        }
    }
}

function getCategoryName($type) {
    switch ($type) {
        case 'movie': return 'Movies';
        case 'series': return 'TV Series';
        case 'live': return 'Live TV';
        default: return 'Other';
    }
}

function cleanString($string) {
    if ($string === null) return '';
    $string = preg_replace('/[\x00-\x1F\x7F]/', '', $string);
    return trim($string);
}

findExactProblem();
?>