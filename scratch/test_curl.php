<?php
$ch = curl_init('http://127.0.0.1:8000/dashboard/data?date=2024-01-01&end=2026-12-31');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($response === false) {
    var_dump(curl_error($ch));
} else {
    var_dump("HTTP", $httpcode, "Response length", strlen($response), substr($response, 0, 500));
}
