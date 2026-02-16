<?php
declare(strict_types=1);

// Minimal autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($path)) require $path;
});

use App\Config;
use App\Logger;
use App\Payment\Id;
use App\Payment\Storage;
use App\Payment\StateMachine;

header('Content-Type: application/json');

Config::loadEnv(__DIR__ . '/../.env');
$logger = new Logger(Config::get('LOG_PATH', __DIR__ . '/../storage/logs/app.log'));
$storage = new Storage(Config::get('DB_PATH', __DIR__ . '/../storage/db/payments.sqlite'));

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

function readJsonBody(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid JSON body');
    }
    return $decoded;
}

function respond(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if ($method === 'GET' && $uri === '/health') {
        respond(200, ['ok' => true, 'service' => 'payment-flow-simulator', 'ts' => gmdate('c')]);
    }

    // GET /transactions/{id}
    if ($method === 'GET' && preg_match('#^/transactions/(.+)$#', $uri, $m)) {
        $id = $m[1];
        $txn = $storage->find($id);
        if (!$txn) respond(404, ['error' => 'Not found', 'id' => $id]);
        respond(200, ['transaction' => $txn]);
    }

    // POST /authorize
    if ($method === 'POST' && $uri === '/authorize') {
        $body = readJsonBody();
        $amount = $body['amount_cents'] ?? null;
        $currency = $body['currency'] ?? null;
        $description = $body['description'] ?? null;

        if (!is_int($amount) || $amount <= 0) respond(400, ['error' => 'amount_cents must be a positive integer']);
        if (!is_string($currency) || strlen($currency) !== 3) respond(400, ['error' => 'currency must be a 3-letter code']);

        $id = Id::txn();
        $history = [[
            'ts' => gmdate('c'),
            'action' => 'authorize',
            'status' => StateMachine::STATUS_AUTHORIZED,
            'amount_cents' => $amount,
            'currency' => strtoupper($currency),
        ]];

        $storage->create($id, $amount, $currency, is_string($description) ? $description : null, StateMachine::STATUS_AUTHORIZED, $history);
        $logger->info('Authorized', ['id' => $id, 'amount_cents' => $amount, 'currency' => strtoupper($currency)]);

        respond(201, ['id' => $id, 'status' => StateMachine::STATUS_AUTHORIZED]);
    }

    // POST /capture {id}
    if ($method === 'POST' && $uri === '/capture') {
        $body = readJsonBody();
        $id = $body['id'] ?? null;
        if (!is_string($id) || $id === '') respond(400, ['error' => 'id is required']);

        $txn = $storage->find($id);
        if (!$txn) respond(404, ['error' => 'Not found', 'id' => $id]);

        if (!StateMachine::canCapture((string)$txn['status'])) {
            respond(409, ['error' => 'Invalid state transition', 'from' => $txn['status'], 'to' => 'captured']);
        }

        $history = $txn['history'];
        $history[] = ['ts' => gmdate('c'), 'action' => 'capture', 'status' => StateMachine::STATUS_CAPTURED];

        $storage->updateStatus($id, StateMachine::STATUS_CAPTURED, $history);
        $logger->info('Captured', ['id' => $id]);

        respond(200, ['id' => $id, 'status' => StateMachine::STATUS_CAPTURED]);
    }

    // POST /void {id}
    if ($method === 'POST' && $uri === '/void') {
        $body = readJsonBody();
        $id = $body['id'] ?? null;
        if (!is_string($id) || $id === '') respond(400, ['error' => 'id is required']);

        $txn = $storage->find($id);
        if (!$txn) respond(404, ['error' => 'Not found', 'id' => $id]);

        if (!StateMachine::canVoid((string)$txn['status'])) {
            respond(409, ['error' => 'Invalid state transition', 'from' => $txn['status'], 'to' => 'voided']);
        }

        $history = $txn['history'];
        $history[] = ['ts' => gmdate('c'), 'action' => 'void', 'status' => StateMachine::STATUS_VOIDED];

        $storage->updateStatus($id, StateMachine::STATUS_VOIDED, $history);
        $logger->info('Voided', ['id' => $id]);

        respond(200, ['id' => $id, 'status' => StateMachine::STATUS_VOIDED]);
    }

    // POST /refund {id, amount_cents?}
    if ($method === 'POST' && $uri === '/refund') {
        $body = readJsonBody();
        $id = $body['id'] ?? null;
        $refundAmount = $body['amount_cents'] ?? null; // optional (demo)

        if (!is_string($id) || $id === '') respond(400, ['error' => 'id is required']);
        if ($refundAmount !== null && (!is_int($refundAmount) || $refundAmount <= 0)) {
            respond(400, ['error' => 'amount_cents must be a positive integer when provided']);
        }

        $txn = $storage->find($id);
        if (!$txn) respond(404, ['error' => 'Not found', 'id' => $id]);

        if (!StateMachine::canRefund((string)$txn['status'])) {
            respond(409, ['error' => 'Invalid state transition', 'from' => $txn['status'], 'to' => 'refunded']);
        }

        $history = $txn['history'];
        $history[] = [
            'ts' => gmdate('c'),
            'action' => 'refund',
            'status' => StateMachine::STATUS_REFUNDED,
            'amount_cents' => $refundAmount ?? $txn['amount_cents'],
        ];

        $storage->updateStatus($id, StateMachine::STATUS_REFUNDED, $history);
        $logger->info('Refunded', ['id' => $id, 'amount_cents' => $refundAmount ?? $txn['amount_cents']]);

        respond(200, ['id' => $id, 'status' => StateMachine::STATUS_REFUNDED]);
    }

    respond(404, ['error' => 'Not Found', 'path' => $uri]);
} catch (Throwable $e) {
    $logger->error('Unhandled exception', ['message' => $e->getMessage()]);
    respond(500, ['error' => 'Server Error']);
}