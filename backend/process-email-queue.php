<?php
require_once __DIR__ . '/db.php';

function tamrehab_log(string $message): void
{
    $logFile = __DIR__ . '/email_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, '[' . $timestamp . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function tamrehab_process_due_email_queue(PDO $pdo): void
{
    $rows = $pdo->query("SELECT * FROM email_queue WHERE status = 'pending' AND send_at <= datetime('now') ORDER BY send_at ASC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        tamrehab_log('Queue processing: id=' . (string) $row['id'] . ' type=' . (string) $row['email_type'] . ' to=' . (string) $row['to_email']);
        $ch = curl_init('https://api.resend.com/emails');
        $apiKey = trim((string) file_get_contents(__DIR__ . '/resend_config.txt'));
        if ($apiKey === '') {
            tamrehab_log('Queue failed: missing Resend API key for id=' . (string) $row['id']);
            $pdo->prepare('UPDATE email_queue SET status = :status, error_message = :error_message WHERE id = :id')->execute([
                ':status' => 'failed',
                ':error_message' => 'Missing Resend API key',
                ':id' => (int) $row['id'],
            ]);
            continue;
        }

        $payload = [
            'from' => 'hello@tamrehab.com',
            'to' => [(string) $row['to_email']],
            'subject' => (string) $row['subject'],
            'html' => nl2br(htmlspecialchars((string) $row['body'], ENT_QUOTES, 'UTF-8'), false),
            'reply_to' => 'hello@tamrehab.com',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        tamrehab_log('Queue resend response: id=' . (string) $row['id'] . ' http_code=' . (string) $httpCode . ' response=' . (string) $response . ' error=' . ($error ?: 'none'));

        $decoded = json_decode((string) $response, true);

        if ($error !== '' || $httpCode >= 400 || !isset($decoded['id'])) {
            $pdo->prepare('UPDATE email_queue SET status = :status, error_message = :error_message WHERE id = :id')->execute([
                ':status' => 'failed',
                ':error_message' => $error !== '' ? $error : ($response ?: 'Resend request failed'),
                ':id' => (int) $row['id'],
            ]);
            continue;
        }

        $pdo->prepare('UPDATE email_queue SET status = :status, sent_at = :sent_at, provider_message_id = :provider_message_id WHERE id = :id')->execute([
            ':status' => 'sent',
            ':sent_at' => date('Y-m-d H:i:s'),
            ':provider_message_id' => (string) $decoded['id'],
            ':id' => (int) $row['id'],
        ]);

        echo "Sent queued email id={$row['id']} type={$row['email_type']} to={$row['to_email']}\n";
    }
}

$pdo = tamrehab_db();
tamrehab_process_due_email_queue($pdo);
