<?php
require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
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
  echo json_encode(['error' => 'Invalid URL']);
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

$start = microtime(true); // Put it from the begining of the page
$html = fetchHTML($url);
$finish = microtime(true); // Put it in the very end of the page
$total_time = round(($finish - $start), 2);
$loadtime = 'Page generated in ' . $total_time . ' seconds.';

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
function getHttpRequestsByType($dom, $xpath, $domain)
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
    'images' => ['img', 'src', 'alt'],
    'javascript' => ['script', 'src'],
    'css' => ['link', 'href', 'rel', 'stylesheet'],
  ];

  foreach ($resourceTypes as $resourceType => $attributes) {
    $nodes = $dom->getElementsByTagName($attributes[0]);
    foreach ($nodes as $node) {
      $attributeValue = $node->getAttribute($attributes[1]);
      if (!empty($attributeValue)) {
        if (count($attributes) > 2) {
          if (count($attributes) > 3) {
            $rel = $node->getAttribute($attributes[2]);
            if ($rel === $attributes[3]) {
              $requests['Resources'][$resourceType][] = $attributeValue;
              $requests['totalRequests']++;
            }
          } else {
            $requests['Resources'][$resourceType][] = $attributeValue;
            $requests['totalRequests']++;
          }
        } else {
          $requests['Resources'][$resourceType][] = $attributeValue;
          $requests['totalRequests']++;
        }
      }
    }
  }

  return $requests;
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
$hasHttp2 = isHttp2Enabled($url);
$socialMediaProfiles = getSocialMediaProfiles($xpath);
$deprecatedTags = checkDeprecatedHTMLTags($xpath);
$sslInfo = getSSLCertificateInfo($domain);
$httpRequests = getHttpRequestsByType($dom, $xpath, $domain);
$plaintextEmails = extractPlaintextEmails($html);
$inlineCSS = extractInlineCSS($xpath);
$socialMediaMetaTags = extractSocialMediaMetaTags($xpath);
$structuredData = extractStructuredData($xpath);



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
    curl_close($ch);
    return false;
  }
  $redirectUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
  curl_close($ch);
  
  if ($redirectUrl !== $url && rtrim($redirectUrl, '/') === $url) {
    return $redirectUrl;
  } else {
    return false;
  }
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











function extractTextFromHTML($html)
{
  // Create a DOMDocument object and load the HTML
  $dom = new DOMDocument();

  // Suppress warnings and errors for invalid HTML
  libxml_use_internal_errors(true);
  $dom->loadHTML($html);
  libxml_clear_errors();

  // Create a DOMXPath object to query the document
  $xpath = new DOMXPath($dom);

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
  $text = strip_tags($dom->saveHTML());

  // Remove extra spaces
  $text = preg_replace('/\s+/', ' ', $text);

  // Remove punctuation
  // $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);

  // Remove extra full stops
  // $text = preg_replace('/\.{2,}/', '.', $text);

  return trim($text);
}

$stopwords = json_decode(file_get_contents('stopword/en.json'), true);
$text = extractTextFromHTML($html);
$wordCount = str_word_count($text);


// $keywords = RakePlus::create($text, $stopwords)->keywords();

// Extract most common keyword using RakePlus
// $mostCommonKeyword = $keywords;







function extract_keywords($str, $minWordLen = 3, $minWordOccurrences = 2, $asArray = false)
{
  function keyword_count_sort($first, $sec)
  {
    return $sec[1] - $first[1];
  }
  $str = preg_replace('/[^\p{L}0-9 ]/', ' ', $str);
  $str = trim(preg_replace('/\s+/', ' ', $str));

  $words = explode(' ', $str);
  $keywords = array();
  while (($c_word = array_shift($words)) !== null) {
    if (strlen($c_word) < $minWordLen)
      continue;

    $c_word = strtolower($c_word);
    if (array_key_exists($c_word, $keywords))
      $keywords[$c_word][1]++;
    else
      $keywords[$c_word] = array($c_word, 1);
  }
  usort($keywords, 'keyword_count_sort');

  $final_keywords = array();
  foreach ($keywords as $keyword_det) {
    if ($keyword_det[1] < $minWordOccurrences)
      break;
    array_push($final_keywords, $keyword_det[0]);
  }
  return $final_keywords;
}













// function getNonModernImageURLs($xpath)
// {
//   // Create an array to store non-modern image URLs
//   $nonModernImageURLs = [];

//   // Get all image source attributes using XPath
//   $imageSrcs = $xpath->evaluate('//img/@src');

//   // Create a Guzzle HTTP client
//   $client = new Client();

//   // Check each image source for modern format
//   foreach ($imageSrcs as $imageSrc) {
//     $imageURL = $imageSrc->value;

//     // Check if the image source is a base64-encoded image
//     if (strpos($imageURL, 'data:image') === 0) {
//       continue; // Skip base64-encoded images
//     }

//     // Skip if the image URL is empty
//     if (empty($imageURL)) {
//       continue;
//     }

//     try {
//       // Send an HTTP request to the image URL
//       $response = $client->head($imageURL, ['http_errors' => false]);

//       // Get the response status code
//       $statusCode = $response->getStatusCode();

//       // Check if the response status code indicates success
//       if ($statusCode >= 200 && $statusCode < 300) {
//         // Check if the response Content-Type header indicates a modern image format
//         $contentType = $response->getHeaderLine('Content-Type');
//         if (strpos($contentType, 'image/webp') !== false || strpos($contentType, 'image/avif') !== false || strpos($contentType, 'image/heif') !== false || strpos($contentType, 'image/heic') !== false) {
//           continue; // Skip modern image formats
//         }
//       } else {
//         // Add the non-modern image URL to the array
//         $nonModernImageURLs[] = $imageURL;
//       }
//     } catch (Exception $e) {
//       // Add the non-modern image URL to the array if an exception occurs
//       $nonModernImageURLs[] = $imageURL;
//     }
//   }

//   return $nonModernImageURLs;
// }

// $nonModernImageFormat = getNonModernImageURLs($xpath);


function isHSTSEnabled($url)
{
  $headers = get_headers($url, 1);

  if (isset($headers['Strict-Transport-Security']) || isset($headers['strict-transport-security'])) {
    return true;
  }

  return false;
}

$hsts = isHSTSEnabled($url);
$starttime = microtime(true);
$endtime = microtime(true);
$executionTime = $endtime - $starttime;
// echo "Execution time: " . $executionTime . " seconds\n";



// End output buffering
ob_end_flush();

// Create the final response array
$response = [
  'hasHttp2' => $hasHttp2,
  'hsts' => $hsts,
  'loadTime' => $loadtime,
  // 'nonModernImageFormat' => $nonModernImageFormat,
  'structuredData' => $structuredData,
  'inlineCSS' => $inlineCSS,
  'plaintextEmails' => $plaintextEmails,
  'socialMetaTags' => $socialMediaMetaTags,
  'hasFramesets' => $hasFramesets,
  'httpRequests' => $httpRequests,
  'ssl' => $sslInfo,
  'deprecatedTags' => $deprecatedTags,
  'socialMediaPresence' => $socialMediaProfiles,
  'wordCount' => $wordCount,
  // 'text' => $text,
  // 'mostCommonKeywords' => $mostCommonKeyword,
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