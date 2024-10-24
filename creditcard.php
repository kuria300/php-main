<?php 
session_start(); 
function getAccessToken() {
    $consumer_key = ' Rm1drpxRb854CYa2YKqY4GjIvZ6ki2ltUYGgR3K4zWswBycK';
    $consumer_secret = 'kEjI9s29S43LS6KG0URrkJo6Pb9iCvdlVxA8Wc8ln5Qp0E1MxPAs7KYfl6NxgX9c';
    $credentials = base64_encode($consumer_key . ':' . $consumer_secret);
    $url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

    $headers = [
        'Authorization: Basic ' . $credentials,
        'Content-Type: application/json; charset=utf8'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_USERPWD, $consumer_key . ':' . $consumer_secret);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $response_data = json_decode($response, true);
     
    return isset($response_data['access_token']) ? $response_data['access_token'] : '';
    curl_close($ch);
}


function callMpesaApi() {
    $url = "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest";
    $access_token = getAccessToken();

    $shortcode = 174379;  // Replace with your shortcode
    $lipa_na_mpesa_online_shortcode_key = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";
    $password = base64_encode($shortcode . $lipa_na_mpesa_online_shortcode_key . date('YmdHis'));
    

    $data = [
        "BusinessShortCode" => $shortcode,
        "Password" => $password,
        "Timestamp" => date('YmdHis'),
        "TransactionType" => "CustomerPayBillOnline",
        "Amount" => 1, // Change this to the desired amount
        "PartyA"=> 254768863372,
        "PartyB"=> 174379,
        "PhoneNumber"=> 254768863372,
        "CallBackURL" => "https://d9a6-102-0-5-84.ngrok-free.app/sms/callback.php",
        "AccountReference" => "AutoReceipt",
        "TransactionDesc" => "Payment for Fees"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    echo "HTTP Code: $http_code\n";
    echo "Response: $response\n";

    // Decode the response
    $response_data = json_decode($response, true);
    
    // Check for JSON decode errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON decode error: " . json_last_error_msg() . "\n";
        return false;
    }

    // Check the response code
    if (isset($response_data['ResponseCode']) && $response_data['ResponseCode'] == '0') {
        if (isset($response_data['CheckoutRequestID'])) {
            $_SESSION['latest_checkout_id'] = $response_data['CheckoutRequestID'];
        }
        return true; // Payment was successful
    } else {
        echo "Error: " . ($response_data['errorMessage'] ?? 'Unknown error') . "\n";
        return false; // Payment failed
    }
    
    curl_close($ch);
}

function queryAPI() {
    if (!isset($_SESSION['latest_checkout_id'])) {
        return "No recent CheckoutRequestID found.";
    }

    $ch = curl_init('https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query');
    $access_token = getAccessToken();
    $shortcode = 174379;
    $lipa_na_mpesa_online_shortcode_key = "bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919";
    $timestamp = date('YmdHis');
    $password = base64_encode($shortcode . $lipa_na_mpesa_online_shortcode_key . $timestamp);
    
    $CheckoutRequestID = $_SESSION['latest_checkout_id'];

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);

    $data = json_encode([
        "BusinessShortCode" => $shortcode,
        "Password" => $password,
        "Timestamp" => $timestamp,
        "CheckoutRequestID" => $CheckoutRequestID
    ]);
    
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return ["error" => "cURL Error: $error_msg"]; // Return error as array
    }

    $data_to = json_decode($response, true); // Decode as associative array
    curl_close($ch);
    
    return $data_to; // Return the full response
}

function processTransaction() {
    $maxAttempts = 5; // Maximum attempts to check the transaction status
    $attempt = 0;

    while ($attempt < $maxAttempts) {
        $response = queryAPI(); // Query the transaction status

        if (isset($response['error'])) {
            echo $response['error']; // Output any cURL errors
            return;
        }

        if (isset($response['errorCode']) && $response['errorCode'] === '500.001.1001') {
            echo "Transaction is still being processed: " . $response['errorMessage'] . "<br>";
            sleep(10); // Wait for 10 seconds before retrying
            $attempt++;
            continue; // Continue polling for transaction status
        }

        // Handle completion of transaction
        return handleTransactionResult($response); // Process and return the result message
    }

    return "Transaction is still being processed after multiple attempts. Please check your M-Pesa app or contact support.";
}

function handleTransactionResult($data_to) {
    $message = "Unknown Result Code";

    if (isset($data_to['ResultCode'])) {
        switch ($data_to['ResultCode']) {
            case 0:
                $message = 'Transaction is Successful';
                break;
            case 1:
                $message = 'Balance is Insufficient to Complete Transaction';
                break;
            case 1032:
                $message = 'Transaction has been Cancelled by User';
                break;
            case 1037:
                $message = 'Timeout in Completing Transaction';
                break;
            default:
                $message = 'Unhandled Result Code: ' . $data_to['ResultCode'];
                break;
        }
    }

    return $message; // Return the message instead of echoing
}

// Usage
$paymentSuccess = callMpesaApi();
if ($paymentSuccess) {
   $transactionMessage = processTransaction(); // Check the transaction status
   echo $transactionMessage; // Output the transaction message
} else {
    echo "Payment initiation failed.";
}

?>