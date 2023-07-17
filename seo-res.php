<?php
require_once('vendor/autoload.php');

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Utils;
use DonatelloZa\RakePlus\RakePlus;




// Initialize the GuzzleHttp client outside the function for connection pooling
$client = new Client();

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$url = $_GET['url'];
// Validate and sanitize the URL input
if (!filter_var($url, FILTER_VALIDATE_URL)) {
  echo json_encode(['error' => 'Please Enter a Valid URL.']);
  exit;
}


// Extract the domain from the provided URL
$urlParts = parse_url($url);
$domain = $urlParts['host'];


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
    'http_version' => '2.0',

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
      echo json_encode(['error' => 'Cannot Check SEO of This Website Try Again After Some Time']);
      exit;
    }
  );
  $promise->wait();

  return $html;
}

$start = $_SERVER['REQUEST_TIME_FLOAT'];
$html = fetchHTML($url);
$finish = microtime(true);
$loadTime = round($finish - $start, 2);

// Calculate the page size in bytes
$pageSize = mb_strlen($html, '8bit');

// Fetch the HTML content of the provided URL


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
// checking framset
$hasFramesets = $xpath->evaluate('count(//frameset) > 0') ?: false;

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
  $noIndexContents = [];
  if ($metaTags->length > 0) {
    $content = $metaTags->item(0)->getAttribute('content');
    foreach ($values as $value) {
      if (strpos($content, $value) !== false) {
        if ($value === "noindex") {
          $noIndexContents[] = $content;
          return $noIndexContents;
        }
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
function isHttp2Enabled($url)
{
  $curlInfo = curl_version();
  return ($curlInfo['features'] & CURL_VERSION_HTTP2) !== 0;
}
function getSocialMediaProfiles($xpath)
{
  $socialProfiles = [];

  // Define the social media platforms and their associated domain names
  $socialPlatforms = [
    'facebook' => 'facebook.com',
    'twitter' => 'twitter.com',
    'instagram' => 'instagram.com',
    'linkedin' => 'linkedin.com',
    'youtube' => 'youtube.com',
    'pinterest' => 'pinterest.com',
    'snapchat' => 'snapchat.com',
    'tiktok' => 'tiktok.com',
    'reddit' => 'reddit.com',
    'tumblr' => 'tumblr.com',
    'github' => 'github.com',
    'wordpress' => 'wordpress.com',
    'soundcloud' => 'soundcloud.com',
    'pexels' => 'pexels.com',
    'behance' => 'behance.net',
    'dribbble' => 'dribbble.com',
    'deviantart' => 'deviantart.com',
    'flickr' => 'flickr.com',
    'vimeo' => 'vimeo.com',
    'twitch' => 'twitch.tv',
    'spotify' => 'spotify.com',
    'medium' => 'medium.com',
    'weibo' => 'weibo.com',
    'vk' => 'vk.com',
    'telegram' => 'telegram.org',
    'slack' => 'slack.com',
    'digg' => 'digg.com',
    'quora' => 'quora.com',
    // Add more social media platforms here
  ];

  // Extract all anchor nodes from the HTML
  $anchorNodes = $xpath->query('//a');

  // Iterate over the anchor nodes and extract social media profiles
  foreach ($anchorNodes as $anchorNode) {
    $href = $anchorNode->getAttribute('href');
    if (!empty($href)) {
      foreach ($socialPlatforms as $platform => $domain) {
        if (strpos($href, $domain) !== false) {
          $socialProfiles[$platform] = $href;
          break; // Found the platform, no need to check other platforms
        }
      }
    }
  }

  return $socialProfiles;
}
function checkDeprecatedHTMLTags($xpath)
{
  // Define the deprecated HTML tags
  $deprecatedTags = [
    'acronym',
    'applet',
    'basefont',
    'big',
    'center',
    'dir',
    'font',
    'frame',
    'frameset',
    'isindex',
    'noframes',
    's',
    'strike',
    'tt',
    'u',
    'xmp',
    // Add more deprecated tags here
  ];

  $deprecatedTagCounts = [];

  // Construct the XPath query to select all deprecated tags at once
  $query = "//" . implode(" | //", $deprecatedTags);
  $tagNodes = $xpath->query($query);

  // Count the occurrences of each deprecated tag
  foreach ($tagNodes as $tagNode) {
    $tagName = $tagNode->tagName;
    $deprecatedTagCounts[$tagName] = isset($deprecatedTagCounts[$tagName]) ? $deprecatedTagCounts[$tagName] + 1 : 1;
  }

  return $deprecatedTagCounts;
}
function getSSLCertificateInfo($hostname)
{
  $sslInfo = [];
  $context = stream_context_create([
    'ssl' => [
      'capture_peer_cert' => true,
      'verify_peer' => false,
      'verify_peer_name' => false,
    ]
  ]);

  // Attempt to establish an SSL/TLS connection
  $stream = stream_socket_client("ssl://$hostname:443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
  if ($stream) {
    // Retrieve the peer certificate
    $params = stream_context_get_params($stream);
    $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);

    if ($cert) {
      // Extract the issuer and expiration date
      $issuer = $cert['issuer']['O'] ?? false;
      $expiration = date('Y-m-d H:i:s', $cert['validTo_time_t']);

      // Assign the SSL certificate information
      $sslInfo['issuer'] = $issuer;
      $sslInfo['expiration'] = $expiration;
    }

    // Close the SSL/TLS connection
    fclose($stream);
  }

  return $sslInfo;
}
function extractPlaintextEmails($html)
{
  $pattern = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/';
  preg_match($pattern, $html, $matches);
  return $matches[0] ?? false;
}
function extractInlineCSS($xpath)
{
  $styles = [];

  // Query the "style" attribute of elements
  $styleAttributes = $xpath->query('//*[@style]/@style');

  // Extract the inline CSS from the attribute values
  foreach ($styleAttributes as $styleAttribute) {
    $style = $styleAttribute->nodeValue;
    if (!empty($style)) {
      $styles[] = $style;
    }
  }

  return $styles;
}
function extractSocialMediaMetaTags($xpath)
{
  $metaTags = $xpath->query('/html/head/meta');

  $socialMediaMetaTags = array(
    'openGraph' => null,
    'twitterCard' => null,
    'facebook' => null,
    'pinterest' => null,
    'linkedin' => null,
    'instagram' => null,
    'googlePlus' => null
  );

  foreach ($metaTags as $metaTag) {
    $property = $metaTag->getAttribute('property');
    $name = $metaTag->getAttribute('name');
    $content = $metaTag->getAttribute('content');

    switch (true) {
      case (strpos($property, 'og:') === 0):
        $socialMediaMetaTags['openGraph'][$property] = $content;
        break;
      case (strpos($name, 'twitter:') === 0):
        $socialMediaMetaTags['twitterCard'][$name] = $content;
        break;
      case (strpos($property, 'fb:') === 0):
        $socialMediaMetaTags['facebook'][$property] = $content;
        break;
      case ($name === 'pinterest-rich-pin'):
        $socialMediaMetaTags['pinterest'][$name] = $content;
        break;
      case (strpos($property, 'linkedin:') === 0):
        $socialMediaMetaTags['linkedin'][$property] = $content;
        break;
      case ($name === 'instagram:app_id'):
        $socialMediaMetaTags['instagram'][$name] = $content;
        break;
      case (strpos($name, 'google+:') === 0):
        $socialMediaMetaTags['googlePlus'][$name] = $content;
        break;
    }
  }

  foreach ($socialMediaMetaTags as &$value) {
    if (empty($value)) {
      $value = false;
    }
  }

  return $socialMediaMetaTags;
}
function extractStructuredData($xpath)
{
  // Select the elements containing structured data using XPath
  $nodes = $xpath->query('//script[@type="application/ld+json"]');
  // Array to store the extracted structured data
  $structuredData = [];


  foreach ($nodes as $node) {
    $scriptContent = $node->nodeValue;
    $jsonLdData = json_decode($scriptContent, true);
    if ($jsonLdData !== null && isset($jsonLdData['@context']) && $jsonLdData['@context'] === 'https://schema.org') {
      $structuredData['Schema.org'] = $jsonLdData;
    }
  }
  return $structuredData;
}
function isHSTSEnabled($url)
{
  global $client;

  try {
    $response = $client->head($url, ['http_errors' => false, 'timeout' => 5]);
    $statusCode = $response->getStatusCode();
    $header = $response->getHeaderLine('Strict-Transport-Security');

    if ($statusCode >= 200 && $statusCode < 300 && !empty($header)) {
      return true;
    }
  } catch (RequestException $e) {
    // Handle request exceptions, if necessary
    return false;
  }

  return false;
}
function isNonSeoFriendlyUrl($url)
{
  // Remove the protocol and www prefix
  $url = preg_replace('/^https?:\/\/(www\.)?/', '', $url);

  // Check for non-SEO friendly patterns
  // '/\b\d{8,}\b/'              Numeric strings with 8 or more digits (e.g., product IDs, timestamps)
  // '/[^a-zA-Z0-9\-\/_.]/'      Non-alphanumeric characters excluding allowed characters
  // '/\d+[A-Za-z]+\d+/',        Alphanumeric strings with numbers and letters combined (e.g., abcd1234)
  // '/[A-Za-z]+\d+[A-Za-z]+/'   Alphanumeric strings with letters and numbers combined (e.g., a1b2c3)

  $pattern = '/\b\d{8,}\b|[^a-zA-Z0-9\-\/_.]|\d+[A-Za-z]+\d+|[A-Za-z]+\d+[A-Za-z]+/';

  return preg_match($pattern, $url) === 1;
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

  $mh = curl_multi_init();
  curl_multi_add_handle($mh, $ch);

  $active = null;
  do {
    $status = curl_multi_exec($mh, $active);
    if ($active) {
      curl_multi_select($mh);
    }
  } while ($status === CURLM_CALL_MULTI_PERFORM || $active);

  $response = curl_multi_getcontent($ch);
  $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

  curl_multi_remove_handle($mh, $ch);
  curl_multi_close($mh);

  $headers = substr($response, 0, $headerSize);

  foreach (explode("\r\n", $headers) as $header) {
    if (stripos($header, 'Server:') !== false) {
      return trim(substr($header, strlen('Server:')));
    }
  }

  return null;
}
function checkURLRedirects($url)
{
  $mh = curl_multi_init();

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_NOBODY, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set a timeout of 10 seconds

  curl_multi_add_handle($mh, $ch);

  $active = null;
  do {
    $status = curl_multi_exec($mh, $active);
    if ($active) {
      curl_multi_select($mh);
    }
  } while ($status === CURLM_CALL_MULTI_PERFORM || $active);

  $info = curl_getinfo($ch);
  $finalURL = $info['url'];

  curl_multi_remove_handle($mh, $ch);
  curl_multi_close($mh);

  $urlWithoutSlash = rtrim($url, '/');
  $finalURLWithoutSlash = rtrim($finalURL, '/');

  return $urlWithoutSlash === $finalURLWithoutSlash ? false : $finalURL;
}
function getHttpRequestsByType($dom, $xpath)
{
  $requests = [
    'totalRequests' => 0,
    'Resources' => [
      'images' => [],
      'javascript' => [],
      'css' => [],
    ],
  ];

  $resourceTypes = [
    'images' => ['img', 'src'],
    'javascript' => ['script', 'src'],
    'css' => ['link', 'href', 'rel', 'stylesheet'],
  ];

  foreach ($resourceTypes as $resourceType => $attributes) {
    $nodes = $dom->getElementsByTagName($attributes[0]);
    foreach ($nodes as $node) {
      $attributeValue = $node->getAttribute($attributes[1]);
      if (!empty($attributeValue) && !in_array($attributeValue, $requests['Resources'][$resourceType])) {
        if (count($attributes) > 2) {
          $rel = $node->getAttribute($attributes[2]);
          if (count($attributes) > 3 && $rel !== $attributes[3]) {
            continue;
          }
        }
        $requests['Resources'][$resourceType][] = $attributeValue;
        $requests['totalRequests']++;
      }
    }
  }

  return $requests;
}

// variable for function 
// Construct the URL for a non-existent page (e.g., example.com/non-existent-page)
$nonExistentPageUrl = rtrim($url, '/') . '/non-existent-page';

// All Function call
$domSize = count($dom->getElementsByTagName('*'));
$unsafeLinks = checkUnsafeCrossOriginLinks($dom, $html, $url);
$spfRecord = getSPFRecord($domain);
$hasCustom404Page = is404Page($nonExistentPageUrl);
$hasNoFollow = hasMetaTag($xpath, 'name', ['nofollow']);
$hasNoIndex = hasMetaTag($xpath, 'name', ['noindex']);
$hasRobotsTxt = checkRobotsTxt($domain);
$characterEncoding = getCharacterEncoding($html);
$viewportContent = getViewportContent($xpath, $html);
$trackingID = extractTrackingID($html);
$hasCanonicalUrl = getCanonicalUrl($xpath);
$hasHttp2 = isHttp2Enabled($url);
$socialMediaProfiles = getSocialMediaProfiles($xpath);
$deprecatedTags = checkDeprecatedHTMLTags($xpath);
$sslInfo = getSSLCertificateInfo($domain);
$plaintextEmails = extractPlaintextEmails($html);
$inlineCSS = extractInlineCSS($xpath);
$socialMediaMetaTags = extractSocialMediaMetaTags($xpath);
$structuredData = extractStructuredData($xpath);
$hsts = isHSTSEnabled($url);
$redirects = checkURLRedirects($url);
$serverSignature = getServerSignature($url);
$httpRequests = getHttpRequestsByType($dom, $xpath);


// extract internal and external links 
$nonSEOFriendlyLinks = [];
$internalLinks = [];
$internalLinkUrls = [];
$externalLinks = [];
$addedLinks = [];
$normalizedOriginalUrlHost = strtolower(parse_url($url, PHP_URL_HOST));
$linkNodes = $xpath->query('//a[not(starts-with(@href, "#"))]');
foreach ($linkNodes as $linkNode) {
  $href = $linkNode->getAttribute('href');
  $text = trim(str_replace(["\r", "\n", "\t"], '', $linkNode->textContent));

  if (strpos($href, 'mailto:') === 0 || strpos($href, 'tel:') === 0) {
    continue;
  }

  if (!empty($href) && !empty($text)) {
    if (filter_var($href, FILTER_VALIDATE_URL)) {
      $parsedHref = parse_url($href);
      $parsedUrlHost = strtolower($parsedHref['host'] ?? '');

      if ($parsedUrlHost === $normalizedOriginalUrlHost) {
        $fullUrl = $href;
        $lowercaseUrl = strtolower($fullUrl);

        if (!isset($internalLinkUrls[$lowercaseUrl])) {
          $internalLinks[] = [
            'url' => $fullUrl,
            'text' => $text
          ];

          $internalLinkUrls[$lowercaseUrl] = true;
        }
      } else {
        $fullUrl = rtrim($href, '/');
        $lowercaseUrl = strtolower($fullUrl);

        if (!isset($addedLinks[$lowercaseUrl])) {
          $externalLinks[] = [
            'url' => $fullUrl,
            'text' => $text
          ];

          $addedLinks[$lowercaseUrl] = true;
        }
      }
    } else {
      $fullUrl = rtrim($url, '/') . '/' . ltrim($href, '/');
      $lowercaseUrl = strtolower($fullUrl);

      if (!isset($internalLinkUrls[$lowercaseUrl])) {
        $internalLinks[] = [
          'url' => $fullUrl,
          'text' => $text
        ];

        $internalLinkUrls[$lowercaseUrl] = true;
      }
    }
  }
}
// filter out non seo friendly link from internal array
$nonSEOFriendlyLinks = array_filter($internalLinks, function ($link) {
  return isNonSeoFriendlyUrl($link['url']);
});
$nonSEOFriendlyLinks = $nonSEOFriendlyLinks ? array_column($nonSEOFriendlyLinks, 'url') : false;



$starttime = microtime(true);




$endtime = microtime(true);
$executionTime = $endtime - $starttime;
// echo "Execution time: " . $executionTime . " seconds\n" . PHP_EOL;
function extractTextFromHTML($dom, $xpath)
{
  // Remove inline style elements
  $styleNodes = $xpath->query('//style');
  foreach ($styleNodes as $styleNode) {
    $styleNode->parentNode->removeChild($styleNode);
  }

  // Remove script elements
  $scriptNodes = $xpath->query('//script');
  foreach ($scriptNodes as $scriptNode) {
    $scriptNode->parentNode->removeChild($scriptNode);
  }

  // Get the text content without tags and inline styles
  $text = '';
  $bodyNode = $xpath->query('//body')->item(0);
  if ($bodyNode) {
    $text = $bodyNode->textContent;
  }

  // Remove extra spaces
  $text = preg_replace('/\s+/', ' ', $text);

  return trim($text);
}


$text = extractTextFromHTML($dom, $xpath);
$wordCount = str_word_count($text);
$rake = RakePlus::create($text, 'en_US', 5, true)->keywords();






// End output buffering
ob_end_flush();

// Create the final response array
$response = [
  'hasHttp2' => $hasHttp2,
  'hsts' => $hsts,
  'domSize' => $domSize,
  'pageSize' => $pageSize,
  'loadTime' => $loadTime,
  // 'nonOptimizedImage' => $unoptimizedImages,
  'Keywords' => $rake,
  'wordCount' => $wordCount,
  'httpRequests' => $httpRequests,
  'text' => $text,
  'redirects' => $redirects,
  'nonSEOFriendlyLinks' => $nonSEOFriendlyLinks,
  'internalLinks' => $internalLinks,
  'externalLinks' => $externalLinks,
  'structuredData' => $structuredData,
  'plaintextEmails' => $plaintextEmails,
  'socialMetaTags' => $socialMediaMetaTags,
  'hasFramesets' => $hasFramesets,
  'ssl' => $sslInfo,
  'deprecatedTags' => $deprecatedTags,
  'socialMediaPresence' => $socialMediaProfiles,
  'url' => $url,
  'domain' => $domain,
  'serverSignature' => $serverSignature,
  'spfRecord' => $spfRecord,
  'googleTrackingID' => $trackingID,
  'hasCustom404Page' => $hasCustom404Page,
  'hasNoFollow' => $hasNoFollow,
  'hasNoIndex' => $hasNoIndex,
  'hasRobotsTxt' => $hasRobotsTxt,
  'hasCanonicalUrl' => $hasCanonicalUrl,
  'hasDoctype' => $hasDoctype,
  'inlineCSS' => $inlineCSS,
  'language' => $language,
  'characterEncoding' => $characterEncoding,
  'hasViewport' => $viewportContent,
  'title' => $title,
  'description' => $description,
  'favicon' => $favicon,
  'unsafeLinks' => $unsafeLinks,
  'headings' => $headings,
  'totalImageCount' => $totalImageCount,
  'imagesWithoutAltText' => $imagesWithoutAltText,
];

// Output the response as JSON
echo json_encode($response);
?>