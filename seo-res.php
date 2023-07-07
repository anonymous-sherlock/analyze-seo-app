<?php
require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Utils;

// Initialize the GuzzleHttp client outside the function for connection pooling
$client = new Client();

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
  global $client;
  $promise = $client->getAsync($url, [
    'headers' => [
      'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.82 Safari/537.36',
      'Accept-Encoding' => 'gzip' // Enable gzip compression
    ],
    'timeout' => 5,
  ]);

  $html = '';
  $promise->then(
    function ($response) use (&$html) {
      $body = $response->getBody();

      // Check if the response is gzipped
      $contentEncoding = $response->getHeaderLine('Content-Encoding');
      if ($contentEncoding === 'gzip') {
        // Uncompress the gzipped content
        $body = gzdecode($body);
      }

      $html = (string) $body;
    },
    function ($error) use ($url) {
      // Handle the error and retry the request
      // You can implement your own error handling logic here
      // For example, you can retry the request a certain number of times
      // before returning an error response
      return fetchHTML($url);
    }
  );
  $promise->wait();

  return $html;
}


$startTime = microtime(true);
$html = fetchHTML($url);
$endTime = microtime(true);
$executionTime = $endTime - $startTime;
echo "Execution Time: " . $executionTime . " seconds\n";
// Fetch the HTML content of the provided URL
// Calculate the page size in bytes
$pageSize = strlen($html);


// Create a DOMDocument object and load the HTML
libxml_use_internal_errors(true); // Ignore any HTML parsing errors
$dom = new DOMDocument();
$dom->preserveWhiteSpace = false;
$dom->formatOutput = false;
$dom->loadHTML($html);
libxml_use_internal_errors(false);

// Create a DOMXPath object to query the DOM
$xpath = new DOMXPath($dom);
// Language
$language = $dom->documentElement->getAttribute('lang');
// Favicon
$favicon = '';
$faviconNode = $xpath->query('/html/head/link[@rel="icon" or @rel="shortcut icon"]/@href')->item(0);
if ($faviconNode) {
  $favicon = $faviconNode->nodeValue;
}
// Title
$title = '';
$titleNode = $xpath->query('/html/head/title')->item(0);
if ($titleNode) {
  $title = $titleNode->nodeValue;
}
// Meta description
$description = '';
$descriptionNode = $xpath->query('/html/head/meta[@name="description"]/@content')->item(0);
if ($descriptionNode) {
  $description = $descriptionNode->nodeValue;
}

// Headings
$headings = ['h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => []];
foreach ($headings as $heading => &$value) {
  $headingNodes = $xpath->query("//{$heading}");

  foreach ($headingNodes as $headingNode) {
    $text = $headingNode ? preg_replace('/\s+/', ' ', trim($headingNode->textContent)) : '';

    $value[] = $text;
  }
}


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


function checkUnsafeCrossOriginLinks($domObj, $html, $currentUrl)
{
  $currentDomain = parse_url($currentUrl, PHP_URL_HOST);
  $dom = $domObj;
  libxml_use_internal_errors(true); // Ignore any HTML parsing errors
  // Use a cached DOM object if available, or load the HTML file
  if (!$dom instanceof DOMDocument) {
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = false;
    $dom->loadHTMLFile($html);
  }
  libxml_use_internal_errors(false);
  $xpath = new DOMXPath($dom);
  $unsafeLinks = [];
  $linkNodes = $xpath->query('//a[@href and string-length(@href) > 0]');
  $currentDomainFiltered = preg_quote($currentDomain, '/');

  // Process links in parallel using multiple threads or processes if possible
  foreach ($linkNodes as $linkNode) {
    $href = $linkNode->getAttribute('href');
    if (filter_var($href, FILTER_VALIDATE_URL)) {
      $linkDomain = parse_url($href, PHP_URL_HOST);
      if ($linkDomain !== $currentDomain) {
        // Check for target="_blank" without rel="noopener" or rel="noreferrer"
        $target = $linkNode->getAttribute('target');
        $rel = $linkNode->getAttribute('rel');
        if ($target === '_blank' && (empty($rel) || strpos($rel, 'noopener') === false)) {
          $unsafeLinks[] = [
            'url' => $href,
            'text' => trim(preg_replace('/\s+/', ' ', $linkNode->textContent))
          ];
        }
      }
    }
  }

  return $unsafeLinks;
}
// spf 
function getSPFRecord($domain)
{
  $spfRecords = dns_get_record($domain, DNS_TXT);
  foreach ($spfRecords as $record) {
    if (stripos($record['txt'], 'v=spf1') !== false) {
      return $record['txt'];
    }
  }
  return false; // SPF record not found
}
// Checking for no index follow
function hasMetaTag($xpathGlobal, $attribute, $values)
{
  $xpath = $xpathGlobal;
  $query = "//meta[@{$attribute}='robots']";
  $metaTags = $xpath->query($query);

  if ($metaTags->length > 0) {
    $content = $metaTags->item(0)->getAttribute('content');
    foreach ($values as $value) {
      if (strpos($content, $value) !== false) {
        return true; // Meta tag exists
      }
    }
  }
  return false; // Meta tag does not exist or values not found
}
function checkRobotsTxt($domain)
{
  $robotsTxtPath = 'https://' . $domain . '/robots.txt';
  global $client;
  try {
    $response = $client->head($robotsTxtPath);
    return $response->getStatusCode() === 200;
  } catch (\Exception $e) {
    return false;
  }
}
function getServerSignature($url)
{
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_NOBODY => true,
    CURLOPT_TIMEOUT => 5, // Set a timeout of 5 seconds
  ]);
  $response = curl_exec($ch);
  $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  curl_close($ch);

  $headers = substr($response, 0, $headerSize);

  foreach (explode("\r\n", $headers) as $header) {
    if (stripos($header, 'Server:') !== false) {
      return trim(substr($header, strlen('Server:')));
    }
  }

  return null;
}
function is404Page($url)
{
  global $client;

  $promises = [
    'response' => $client->headAsync($url, [
      'http_errors' => false,
      'timeout' => 5, // Set a timeout of 5 seconds
    ]),
  ];

  $results = Utils::unwrap($promises);

  $response = $results['response'];
  $statusCode = $response->getStatusCode();

  if ($statusCode === 404) {
    return true; // Custom 404 page exists
  }

  return false; // No custom 404 page
}
function getCharacterEncoding($html)
{
  preg_match('/<meta[^>]+charset=["\']?([a-zA-Z0-9\-_]+)/i', $html, $matches);
  if (isset($matches[1])) {
    return $matches[1]; // Return the character encoding
  }
  return null; // No character encoding declaration found
}
function getViewportContent($xpathGlobal, $html)
{
  $xpath = $xpathGlobal;
  $viewportMeta = $xpath->query('//meta[@name="viewport"]/@content')->item(0);

  if ($viewportMeta !== null) {
    return $viewportMeta->nodeValue;
  }
  return false; // Viewport meta tag does not exist or does not match the desired attributes
}
// Extract the Google Analytics tracking ID from the HTML
function extractTrackingID($html)
{
  $matches = [];
  $pattern = '/UA-\d{4,}-\d{1,}/';
  preg_match($pattern, $html, $matches);
  return isset($matches[0]) ? $matches[0] : false;
}
function getCanonicalUrl($xpath)
{
  $canonicalUrlNode = $xpath->evaluate('string((//link[@rel="canonical"]/@href)[1])');
  if (!empty($canonicalUrlNode)) {
    return $canonicalUrlNode; // Return the canonical URL
  }
  return false; // Canonical URL not found
}


