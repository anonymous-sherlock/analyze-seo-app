<?php
require_once(__DIR__ . '/../db/config.php');

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed status code
    echo json_encode(['message' => 'Only POST requests are allowed']);
    exit;
}

// Create the users table if it doesn't exist
$createTableQuery = "
CREATE TABLE IF NOT EXISTS users (
id INT AUTO_INCREMENT PRIMARY KEY,
email VARCHAR(255) NOT NULL,
ip VARCHAR(255) NOT NULL,
city VARCHAR(255) NOT NULL,
country VARCHAR(255) NOT NULL,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
";
$conn->query($createTableQuery);

// Get the email from the request body
$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'];

// Check if the email already exists in the users table
$checkStmt = $conn->prepare("SELECT COUNT(*) AS email_count FROM users WHERE email = ?");
$checkStmt->bind_param("s", $email);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
$emailCount = $checkResult->fetch_assoc()['email_count'];

if ($emailCount > 0) {
    // Email already exists, return an error response
    http_response_code(409); // Conflict status code
    echo json_encode(['success' => false, 'message' => 'Email already exists']);
    exit;
}

// Get the user's IP address
$ip = $_SERVER['REMOTE_ADDR'];
$ipDetails = null;

// Fetch IP details only if email is new
if ($emailCount === 0) {
    function getIPDetails($ip)
    {
        $url = "https://freegeoip.app/json/{$ip}";

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($curl);

        if ($response) {
            $data = json_decode($response, true);

            if ($data && isset($data['city']) && isset($data['country_name'])) {
                return [
                    'city' => $data['city'],
                    'country' => $data['country_name']
                ];
            }
        }

        return [
            'city' => 'Unknown',
            'country' => 'Unknown'
        ];
    }

    // Get the user's city and country information
    $ipDetails = getIPDetails($ip);
    $city = $ipDetails['city'];
    $country = $ipDetails['country'];
} else {
    // Use default values for city and country
    $city = 'Unknown';
    $country = 'Unknown';
}

// Insert the user into the database
$stmt = $conn->prepare("INSERT INTO users (email, ip, city, country) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $email, $ip, $city, $country);

if ($stmt->execute()) {
    // Success, return a success response
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'User added successfully']);
} else {
    // Error, return an error response
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to add user']);
}

$stmt->close();
$checkStmt->close();
$conn->close();
?>