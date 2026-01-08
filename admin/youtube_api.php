<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../config.php';
require_once 'youtube_handler.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'search_youtube') {
    $query = $_GET['query'] ?? '';
    $contentType = $_GET['type'] ?? 'video';
    $apiKey = $_GET['apiKey'] ?? 'AIzaSyCuDFW3lSVrvc-nGUeQOkM7h_f_MA90NwY';
    
    if (empty($query)) {
        echo json_encode(['error' => 'Query parameter is required']);
        exit;
    }
    
    $results = searchYouTubeVideos($query, $contentType, $apiKey);
    
    if (isset($results['error'])) {
        echo json_encode(['error' => $results['error']]);
        exit;
    }
    
    $formattedResults = [];
    if (isset($results['items'])) {
        foreach ($results['items'] as $item) {
            $snippet = $item['snippet'];
            $contentDetails = $item['contentDetails'] ?? [];
            
            $formattedResults[] = [
                'id' => $item['id'],
                'title' => $snippet['title'],
                'description' => $snippet['description'] ?? '',
                'thumbnail' => $snippet['thumbnails']['high']['url'] ?? $snippet['thumbnails']['default']['url'] ?? '',
                'publishedAt' => $snippet['publishedAt'] ?? '',
                'channelTitle' => $snippet['channelTitle'] ?? '',
                'duration' => $contentDetails['duration'] ?? '',
                'definition' => $contentDetails['definition'] ?? 'sd'
            ];
        }
    }
    
    echo json_encode($formattedResults);
    exit;
}

if ($action === 'add_youtube_content') {
    $videoId = $_POST['video_id'] ?? '';
    $contentType = $_POST['content_type'] ?? 'movie';
    $apiKey = $_POST['apiKey'] ?? 'AIzaSyCuDFW3lSVrvc-nGUeQOkM7h_f_MA90NwY';
    
    if (empty($videoId)) {
        echo json_encode(['status' => 'error', 'message' => 'Video ID is required']);
        exit;
    }
    
    $result = addYouTubeContent($videoId, $contentType, $apiKey);
    
    if (strpos($result, 'Success') === 0) {
        echo json_encode(['status' => 'success', 'message' => $result]);
    } elseif (strpos($result, 'Info') === 0) {
        echo json_encode(['status' => 'info', 'message' => $result]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $result]);
    }
    exit;
}

echo json_encode(['error' => 'Invalid action']);
?>
