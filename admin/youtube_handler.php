<?php
require_once '../config.php';

function fetchYouTube($endpoint, $apiKey, $params = []) {
    $base_url = "https://www.googleapis.com/youtube/v3";
    $params['key'] = $apiKey;
    $queryString = http_build_query($params);
    $url = "{$base_url}{$endpoint}?{$queryString}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'CineCrazePHPApp/1.0');
    $output = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpcode != 200) {
        return ['error' => 'YouTube API request failed', 'status_code' => $httpcode, 'response' => json_decode($output, true)];
    }
    
    return json_decode($output, true);
}

function searchYouTubeVideos($query, $contentType = 'video', $apiKey = 'AIzaSyCuDFW3lSVrvc-nGUeQOkM7h_f_MA90NwY', $maxResults = 20) {
    $params = [
        'part' => 'snippet',
        'q' => $query,
        'type' => 'video',
        'maxResults' => $maxResults,
        'order' => 'relevance',
        'videoDefinition' => 'any',
        'safeSearch' => 'none'
    ];
    
    if ($contentType === 'live') {
        $params['eventType'] = 'live';
    }
    
    $result = fetchYouTube('/search', $apiKey, $params);
    
    if (isset($result['error'])) {
        return $result;
    }
    
    if (empty($result['items'])) {
        return ['items' => []];
    }
    
    $videoIds = array_map(function($item) {
        return $item['id']['videoId'];
    }, $result['items']);
    
    $videoDetails = fetchYouTube('/videos', $apiKey, [
        'part' => 'snippet,contentDetails,statistics',
        'id' => implode(',', $videoIds)
    ]);
    
    if (isset($videoDetails['items'])) {
        return ['items' => $videoDetails['items']];
    }
    
    return $result;
}

function addYouTubeContent($videoId, $contentType = 'movie', $apiKey = 'AIzaSyCuDFW3lSVrvc-nGUeQOkM7h_f_MA90NwY') {
    $pdo = connect_db();
    if (!$pdo) return "Error: Database connection failed.";
    
    $videoData = fetchYouTube('/videos', $apiKey, [
        'part' => 'snippet,contentDetails,statistics',
        'id' => $videoId
    ]);
    
    if (!$videoData || !empty($videoData['error'])) {
        return "Error: Could not fetch data for YouTube Video ID: {$videoId}.";
    }
    
    if (empty($videoData['items'])) {
        return "Error: Video not found or unavailable.";
    }
    
    $video = $videoData['items'][0];
    $snippet = $video['snippet'];
    $contentDetails = $video['contentDetails'];
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT id FROM content WHERE title = ? AND type = ?");
        $stmt->execute([$snippet['title'], $contentType]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            return "Info: Content with title '{$snippet['title']}' already exists in the database.";
        }
        
        $thumbnailUrl = $snippet['thumbnails']['high']['url'] ?? $snippet['thumbnails']['default']['url'] ?? null;
        $maxresThumbnail = $snippet['thumbnails']['maxres']['url'] ?? $snippet['thumbnails']['standard']['url'] ?? $thumbnailUrl;
        
        $duration = parseDuration($contentDetails['duration'] ?? '');
        
        $publishedYear = isset($snippet['publishedAt']) ? date('Y', strtotime($snippet['publishedAt'])) : null;
        
        $rating = isset($video['statistics']['likeCount']) ? calculateRating($video['statistics']) : 5.0;
        
        $stmt = $pdo->prepare("INSERT INTO content (type, title, description, poster_url, thumbnail_url, release_year, rating, duration, parental_rating) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $contentType,
            $snippet['title'],
            $snippet['description'] ?? '',
            $thumbnailUrl,
            $maxresThumbnail,
            $publishedYear,
            $rating,
            $duration,
            'NR'
        ]);
        
        $contentId = $pdo->lastInsertId();
        
        if (!empty($snippet['tags'])) {
            foreach (array_slice($snippet['tags'], 0, 5) as $tag) {
                $stmt = $pdo->prepare("SELECT id FROM genres WHERE name = ?");
                $stmt->execute([ucfirst(strtolower($tag))]);
                
                if (!($genre = $stmt->fetch())) {
                    $stmt = $pdo->prepare("INSERT INTO genres (name) VALUES (?)");
                    $stmt->execute([ucfirst(strtolower($tag))]);
                    $genreId = $pdo->lastInsertId();
                } else {
                    $genreId = $genre['id'];
                }
                
                $stmt = $pdo->prepare("INSERT INTO content_genres (content_id, genre_id) VALUES (?, ?)");
                $stmt->execute([$contentId, $genreId]);
            }
        }
        
        $youtubeUrl = "https://youtu.be/{$videoId}?si={$videoId}&autoplay=1&rel=0";
        
        // Set trailer information for YouTube content
        $trailerUrl = "https://www.youtube.com/embed/{$videoId}?autoplay=1&mute=1&controls=0&showinfo=0&rel=0&modestbranding=1";
        $trailerType = "youtube";
        
        // Add trailer to content record
        $stmt = $pdo->prepare("UPDATE content SET trailer_url = ?, trailer_type = ? WHERE id = ?");
        $stmt->execute([$trailerUrl, $trailerType, $contentId]);
        
        $stmt = $pdo->prepare("INSERT INTO servers (content_id, name, url, quality) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $contentId,
            'YouTube',
            $youtubeUrl,
            $contentDetails['definition'] === 'hd' ? 'HD' : 'SD'
        ]);
        
        $pdo->commit();
        
        return "Success: YouTube content '{$snippet['title']}' was added successfully with YouTube share link.";
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return "Database error: " . $e->getMessage();
    }
}

function parseDuration($isoDuration) {
    if (empty($isoDuration)) return '00:00:00';
    
    preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $isoDuration, $matches);
    
    $hours = isset($matches[1]) ? (int)$matches[1] : 0;
    $minutes = isset($matches[2]) ? (int)$matches[2] : 0;
    $seconds = isset($matches[3]) ? (int)$matches[3] : 0;
    
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

function calculateRating($statistics) {
    $likeCount = isset($statistics['likeCount']) ? (int)$statistics['likeCount'] : 0;
    $viewCount = isset($statistics['viewCount']) ? (int)$statistics['viewCount'] : 1;
    
    if ($viewCount == 0) return 5.0;
    
    $ratio = $likeCount / $viewCount;
    $rating = min(10, max(1, $ratio * 100));
    
    return round($rating, 1);
}
?>
