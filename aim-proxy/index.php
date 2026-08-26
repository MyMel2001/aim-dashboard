<?php
// Set target API base URL
$target_base = 'https://aim.nodemixaholic.com';

// 1. Capture client headers and allow origin dynamically
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// 2. Handle CORS Preflight OPTIONS requests immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 3. Extract the target endpoint path passed from query string (e.g., proxy.php?endpoint=/user)
$endpoint = $_GET['endpoint'] ?? '';
$target_url = $target_base . $endpoint;

// 4. Read incoming payload and request parameters
$method = $_SERVER['REQUEST_METHOD'];
$body = file_get_contents('php://input');

// Forward specific request headers (like Content-Type)
$request_headers = [];
if (isset($_SERVER['CONTENT_TYPE'])) {
    $request_headers[] = 'Content-Type: ' . $_SERVER['CONTENT_TYPE'];
}

// 5. Initialize cURL request to target API
$ch = curl_init($target_url);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $request_headers,
    CURLOPT_HEADER => true, // Capture response headers
    CURLOPT_SSL_VERIFYPEER => false // Use only for local dev if SSL issues arise
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['error' => curl_error($ch)]);
    curl_close($ch);
    exit;
}

// 6. Separate header and body from target response
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$response_headers = substr($response, 0, $header_size);
$response_body = substr($response, $header_size);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

// Forward Content-Type header from target response if present
foreach (explode("\r\n", $response_headers) as $header) {
    if (stripos($header, 'Content-Type:') === 0) {
        header($header);
    }
}

// Return HTTP status code and body to client
http_response_code($http_code);
echo $response_body;
