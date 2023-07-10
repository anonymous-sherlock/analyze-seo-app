<?php
function getSitemapUrl($domain)
{
    $sitemapUrls = [];

    // Generate possible sitemap URLs
    $possibleSitemapUrls = [
        "https://{$domain}/sitemap.xml",
        "https://{$domain}/sitemap.txt",
        "https://{$domain}/sitemap",
        "https://{$domain}/sitemap_index.xml",
        "https://{$domain}/sitemap_index.txt",
        "https://{$domain}/sitemap_index",
        "https://{$domain}/sitemap_index.html",
        "https://{$domain}/sitemap.xml.gz",
        "https://{$domain}/sitemap.xml.zip",
        "https://{$domain}/sitemap.xml.tar",
        "https://{$domain}/sitemap.xml.rar",
        "https://{$domain}/sitemap.rss",
        "https://{$domain}/sitemap.res",
    ];

    $multiHandle = curl_multi_init();
    $curlHandles = [];

    // Create curl handles for each possible sitemap URL
    foreach ($possibleSitemapUrls as $possibleUrl) {
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $possibleUrl);
        curl_setopt($handle, CURLOPT_HEADER, true);
        curl_setopt($handle, CURLOPT_NOBODY, true);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_multi_add_handle($multiHandle, $handle);
        $curlHandles[$possibleUrl] = $handle;
    }

    // Execute the multi-handle requests
    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);

    // Process the results
    foreach ($curlHandles as $possibleUrl => $handle) {
        $responseCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        if ($responseCode == 200) {
            $sitemapUrls[] = $possibleUrl;
        }
        curl_multi_remove_handle($multiHandle, $handle);
        curl_close($handle);
    }

    curl_multi_close($multiHandle);

    // Check the robots.txt file for sitemap location
    $robotsUrl = "https://{$domain}/robots.txt";
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $robotsUrl);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $robotsContent = curl_exec($curl);
    curl_close($curl);

    if ($robotsContent) {
        $matches = [];
        if (preg_match_all('/sitemap:\s*(.*)/i', $robotsContent, $matches)) {
            $robotsSitemapUrls = $matches[1];
            foreach ($robotsSitemapUrls as $sitemapUrl) {
                $sitemapUrl = trim($sitemapUrl);
                if (!in_array($sitemapUrl, $sitemapUrls)) {
                    $sitemapUrls[] = $sitemapUrl;
                }
            }
        }
    }

    return $sitemapUrls;
}
?>