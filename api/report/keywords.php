<?php
require_once(__DIR__ . '/../../vendor/autoload.php');
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Utils;
use Spatie\Crawler\Crawler;
use Spatie\Crawler\CrawlObservers\SitemapObserver;
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

$html = fetchHTML($url);
libxml_use_internal_errors(true); // Ignore any HTML parsing errors
$dom = new DOMDocument();
$dom->preserveWhiteSpace = false;
$dom->formatOutput = false;
$dom->loadHTML($html);
libxml_use_internal_errors(false);
$xpath = new DOMXPath($dom);

// keyword extraction start here
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

function preprocessText($text)
{
    // Convert text to lowercase
    $text = strtolower($text);

    // Remove punctuation
    $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);

    // You can add additional preprocessing steps here if needed

    return $text;
}

// Calculate TF-IDF scores
function calculateTFIDF($documents)
{
    $wordCounts = [];
    $documentCount = count($documents);

    // Count the frequency of each word in each document
    foreach ($documents as $document) {
        $document = preprocessText($document);
        $words = explode(' ', $document);
        $uniqueWords = array_unique($words);

        foreach ($uniqueWords as $word) {
            if (!isset($wordCounts[$word])) {
                $wordCounts[$word] = 0;
            }

            $wordCounts[$word]++;
        }
    }

    $keywords = [];

    // Calculate TF-IDF score for each word
    foreach ($wordCounts as $word => $count) {
        $tf = $count / $documentCount;
        $idf = log($documentCount / $count);
        $tfidf = $tf * $idf;

        $keywords[] = [
            'keyword' => $word,
            'score' => $tfidf
        ];
    }

    // Sort the keywords by their TF-IDF scores
    usort($keywords, function ($a, $b) {
        return $b['score'] - $a['score'];
    });

    return $keywords;
}


$text = extractTextFromHTML($dom, $xpath);

// Split the text into documents (if needed)
$documents = str_split($text, 1500); // Split into 1500-word chunks

// Calculate TF-IDF scores for the documents
$keywords = calculateTFIDF($documents);

// Create an array to store the keyword data
$keywordData = [];

// Store the keywords and their scores in the array
foreach ($keywords as $keywordItem) {
    $keyword = $keywordItem['keyword'];
    $score = $keywordItem['score'];

    // Add the keyword data to the array
    $keywordData[] = [
        'keyword' => $keyword,
        'score' => $score
    ];
}

// Create a response array
$response = [
    'keywords' => $keywordData
];

// Convert the response array to JSON
$jsonResponse = json_encode($response);

// Send the JSON response
header('Content-Type: application/json');
echo $jsonResponse;


?>