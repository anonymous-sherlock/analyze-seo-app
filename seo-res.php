<?php
require __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$url = $_GET['url'];
// Extract the domain from the provided URL
$urlParts = parse_url($url);
$domain = $urlParts['host'];

// Validate and sanitize the URL input
if (!filter_var($url, FILTER_VALIDATE_URL)) {
  echo json_encode(['error' => 'Invalid URL']);
  exit;
}

// Enable output buffering
ob_start();

// Function to fetch the HTML content of a URL using Guzzle HTTP client
function fetchHTML($url)
{
  $client = new \GuzzleHttp\Client();
  $response = $client->get($url, [
    'headers' => [
      'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.82 Safari/537.36'
    ]
  ]);
  $html = (string) $response->getBody();
  return $html;
}

// Function to check for URL redirects and return the redirection path
function checkURLRedirects($url)
{
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_NOBODY, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $response = curl_exec($ch);

  if ($response === false) {
    // Error occurred while making the request
    return false;
  }

  $redirectUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
  curl_close($ch);

  return $redirectUrl;
}
// Function to check if robots.txt exists using Guzzle HTTP client
function checkRobotsTxt($url)
{
  $robotsTxtUrl = rtrim($url, '/') . '/robots.txt';
  $client = new \GuzzleHttp\Client();
  $response = $client->head($robotsTxtUrl);
  $statusCode = $response->getStatusCode();
  return $statusCode === 200;
}

// Function to check if the nofollow meta tag exists
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

// Function to check if the noindex meta tag exists
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

//Continued code:
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
// my new code








function checkUnsafeCrossOriginLinks($html, $currentUrl)
{
  $currentDomain = parse_url($currentUrl, PHP_URL_HOST);

  $dom = new DOMDocument();
  libxml_use_internal_errors(true); // Ignore any HTML parsing errors
  $dom->loadHTML($html);
  libxml_use_internal_errors(false);

  $xpath = new DOMXPath($dom);

  $unsafeLinks = [];
  $linkNodes = $xpath->query('//a[@href]');
  foreach ($linkNodes as $linkNode) {
    $href = $linkNode->getAttribute('href');
    if (!empty($href) && filter_var($href, FILTER_VALIDATE_URL)) {
      $linkDomain = parse_url($href, PHP_URL_HOST);
      if ($linkDomain !== $currentDomain) {
        $unsafeLinks[] = [
          'url' => $href,
          'text' => trim(preg_replace('/\s+/', ' ', $linkNode->textContent))
        ];
      }
    }
  }

  return $unsafeLinks;
}
$unsafeLinks = checkUnsafeCrossOriginLinks($html, $url);





// End output buffering
ob_end_flush();

// Create the final response array
$response = [
  'url' => $url,
  'domain' => $domain,
  'favicon' => $favicon,
  'domSize' => $domSize,
  'pageSize' => $pageSize,
  'unsafeLinks' => $unsafeLinks,
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

// Output the response as JSON
echo json_encode($response);
?>