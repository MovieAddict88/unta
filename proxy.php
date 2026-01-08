<?php
// Function to get the base URL from a full URL
function get_base_url($url) {
    $parts = parse_url($url);
    $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $host = isset($parts['host']) ? $parts['host'] : '';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path = isset($parts['path']) ? dirname($parts['path']) : '';
    return rtrim("$scheme$host$port$path", '/') . '/';
}

// Get the target URL from the query string
$target_url = isset($_GET['url']) ? $_GET['url'] : null;

if (!$target_url || !filter_var($target_url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => "A valid 'url' parameter is required."]);
    exit;
}

// Determine if the target is a playlist or a binary segment
$path_info = pathinfo(parse_url($target_url, PHP_URL_PATH));
$extension = isset($path_info['extension']) ? strtolower($path_info['extension']) : '';
$is_playlist = $extension === 'm3u8';

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $target_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Set a 15-second timeout
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Set a 5-second connection timeout
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

// Execute the cURL request
$content = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$curl_error_num = curl_errno($ch);
$curl_error_msg = curl_error($ch);
curl_close($ch);

// Check for cURL-specific errors (e.g., timeouts, connection refused)
if ($curl_error_num > 0) {
    http_response_code(504); // Gateway Timeout
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Proxy failed to fetch content.',
        'details' => "cURL Error ($curl_error_num): " . $curl_error_msg
    ]);
    exit;
}

// Check for HTTP errors from the remote server
if ($http_code < 200 || $http_code >= 300) {
    http_response_code($http_code);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Remote server returned an error.',
        'details' => "HTTP Status Code: $http_code"
    ]);
    exit;
}

// Process the content based on its type
if ($is_playlist) {
    $base_url = get_base_url($target_url);
    $lines = explode("\n", $content);
    $output = '';

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        if (preg_match('/^#EXT-X-KEY:.*URI="([^"]+)"/i', $line, $matches)) {
            $key_uri = $matches[1];
            if (substr($key_uri, 0, 4) !== 'http') {
                $key_uri = $base_url . $key_uri;
            }
            $proxied_key_uri = 'proxy.php?url=' . urlencode($key_uri);
            $line = str_replace($matches[1], $proxied_key_uri, $line);
            $output .= $line . "\n";
        } elseif (substr($line, 0, 1) !== '#') {
            $segment_url = $line;
            if (substr($segment_url, 0, 4) !== 'http') {
                $segment_url = $base_url . $segment_url;
            }
            $output .= 'proxy.php?url=' . urlencode($segment_url) . "\n";
        } else {
            $output .= $line . "\n";
        }
    }

    header('Content-Type: application/vnd.apple.mpegurl');
    echo $output;

} else {
    header('Content-Type: ' . ($content_type ?: 'application/octet-stream'));
    header('Content-Length: ' . strlen($content));
    echo $content;
}
?>