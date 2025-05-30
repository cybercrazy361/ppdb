<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://console.wablas.com/api/v2/send-message"); // Ganti dengan API mana saja yang ingin diuji
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$result = curl_exec($ch);
if (curl_errno($ch)) {
    echo "CURL Error: " . curl_error($ch);
} else {
    echo "CURL OK, response: " . substr($result, 0, 200);
}
curl_close($ch);
?>
