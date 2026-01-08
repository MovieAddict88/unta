<?php
// This file will handle TMDB API interactions and database insertions.
require_once '../config.php';

// Function to fetch data from TMDB API
function fetchTMDB($endpoint, $apiKey, $params = []) {
    $base_url = "https://api.themoviedb.org/3";
    $params['api_key'] = $apiKey;
    if (!isset($params['append_to_response'])) {
        $params['append_to_response'] = 'credits,videos,release_dates,content_ratings';
    }
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
        return ['error' => 'TMDB API request failed', 'status_code' => $httpcode, 'response' => json_decode($output, true)];
    }
    return json_decode($output, true);
}

// Function to generate the final URL based on a base URL and content type
function generate_final_url($base_url, $type, $tmdb_id, $season = null, $episode = null) {
    $host = parse_url($base_url, PHP_URL_HOST);
    $base_url = rtrim($base_url, '/');

    // Default pattern for many servers
    $default_movie_path = "/movie/{$tmdb_id}";
    $default_tv_path = "/tv/{$tmdb_id}/{$season}/{$episode}";

    switch (true) {
        case strpos($host, 'multiembed.mov') !== false:
            return $type === 'movie'
                ? "{$base_url}?video_id={$tmdb_id}&content_type=movie"
                : "{$base_url}?video_id={$tmdb_id}&tmdb=1&s={$season}&e={$episode}";

        case strpos($host, 'vidsrc.win') !== false:
            return $type === 'movie'
                ? "{$base_url}/movie.html?id={$tmdb_id}"
                : "{$base_url}/tv?id={$tmdb_id}&s={$season}&e={$episode}";

        case strpos($host, 'moviesapi.club') !== false:
        case strpos($host, 'dbgo.fun') !== false:
            return $type === 'movie'
                ? "{$base_url}/{$tmdb_id}"
                : "{$base_url}/{$tmdb_id}?s={$season}&e={$episode}";

        case strpos($host, 'flixhq.to') !== false:
        case strpos($host, 'gomovies.sx') !== false:
            return $type === 'movie'
                ? "{$base_url}/movie/{$tmdb_id}"
                : "{$base_url}/tv/{$tmdb_id}/{$season}/{$episode}";

        case strpos($host, 'streamwish.to') !== false:
        case strpos($host, 'doodstream.com') !== false:
            return $type === 'movie'
                ? "{$base_url}/e/{$tmdb_id}"
                : "{$base_url}/e/{$tmdb_id}_s{$season}e{$episode}";

        case strpos($host, 'vidplus.to') !== false:
        case strpos($host, 'vidfast.pro') !== false:
        case strpos($host, 'vidlink.pro') !== false:
             return $type === 'movie'
                ? "{$base_url}/movie/{$tmdb_id}?autoplay=true"
                : "{$base_url}/tv/{$tmdb_id}/{$season}/{$episode}?autoplay=true";

        case strpos($host, 'godriveplayer.com') !== false:
        case strpos($host, '2embed.cc') !== false:
             return $type === 'movie'
                ? "{$base_url}/embed/{$tmdb_id}"
                : "{$base_url}/embed/{$tmdb_id}?s={$season}&e={$episode}";

        // Default cases for most embed sites
        case strpos($host, 'vidsrc.net') !== false:
        case strpos($host, 'vidjoy.pro') !== false:
        case strpos($host, 'embed.su') !== false:
        case strpos($host, 'vidsrc.me') !== false:
        case strpos($host, 'player.autoembed.cc') !== false:
        case strpos($host, 'vidsrc.to') !== false:
        case strpos($host, 'vidsrc.xyz') !== false:
        case strpos($host, 'embedsoap.com') !== false:
        case strpos($host, 'showbox.media') !== false:
        case strpos($host, 'primewire.mx') !== false:
        case strpos($host, 'hdtoday.tv') !== false:
        case strpos($host, 'vidcloud.to') !== false:
        case strpos($host, '2embed.stream') !== false:
        case strpos($host, 'player.videasy.net') !== false:
            return $type === 'movie'
                ? "{$base_url}{$default_movie_path}"
                : "{$base_url}{$default_tv_path}";

        default:
            // Fallback for any other server not explicitly handled
            return $type === 'movie'
                ? "{$base_url}{$default_movie_path}"
                : "{$base_url}{$default_tv_path}";
    }
}


