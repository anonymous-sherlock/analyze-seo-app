<?php
$normalizedUrl = rtrim($url, '/') . '/';
$cacheKey = md5($normalizedUrl);
$cachePath = __DIR__ . '/cache/';
$cacheFilePath = $cachePath . $cacheKey . '.json';

// Check if the cache file exists and is readable
if (file_exists($cacheFilePath) && is_readable($cacheFilePath)) {
    $cacheCreationTime = filemtime($cacheFilePath); // Get the cache file's creation time
    $currentTime = time(); // Get the current time
    $cacheLifetime = 10 * 60; // Cache lifetime in seconds (10 minutes)
    $cacheCountLimit = 20; // Maximum number of cache files allowed

    // Check if the cache file count exceeds the limit
    $cacheFiles = glob($cachePath . '*.json');
    if (count($cacheFiles) > $cacheCountLimit) {
        // Delete all cache files
        foreach ($cacheFiles as $file) {
            unlink($file);
        }
    } else {
        // Check if the cache file has expired
        if (($currentTime - $cacheCreationTime) > $cacheLifetime) {
            unlink($cacheFilePath); // Delete the cache file
        } else {
            $cachedResponse = file_get_contents($cacheFilePath);
            if ($cachedResponse !== false) {
                echo $cachedResponse;
                exit;
            }
        }
    }
}

// Set default deletion time for cache files (1 day)
$cacheFiles = glob($cachePath . '*.json');
foreach ($cacheFiles as $file) {
    // Get the file's last modification timestamp
    $fileTimestamp = filemtime($file);

    // Calculate the difference in seconds between the current time and file's modification time
    $secondsPassed = time() - $fileTimestamp;

    // Check if the file has exceeded the default deletion time (1 day)
    if ($secondsPassed > (24 * 60 * 60)) {
        unlink($file); // Delete the cache file
    }
}
?>