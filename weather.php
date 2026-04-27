<?php
header('Content-Type: application/json; charset=UTF-8');

function fetch_weather_response($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => 'EventProjectWeather/1.0',
            CURLOPT_HTTPHEADER => ['Accept: application/json']
        ]);

        $response = curl_exec($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response !== false && $http_code >= 200 && $http_code < 300) {
            return $response;
        }
    }

    if (ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'header' => "User-Agent: EventProjectWeather/1.0\r\nAccept: application/json\r\n"
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response !== false) {
            return $response;
        }
    }

    return false;
}

$city = trim((string) ($_GET['city'] ?? 'Tunis'));

if ($city === '') {
    $city = 'Tunis';
}

$url = 'https://wttr.in/' . rawurlencode($city) . '?format=j1';
$response = fetch_weather_response($url);

if ($response === false) {
    echo json_encode([
        'ok' => false,
        'weather' => 'N/A'
    ]);
    exit();
}

$data = json_decode($response, true);
$current = $data['current_condition'][0] ?? null;
$temp = $current['temp_C'] ?? null;
$description = $current['weatherDesc'][0]['value'] ?? null;

if ($temp === null || $description === null) {
    echo json_encode([
        'ok' => false,
        'weather' => 'N/A'
    ]);
    exit();
}

echo json_encode([
    'ok' => true,
    'weather' => $temp . "°C " . $description
]);
