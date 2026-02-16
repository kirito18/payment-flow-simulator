<?php
declare(strict_types=1);

namespace App\Payment;

use PDO;

final class Storage
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        $this->pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $this->migrate();
    }

    private function migrate(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS transactions (
                id TEXT PRIMARY KEY,
                status TEXT NOT NULL,
                amount_cents INTEGER NOT NULL,
                currency TEXT NOT NULL,
                description TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                history_json TEXT NOT NULL
            )"
        );
    }

    /**
     * @param array<int,array<string,mixed>> $history
     */
    public function create(string $id, int $amountCents, string $currency, ?string $description, string $status, array $history): void
    {
        $now = gmdate('c');
        $stmt = $this->pdo->prepare(
            "INSERT INTO transactions (id,status,amount_cents,currency,description,created_at,updated_at,history_json)
             VALUES (:id,:status,:amount,:currency,:description,:created,:updated,:history)"
        );
        $stmt->execute([
            ':id' => $id,
            ':status' => $status,
            ':amount' => $amountCents,
            ':currency' => strtoupper($currency),
            ':description' => $description,
            ':created' => $now,
            ':updated' => $now,
            ':history' => json_encode($history, JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(string $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM transactions WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        $row['amount_cents'] = (int)$row['amount_cents'];
        $row['history'] = json_decode((string)$row['history_json'], true) ?: [];
        unset($row['history_json']);

        return $row;
    }

    /**
     * @param array<int,array<string,mixed>> $history
     */
    public function updateStatus(string $id, string $status, array $history): void
    {
        $now = gmdate('c');
        $stmt = $this->pdo->prepare(
            "UPDATE transactions
             SET status=:status, updated_at=:updated, history_json=:history
             WHERE id=:id"
        );
        $stmt->execute([
            ':id' => $id,
            ':status' => $status,
            ':updated' => $now,
            ':history' => json_encode($history, JSON_UNESCAPED_SLASHES),
        ]);
    }
}