// Function to add a movie from TMDB to the database
function addMovieFromTmdb($tmdbId) {
    $apiKey = 'ec926176bf467b3f7735e3154238c161';
    $movieData = fetchTMDB("/movie/{$tmdbId}", $apiKey);
    if (!$movieData || !empty($movieData['error'])) {
        return "Error: Could not fetch data for TMDB ID: {$tmdbId}. The ID might be invalid or the API key is wrong.";
    }
    $pdo = connect_db();
    if (!$pdo) return "Error: Database connection failed.";

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id FROM content WHERE tmdb_id = ? AND type = 'movie'");
        $stmt->execute([$tmdbId]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            return "Info: Movie with TMDB ID {$tmdbId} already exists in the database.";
        }

        $server_stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'auto_embed_servers'");
        $server_stmt->execute();
        $servers_json = $server_stmt->fetchColumn();
        $all_servers = $servers_json ? json_decode($servers_json, true) : [];
        $enabled_servers = array_filter($all_servers, function($server) { return !empty($server['enabled']); });
        $server_count = 0;

        $stmt = $pdo->prepare("INSERT INTO content (tmdb_id, type, title, description, poster_url, thumbnail_url, release_year, rating, duration, parental_rating, trailer_url, trailer_type) VALUES (?, 'movie', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Get trailer information
        $trailerInfo = getMovieTrailer($movieData);
        
        $stmt->execute([ $tmdbId, $movieData['title'], $movieData['overview'],
            isset($movieData['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $movieData['poster_path'] : null,
            isset($movieData['backdrop_path']) ? 'https://image.tmdb.org/t/p/w1280' . $movieData['backdrop_path'] : null,
            isset($movieData['release_date']) ? date('Y', strtotime($movieData['release_date'])) : null,
            $movieData['vote_average'], $movieData['runtime'] ? gmdate("H:i:s", $movieData['runtime'] * 60) : null, 
            getMovieCertification($movieData),
            $trailerInfo['url'],
            $trailerInfo['type']
        ]);
        $contentId = $pdo->lastInsertId();

        if (!empty($movieData['genres'])) {
            foreach ($movieData['genres'] as $genreData) {
                $stmt = $pdo->prepare("SELECT id FROM genres WHERE name = ?"); $stmt->execute([$genreData['name']]);
                if (!($genre = $stmt->fetch())) {
                    $stmt = $pdo->prepare("INSERT INTO genres (name) VALUES (?)"); $stmt->execute([$genreData['name']]);
                    $genreId = $pdo->lastInsertId();
                } else { $genreId = $genre['id']; }
                $stmt = $pdo->prepare("INSERT INTO content_genres (content_id, genre_id) VALUES (?, ?)"); $stmt->execute([$contentId, $genreId]);
            }
        }

        if (!empty($enabled_servers)) {
            $server_insert_stmt = $pdo->prepare("INSERT INTO servers (content_id, name, url, quality) VALUES (?, ?, ?, ?)");
            foreach ($enabled_servers as $server) {
                $host = parse_url($server['url'], PHP_URL_HOST) ?? 'Auto Server';
                $final_url = generate_final_url($server['url'], 'movie', $tmdbId);
                $server_insert_stmt->execute([$contentId, $host, $final_url, 'HD']);
                $server_count++;
            }
        }

        $pdo->commit();
        $message = "Success: Movie '{$movieData['title']}' was added.";
        if ($server_count > 0) {
            $message .= " {$server_count} embed link(s) auto-generated from enabled servers.";
        } else {
            $message .= " Warning: No enabled servers found in Settings. No embed links generated.";
        }
        return $message;
    } catch (Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        return "Database error: " . $e->getMessage();
    }
}

// Function to get movie trailer information
function getMovieTrailer($movieData) {
    $trailerUrl = null;
    $trailerType = null;
    
    // Check if videos data is available in the movie data
    if (isset($movieData['videos']['results']) && !empty($movieData['videos']['results'])) {
        // Look for trailers first
        foreach ($movieData['videos']['results'] as $video) {
            if ($video['type'] === 'Trailer') {
                if ($video['site'] === 'YouTube') {
                    $trailerUrl = 'https://www.youtube.com/embed/' . $video['key'];
                    $trailerType = 'youtube';
                    break;
                } elseif ($video['site'] === 'Vimeo') {
                    $trailerUrl = 'https://player.vimeo.com/video/' . $video['key'];
                    $trailerType = 'vimeo';
                    break;
                }
            }
        }
        
        // If no trailer found, look for teasers or other videos
        if (!$trailerUrl) {
            foreach ($movieData['videos']['results'] as $video) {
                if (in_array($video['type'], ['Teaser', 'Featurette'])) {
                    if ($video['site'] === 'YouTube') {
                        $trailerUrl = 'https://www.youtube.com/embed/' . $video['key'];
                        $trailerType = 'youtube';
                        break;
                    } elseif ($video['site'] === 'Vimeo') {
                        $trailerUrl = 'https://player.vimeo.com/video/' . $video['key'];
                        $trailerType = 'vimeo';
                        break;
                    }
                }
            }
        }
        
        // If still no trailer, take the first available video
        if (!$trailerUrl && !empty($movieData['videos']['results'])) {
            $video = $movieData['videos']['results'][0];
            if ($video['site'] === 'YouTube') {
                $trailerUrl = 'https://www.youtube.com/embed/' . $video['key'];
                $trailerType = 'youtube';
            } elseif ($video['site'] === 'Vimeo') {
                $trailerUrl = 'https://player.vimeo.com/video/' . $video['key'];
                $trailerType = 'vimeo';
            }
        }
    }
    
    return [
        'url' => $trailerUrl,
        'type' => $trailerType
    ];
}

// Function to get series trailer information
function getSeriesTrailer($seriesData) {
    $trailerUrl = null;
    $trailerType = null;
    
    // Check if videos data is available in the series data
    if (isset($seriesData['videos']['results']) && !empty($seriesData['videos']['results'])) {
        // Look for trailers first
        foreach ($seriesData['videos']['results'] as $video) {
            if ($video['type'] === 'Trailer') {
                if ($video['site'] === 'YouTube') {
                    $trailerUrl = 'https://www.youtube.com/embed/' . $video['key'];
                    $trailerType = 'youtube';
                    break;
                } elseif ($video['site'] === 'Vimeo') {
                    $trailerUrl = 'https://player.vimeo.com/video/' . $video['key'];
                    $trailerType = 'vimeo';
                    break;
                }
            }
        }
        
        // If no trailer found, look for teasers or other videos
        if (!$trailerUrl) {
            foreach ($seriesData['videos']['results'] as $video) {
                if (in_array($video['type'], ['Teaser', 'Featurette'])) {
                    if ($video['site'] === 'YouTube') {
                        $trailerUrl = 'https://www.youtube.com/embed/' . $video['key'];
                        $trailerType = 'youtube';
                        break;
                    } elseif ($video['site'] === 'Vimeo') {
                        $trailerUrl = 'https://player.vimeo.com/video/' . $video['key'];
                        $trailerType = 'vimeo';
                        break;
                    }
                }
            }
        }
        
        // If still no trailer, take the first available video
        if (!$trailerUrl && !empty($seriesData['videos']['results'])) {
            $video = $seriesData['videos']['results'][0];
            if ($video['site'] === 'YouTube') {
                $trailerUrl = 'https://www.youtube.com/embed/' . $video['key'];
                $trailerType = 'youtube';
            } elseif ($video['site'] === 'Vimeo') {
                $trailerUrl = 'https://player.vimeo.com/video/' . $video['key'];
                $trailerType = 'vimeo';
            }
        }
    }
    
    return [
        'url' => $trailerUrl,
        'type' => $trailerType
    ];
}

function getMovieCertification($movieData) {
    if (isset($movieData['release_dates']['results'])) {
        foreach ($movieData['release_dates']['results'] as $result) {
            if ($result['iso_3166_1'] == 'US') {
                foreach($result['release_dates'] as $release) { if($release['type'] == 3 && !empty($release['certification'])) return $release['certification']; }
            }
        }
    }
    return 'NR';
}

function getSeriesCertification($seriesData) {
    if (isset($seriesData['content_ratings']['results'])) {
        foreach ($seriesData['content_ratings']['results'] as $result) {
            if ($result['iso_3166_1'] == 'US' && !empty($result['rating'])) return $result['rating'];
        }
    }
    return 'NR';
}

function addSeriesFromTmdb($tmdbId, $seasonsInput = '') {
    $apiKey = 'ec926176bf467b3f7735e3154238c161';
    $seriesData = fetchTMDB("/tv/{$tmdbId}", $apiKey);
    if (!$seriesData || !empty($seriesData['error'])) return "Error: Could not fetch data for TV Series TMDB ID: {$tmdbId}.";
    $pdo = connect_db();
    if (!$pdo) return "Error: Database connection failed.";

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id FROM content WHERE tmdb_id = ? AND type = 'series'");
        $stmt->execute([$tmdbId]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            return "Info: Series with TMDB ID {$tmdbId} already exists.";
        }

        $server_stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'auto_embed_servers'");
        $server_stmt->execute();
        $servers_json = $server_stmt->fetchColumn();
        $all_servers = $servers_json ? json_decode($servers_json, true) : [];
        $enabled_servers = array_filter($all_servers, function($server) { return !empty($server['enabled']); });
        $server_count = 0;

        $stmt = $pdo->prepare("INSERT INTO content (tmdb_id, type, title, description, poster_url, thumbnail_url, release_year, rating, parental_rating, trailer_url, trailer_type) VALUES (?, 'series', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Get trailer information
        $trailerInfo = getSeriesTrailer($seriesData);
        
        $stmt->execute([$tmdbId, $seriesData['name'], $seriesData['overview'],
            isset($seriesData['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $seriesData['poster_path'] : null,
            isset($seriesData['backdrop_path']) ? 'https://image.tmdb.org/t/p/w1280' . $seriesData['backdrop_path'] : null,
            isset($seriesData['first_air_date']) ? date('Y', strtotime($seriesData['first_air_date'])) : null,
            $seriesData['vote_average'], getSeriesCertification($seriesData),
            $trailerInfo['url'],
            $trailerInfo['type']
        ]);
        $contentId = $pdo->lastInsertId();

        if (!empty($seriesData['genres'])) {
            foreach ($seriesData['genres'] as $genreData) {
                $stmt = $pdo->prepare("SELECT id FROM genres WHERE name = ?"); $stmt->execute([$genreData['name']]);
                if(!($genre = $stmt->fetch())) {
                    $stmt = $pdo->prepare("INSERT INTO genres (name) VALUES (?)"); $stmt->execute([$genreData['name']]);
                    $genreId = $pdo->lastInsertId();
                } else { $genreId = $genre['id']; }
                $stmt = $pdo->prepare("INSERT INTO content_genres (content_id, genre_id) VALUES (?, ?)"); $stmt->execute([$contentId, $genreId]);
            }
        }

        $seasonsToFetch = [];
        if (!empty($seasonsInput)) {
            $seasonsToFetch = array_map('intval', explode(',', $seasonsInput));
        } else {
            foreach ($seriesData['seasons'] as $season) { if ($season['season_number'] > 0) $seasonsToFetch[] = $season['season_number']; }
        }

        if (!empty($enabled_servers)) {
            $server_insert_stmt = $pdo->prepare("INSERT INTO servers (episode_id, name, url, quality) VALUES (?, ?, ?, ?)");
        }

        foreach ($seasonsToFetch as $seasonNumber) {
            $seasonData = fetchTMDB("/tv/{$tmdbId}/season/{$seasonNumber}", $apiKey);
            if (!$seasonData || !empty($seasonData['error'])) continue;
            $stmt = $pdo->prepare("INSERT INTO seasons (content_id, season_number, title, poster_url) VALUES (?, ?, ?, ?)");
            $stmt->execute([$contentId, $seasonNumber, $seasonData['name'], isset($seasonData['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $seasonData['poster_path'] : null]);
            $seasonId = $pdo->lastInsertId();

            if (!empty($seasonData['episodes'])) {
                foreach ($seasonData['episodes'] as $episodeData) {
                    $stmt = $pdo->prepare("INSERT INTO episodes (season_id, episode_number, title, description, thumbnail_url, duration) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$seasonId, $episodeData['episode_number'], $episodeData['name'], $episodeData['overview'],
                        isset($episodeData['still_path']) ? 'https://image.tmdb.org/t/p/w780' . $episodeData['still_path'] : null,
                        $episodeData['runtime'] ? gmdate("H:i:s", $episodeData['runtime'] * 60) : null
                    ]);
                    $episodeId = $pdo->lastInsertId();

                    if (!empty($enabled_servers)) {
                        foreach ($enabled_servers as $server) {
                            $host = parse_url($server['url'], PHP_URL_HOST) ?? 'Auto Server';
                            $final_url = generate_final_url($server['url'], 'tv', $tmdbId, $seasonNumber, $episodeData['episode_number']);
                            $server_insert_stmt->execute([$episodeId, $host, $final_url, 'HD']);
                            $server_count++;
                        }
                    }
                }
            }
        }
        $pdo->commit();
        $message = "Success: Series '{$seriesData['name']}' and its seasons/episodes were added.";
        if ($server_count > 0) {
            $message .= " {$server_count} embed link(s) auto-generated from enabled servers.";
        } else {
            $message .= " Warning: No enabled servers found in Settings. No embed links generated.";
        }
        return $message;
    } catch (Exception $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        return "Database error: " . $e->getMessage();
    }
}
?>