// variable for function 
// Construct the URL for a non-existent page (e.g., example.com/non-existent-page)
$nonExistentPageUrl = rtrim($url, '/') . '/non-existent-page';


// All Function call
$domSize = count($dom->getElementsByTagName('*'));
$redirects = checkURLRedirects($url);
$unsafeLinks = checkUnsafeCrossOriginLinks($dom, $html, $url);
$serverSignature = getServerSignature($url);
$spfRecord = getSPFRecord($domain);
$hasCustom404Page = is404Page($nonExistentPageUrl);
$hasNoFollow = hasMetaTag($xpath, 'name', ['nofollow']);
$hasNoIndex = hasMetaTag($xpath, 'name', ['noindex']);
$hasRobotsTxt = checkRobotsTxt($domain);
$characterEncoding = getCharacterEncoding($html);
$viewportContent = getViewportContent($xpath, $html);
$trackingID = extractTrackingID($html);
$hasCanonicalUrl = getCanonicalUrl($xpath);




// my new code

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
// Extract external links with link text
$externalLinks = [];
$externalLinkNodes = $xpath->query('//a[not(starts-with(@href, "/")) and not(starts-with(@href, "#"))]');
$addedLinks = [];
foreach ($externalLinkNodes as $linkNode) {
  $href = $linkNode->getAttribute('href');
  $text = trim(preg_replace('/\s+/', ' ', $linkNode->textContent));

  if (empty($href) || empty($text)) {
    continue; // Skip if href or text is empty
  }

  $linkParts = parse_url($href);

  // Skip if URL parsing failed
  if (!$linkParts || !isset($linkParts['host'])) {
    continue;
  }
  $linkDomain = $linkParts['host'];

  // Normalize the link domain and current domain for comparison
  $normalizedLinkDomain = rtrim(strtolower($linkDomain), '/');
  $normalizedCurrentDomain = rtrim(strtolower($domain), '/');

  if ($normalizedLinkDomain === $normalizedCurrentDomain) {
    continue; // Skip if link belongs to the same domain
  }

  $href = rtrim($href, '/');

  // Check if the link is already added to internal or external links
  if (isset($addedLinks[$href])) {
    continue; // Skip if link is a duplicate
  }

  $addedLinks[$href] = true;

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
  'serverSignature' => $serverSignature,
  'spfRecord' => $spfRecord,
  'googleTrackingID' => $trackingID,
  'redirects' => $redirects,
  'hasCustom404Page' => $hasCustom404Page,
  'hasNoFollow' => $hasNoFollow,
  'hasNoIndex' => $hasNoIndex,
  'hasRobotsTxt' => $hasRobotsTxt,
  'hasCanonicalUrl' => $hasCanonicalUrl,
  'hasDoctype' => $hasDoctype,
  'language' => $language,
  'characterEncoding' => $characterEncoding,
  'hasViewport' => $viewportContent,
  'title' => $title,
  'description' => $description,
  'favicon' => $favicon,
  'domSize' => $domSize,
  'pageSize' => $pageSize,
  'unsafeLinks' => $unsafeLinks,
  'headings' => $headings,
  'totalImageCount' => $totalImageCount,
  'imagesWithoutAltText' => $imagesWithoutAltText,
  'internalLinks' => $internalLinks,
  'externalLinks' => $externalLinks
];

// Output the response as JSON
echo json_encode($response);
?>