<?php
require_once(__DIR__ . '/../../vendor/autoload.php');

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$url = $_GET['url'];

// Validate and sanitize the URL input
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['error' => 'Invalid URL']);
    exit;
}

// Extract the domain from the provided URL
$urlParts = parse_url($url);
$domain = $urlParts['host'];

// Enable output buffering
ob_start();

/**
 * Fetches the HTML content of a URL using Guzzle HTTP client.
 *
 * @param string $url The URL to fetch.
 * @return string The fetched HTML content.
 * @throws GuzzleException
 */
function fetchHTML($url)
{
    $client = new Client();
    $response = $client->get($url, [
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.82 Safari/537.36'
        ]
    ]);
    $html = (string) $response->getBody();
    return $html;
}

/**
 * Checks for URL redirects and returns the redirection path.
 *
 * @param string $url The URL to check redirects for.
 * @return string|bool The URL after redirection or false if error occurred.
 * @throws GuzzleException
 */
function checkURLRedirects($url)
{
    try {
        $client = new Client();
        $response = $client->head($url, [
            'allow_redirects' => [
                'track_redirects' => true
            ]
        ]);
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 300 && $statusCode < 400) {
            // Redirect occurred
            return $response->getHeaderLine('Location');
        } else {
            // No redirect
            return false;
        }
    } catch (GuzzleException $e) {
        // Error occurred while making the request
        return false;
    }
}

/**
 * Checks if robots.txt exists using Guzzle HTTP client.
 *
 * @param string $url The URL to check robots.txt for.
 * @return bool True if robots.txt exists, false otherwise.
 * @throws GuzzleException
 */
function checkRobotsTxt($url)
{
    $robotsTxtUrl = rtrim($url, '/') . '/robots.txt';
    $client = new Client();
    try {
        $response = $client->head($robotsTxtUrl);
        $statusCode = $response->getStatusCode();
        return $statusCode === 200;
    } catch (GuzzleException $e) {
        return false;
    }
}

/**
 * Checks if the nofollow meta tag exists in the HTML content.
 *
 * @param string $html The HTML content to check.
 * @return bool True if nofollow meta tag exists, false otherwise.
 */
function hasNofollowTag($html)
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_use_internal_errors(false);

    $xpath = new DOMXPath($dom);
    $metaTag = $xpath->query('//meta[@name="robots" and @content="nofollow"]');
    return $metaTag->length > 0;
}

/**
 * Checks if the noindex meta tag exists in the HTML content.
 *
 * @param string $html The HTML content to check.
 * @return bool True if noindex meta tag exists, false otherwise.
 */
function hasNoindexTag($html)
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_use_internal_errors(false);

    $xpath = new DOMXPath($dom);
    $metaTag = $xpath->query('//meta[@name="robots" and @content="noindex"]');
    return $metaTag->length > 0;
}

// Fetch the HTML content of the provided URL
$html = fetchHTML($url);

// Check if the Robots.txt nofollow, noindex.
$hasRobotsTxt = checkRobotsTxt($url);
$hasNofollow = hasNofollowTag($html);
$hasNoindex = hasNoindexTag($html);

// Check for URL redirects
$redirects = checkURLRedirects($url);

// Calculate the page size in bytes
$pageSize = strlen($html);

// Create a DOMDocument object and load the HTML
$dom = new DOMDocument();
libxml_use_internal_errors(true); // Ignore any HTML parsing errors
$dom->loadHTML($html);
libxml_use_internal_errors(false);

// Create a DOMXPath object to query the DOM
$xpath = new DOMXPath($dom);

// Language
$language = $dom->documentElement->getAttribute('lang');

// Title
$titleNode = $xpath->query('//title')->item(0);
$title = $titleNode ? $titleNode->textContent : '';

// Favicon
$faviconNode = $xpath->query('//link[@rel="icon" or @rel="shortcut icon"]/@href')->item(0);
$favicon = $faviconNode ? $faviconNode->textContent : '';

