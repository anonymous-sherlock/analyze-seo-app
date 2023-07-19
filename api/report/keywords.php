<?php
require_once(__DIR__ . '/../../vendor/autoload.php');
use StopWord\StopWord;
use tidy;

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$url = $_GET['url'];
// Validate and sanitize the URL input
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['error' => 'Please enter a valid URL.']);
    exit;
}

function fetchHTML($url)
{
    $tidy = new tidy();
    $tidy->parseFile($url, [], 'utf8');
    $tidy->cleanRepair();

    return (string) $tidy;
}

function extractTextFromHTML($html)
{
    // Remove HTML tags and attributes
    $text = strip_tags($html);

    // Remove extra spaces and newlines
    $text = preg_replace('/\s+/', ' ', $text);

    return trim($text);
}

function removeStopWords($text)
{
    $stopWords = StopWord::getStopWords('en');

    // Convert the text to lowercase
    $text = strtolower($text);

    // Tokenize the text into individual words
    $words = preg_split('/\s+/', $text);

    // Remove stop words from the list of words
    $filteredWords = array_diff($words, $stopWords);

    // Reconstruct the filtered text
    $filteredText = implode(' ', $filteredWords);

    return $filteredText;
}

function calculateKeywords($text)
{
    $wordFrequency = [];
    $wordScore = [];
    $keywords = [];

    $sentences = preg_split('/(?<=[.?!])\s+(?=[a-z])/i', $text); // Split text into sentences

    foreach ($sentences as $sentence) {
        $words = preg_split('/\s+/', $sentence); // Split sentence into words

        foreach ($words as $word) {
            $word = preg_replace('/[^\p{L}\p{N}\s]/u', '', $word); // Remove non-alphanumeric characters

            if (!empty($word) && strlen($word) > 1) {
                if (!isset($wordFrequency[$word])) {
                    $wordFrequency[$word] = 0;
                }
                if (!isset($wordScore[$word])) {
                    $wordScore[$word] = 0;
                }

                $wordFrequency[$word]++; // Increase word frequency
                $wordScore[$word] += strlen($sentence); // Increase word score based on sentence length
            }
        }
    }

    foreach ($wordFrequency as $word => $frequency) {
        $wordScore[$word] = $wordScore[$word] / $frequency; // Calculate word score

        if ($wordScore[$word] >= 1.0) {
            $keywords[] = $word; // Add word to keywords list
        }
    }

    return $keywords;
}

$html = fetchHTML($url);
$text = extractTextFromHTML($html);
$filteredText = removeStopWords($text);
$keywords = calculateKeywords($filteredText);

// Create a response array
$response = [
    'keywords' => $keywords
];

// Convert the response array to JSON
$jsonResponse = json_encode($response);

// Send the JSON response
echo $jsonResponse;


?>