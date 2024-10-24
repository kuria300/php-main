<?php
// callback.php

// Read the incoming POST data
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Log the data for debugging
file_put_contents('callback_log.txt', "Headers: " . print_r(getallheaders(), true), FILE_APPEND);
file_put_contents('callback_log.txt', "Input: " . print_r($input, true), FILE_APPEND);

// Check if data is received
if (!empty($data)) {
    // Process the payment result
    $resultCode = $data['Body']['stkCallback']['ResultCode'] ?? 'No ResultCode';
    $resultDesc = $data['Body']['stkCallback']['ResultDesc'] ?? 'No ResultDesc';

    // Log or handle the result as needed
    file_put_contents('callback_log.txt', "ResultCode: $resultCode, ResultDesc: $resultDesc\n", FILE_APPEND);
} else {
    // Log that no data was received
    file_put_contents('callback_log.txt', "No data received\n", FILE_APPEND);
}
?>