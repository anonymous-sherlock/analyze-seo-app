<?php
// Get the requested URI
$requestUri = $_SERVER['REQUEST_URI'];

// Define your base directory
$baseDir = '/analyze';

// Define your routing rules
$routes = [
    $baseDir . '/report' => $baseDir,
    $baseDir . '/add-user.php' => 'config/add-user.php',
    $baseDir . '/api/seo-res.php' => 'api/seo-res.php',
    // Add more routes as needed
];

// Remove the base directory from the requested URI
$requestUri = str_replace($baseDir, '', $requestUri);

// Check if the requested URI matches any route
if (isset($routes[$requestUri])) {
    // If there's a match, include the corresponding file
    include $routes[$requestUri];
} else {
    // Handle 404 - Page not found
    http_response_code(404);
    echo '404 - Page not found';
}
?>