// Headings
$headings = ['h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => []];

foreach ($headings as $heading => &$value) {
    $headingNodes = $xpath->query("//{$heading}");

    foreach ($headingNodes as $headingNode) {
        $text = $headingNode ? preg_replace('/\s+/', ' ', trim($headingNode->textContent)) : '';

        $value[] = $text;
    }
}

// Meta description
$descriptionNode = $xpath->query('//meta[@name="description"]/@content')->item(0);
$description = $descriptionNode ? $descriptionNode->textContent : '';

// Calculate the DOM size (number of nodes)
$domSize = count($dom->getElementsByTagName('*'));

// Checking for Doctype
$hasDoctype = strpos($html, '<!DOCTYPE html>') !== false;

// Initialize totalImageCount
$totalImageCount = 0;

// Extract images without alt attribute text and total images used on the website
$imagesWithoutAltText = [];
$imageNodes = $dom->getElementsByTagName('img');
foreach ($imageNodes as $imageNode) {
    $src = $imageNode->getAttribute('src');
    if (!empty($src)) {
        // Check if the alt attribute is empty or not present
        $alt = $imageNode->getAttribute('alt');
        if (empty($alt)) {
            $imagesWithoutAltText[] = $src;
        }
        $totalImageCount++;
    }
}

// Extract internal links with link text
$internalLinks = [];
$internalLinkUrls = [];
$internalLinkNodes = $dom->getElementsByTagName('a');
foreach ($internalLinkNodes as $linkNode) {
    $href = $linkNode->getAttribute('href');
    $text = trim(preg_replace('/\s+/', ' ', $linkNode->textContent));

    if (!empty($href) && !empty($text)) {
        // Check if $href is an absolute URL and belongs to the same domain
        if (filter_var($href, FILTER_VALIDATE_URL)) {
            $parsedHref = parse_url($href);
            // Check if the parsed URL matches any of the domain variations
            $parsedUrlHost = isset($parsedHref['host']) ? $parsedHref['host'] : '';
            $originalUrlHost = parse_url($url, PHP_URL_HOST);
            $wwwOriginalUrlHost = 'www.' . $originalUrlHost;

            if ($parsedUrlHost === $originalUrlHost || $parsedUrlHost === $wwwOriginalUrlHost || $wwwOriginalUrlHost === $parsedUrlHost) {
                $fullUrl = $href;
            } else {
                continue; // Skip external URLs
            }
        } else {
            $base = rtrim($url, '/');
            $separator = '/';
            if (substr($href, 0, 1) === '/') {
                $separator = '';
            }
            $fullUrl = $base . $separator . $href;
        }

        $lowercaseUrl = strtolower($fullUrl);

        // Check if the lowercase URL has already been added to the array
        $isInternalLink = isset($internalLinkUrls[$lowercaseUrl]);

        if (!$isInternalLink) {
            $internalLinks[] = [
                'url' => $fullUrl,
                'text' => $text
            ];

            // Add the lowercase URL to the list of added URLs
            $internalLinkUrls[$lowercaseUrl] = true;
        }
    }
}

// Extract external links with link text
$externalLinks = [];
$externalLinkNodes = $dom->getElementsByTagName('a');
foreach ($externalLinkNodes as $linkNode) {
    $href = $linkNode->getAttribute('href');
    $text = trim(preg_replace('/\s+/', ' ', $linkNode->textContent));

    if (empty($href) || empty($text)) {
        continue; // Skip if href or text is empty
    }

    $linkParts = parse_url($href);

    // Skip if URL parsing failed
    if (!$linkParts || !isset($linkParts['scheme'])) {
        continue;
    }

    $scheme = strtolower($linkParts['scheme']);
    if ($scheme !== 'http' && $scheme !== 'https') {
        continue; // Skip non-HTTP URLs
    }

    $externalLinks[] = [
        'url' => $href,
        'text' => $text
    ];
}

// End output buffering
ob_end_flush();

// Create the final response array
$response = [
    'url' => $url,
    'domain' => $domain,
    'favicon' => $favicon,
    'domSize' => $domSize,
    'pageSize' => $pageSize,
    'hasDoctype' => $hasDoctype,
    'language' => $language,
    'title' => $title,
    'description' => $description,
    'hasRobotsTxt' => $hasRobotsTxt,
    'hasNofollow' => $hasNofollow,
    'hasNoindex' => $hasNoindex,
    'redirects' => $redirects,
    'headings' => $headings,
    'totalImageCount' => $totalImageCount,
    'imagesWithoutAltText' => $imagesWithoutAltText,
    'internalLinks' => $internalLinks,
    'externalLinks' => $externalLinks
];

// Output the JSON response
echo json_encode($response);

// End output buffering
ob_end_flush();