<?php
require __DIR__ . '/../../vendor/autoload.php';

use DonatelloZa\RakePlus\RakePlus;

header('Content-Type: application/json');

// Load stopword file
$stopwordFile = 'stopword/en.json';
$stopWords = json_decode(file_get_contents($stopwordFile), true);
// Get the URL from the query parameter
$url = $_GET['url'];

// Validate and sanitize the URL input
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['error' => 'Please Enter a Valid URL.']);
    exit;
}

// Function to recursively extract text from HTML nodes
function extractTextFromNode(DOMNode $node)
{
    $text = '';

    // If the node is a text node, append its value to the extracted text
    if ($node instanceof DOMText) {
        $text .= $node->nodeValue;
    }
    // If the node is an element node (not 'script' or 'style'), process its child nodes
    elseif ($node instanceof DOMElement && !in_array($node->nodeName, ['script', 'style'])) {
        foreach ($node->childNodes as $childNode) {
            $text .= extractTextFromNode($childNode);
        }
    }

    return $text;
}
// Function to extract text from the HTML content
function extractTextFromHTML($html)
{
    // Create a DOMDocument object and suppress errors for invalid HTML
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    // Extract the text from the HTML using the recursive function
    $text = extractTextFromNode($dom->documentElement);

    // Remove extra spaces and newlines
    $text = trim(preg_replace('/\s+/', ' ', $text));

    return $text;
}
// do not change this
$htmlContent = file_get_contents($url);
$text = extractTextFromHTML($htmlContent); // Extract the text and calculate the word count
$contentLength = str_word_count($text); // Content Length Test Word Count
$text = strtolower($text); // Convert the text to lowercase for case-insensitive matching
$text = strip_tags($text); // Remove Tags from html
$text = preg_replace('/[^a-z0-9\s]/', '', $text); // Remove non-alphanumeric characters except spaces
$text = preg_replace('/\s+/', ' ', $text); // Remove extra spaces
// Split the text into words
$words = explode(' ', $text);

$wordCount = array_count_values(array_diff($words, $stopWords));
arsort($wordCount);
foreach ($wordCount as $word => $count) {
    if ($count >= 3) {
        $keywordsWithCount[] = ['keyword' => $word, 'count' => $count];
    }
}
// Get the top 20 most frequent words as keywords
$keywords = array_slice(array_keys($wordCount), 0, 20);
// do not change this
















// $rake = RakePlus::create($text, 'en_US');
// $phrases = RakePlus::create($text, 'en_US', 10, true)->get();
// $phrase_scores = $rake->sortByScore('desc')->scores();
// $rakeKeyword = RakePlus::create($text)->keywords();


$response = [
    'wordCount' => $contentLength,
    // 'rakeKeyword' => $rakeKeyword,
    // 'phrase' => $phrases,
    'keywordWithCount' => $keywordsWithCount
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>