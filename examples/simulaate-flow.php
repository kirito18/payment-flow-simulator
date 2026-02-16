<?php
declare(strict_types=1);

function post(string $url, array $json): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($json, JSON_UNESCAPED_SLASHES),
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        throw new RuntimeException('cURL error: ' . curl_error($ch));
    }
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$code, $resp];
}

$base = 'http://127.0.0.1:8000';

$payload = json_decode(file_get_contents(__DIR__ . '/sample-payment.json') ?: '[]', true);
if (!is_array($payload)) $payload = [];

echo "1) AUTHORIZE\n";
[$code, $body] = post($base . '/authorize', $payload);
echo "HTTP $code\n$body\n\n";

$data = json_decode($body, true);
$id = $data['id'] ?? null;
if (!is_string($id)) {
    echo "Missing id, abort.\n";
    exit(1);
}

echo "2) CAPTURE\n";
[$code, $body] = post($base . '/capture', ['id' => $id]);
echo "HTTP $code\n$body\n\n";

echo "3) REFUND\n";
[$code, $body] = post($base . '/refund', ['id' => $id, 'amount_cents' => 2500]);
echo "HTTP $code\n$body\n\n";

echo "4) FETCH TRANSACTION\n";
echo file_get_contents($base . '/transactions/' . $id) . "\n";