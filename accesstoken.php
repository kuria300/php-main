<?php 
session_start(); 

    $consumer_key = 'Pya9DhqKqIzLD6lbUDfKbEGrHk5n7mABWUN0NkHw64dxDKWU';
    $consumer_secret = '9fcEgZtSNhx3YyAdzOW2rGd8Wov8NlGuLZ6OK9BlAktjIbAoAgLYT9PhKHuF1znZ';
    
    $credentials = base64_encode($consumer_key . ':' . $consumer_secret);
    $url = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

    $headers = [
        'Authorization: Basic ' . $credentials,
        'Content-Type: application/json; charset=utf8'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Code: $http_code\n";
    echo "Response: " . $response . "\n"; // Log the full response for debugging
    // Check for errors and log the response
    if ($http_code != 200) {
        echo "Error generating access token: " . $response . "\n";
        return ''; // Return empty string if token generation fails
    }

    $response_data = json_decode($response, true);
    
    $access_token = isset($response_data['access_token']) ? $response_data['access_token'] : '';

?>