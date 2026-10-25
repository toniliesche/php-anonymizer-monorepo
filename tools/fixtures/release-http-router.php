<?php

declare(strict_types=1);

$specificationPath = getenv('RELEASE_FIXTURE_HTTP_SPEC');
$statePath = getenv('RELEASE_FIXTURE_HTTP_STATE');
if (!is_string($specificationPath) || !is_string($statePath)) {
    http_response_code(500);

    exit;
}

$specification = json_decode((string) file_get_contents($specificationPath), true);
$state = json_decode((string) file_get_contents($statePath), true);
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$route = $method . ' ' . (is_string($path) ? $path : '/');
$responses = $specification['routes'][$route] ?? null;
if (!is_array($responses) || $responses === []) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'unconfigured fixture route']);

    exit;
}

$state['requests'] ??= [];
$state['observed'] ??= [];
$index = $state['requests'][$route] ?? 0;
$response = $responses[min($index, count($responses) - 1)];
$state['requests'][$route] = $index + 1;
$state['observed'][] = [
    'method' => $method,
    'path' => is_string($path) ? $path : '/',
    'authorization_present' => isset($_SERVER['HTTP_AUTHORIZATION']),
];
file_put_contents($statePath, json_encode($state, JSON_THROW_ON_ERROR) . "\n");

http_response_code($response['status']);
foreach ($response['headers'] ?? [] as $name => $value) {
    header($name . ': ' . $value);
}
if (!isset($response['headers']['Content-Type'])) {
    header('Content-Type: application/json');
}
$body = $response['body'] ?? '';
echo is_array($body) ? json_encode($body, JSON_THROW_ON_ERROR) : $body;
