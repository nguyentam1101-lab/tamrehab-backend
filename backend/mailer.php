<?php

declare(strict_types=1);

function tamrehab_resend_api_key(): string
{
    $fromEnv = trim((string) (getenv('RESEND_API_KEY') ?: ($_ENV['RESEND_API_KEY'] ?? '')));
    if ($fromEnv !== '') {
        return $fromEnv;
    }

    $candidates = [
        __DIR__ . DIRECTORY_SEPARATOR . 'resend_config.txt',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'resend_config.txt',
    ];

    foreach ($candidates as $path) {
        if (!is_file($path)) {
            continue;
        }
        $raw = trim((string) file_get_contents($path));
        if ($raw === '') {
            continue;
        }
        if (preg_match('/^(?:RESEND_API_KEY\s*=\s*)?(\S+)/m', $raw, $match)) {
            return trim($match[1], " \t\"'");
        }
    }

    return '';
}

function tamrehab_send_resend_email(string $to, string $subject, string $html): array
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Email người nhận không hợp lệ'];
    }

    $apiKey = tamrehab_resend_api_key();
    if ($apiKey === '') {
        return [
            'success' => false,
            'message' => 'Thiếu API Key Resend. Đặt RESEND_API_KEY trên Render hoặc lưu trong backend/resend_config.txt',
        ];
    }

    $payload = json_encode([
        'from' => 'T.A.M REHAB <hello@tamrehab.com>',
        'to' => [$to],
        'subject' => $subject,
        'html' => $html,
    ], JSON_UNESCAPED_UNICODE);

    $responseBody = '';
    $statusCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $responseBody = (string) curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($responseBody === '' && $curlError !== '') {
            return ['success' => false, 'message' => 'Không gọi được Resend: ' . $curlError];
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Bearer {$apiKey}\r\nContent-Type: application/json\r\n",
                'content' => $payload,
                'ignore_errors' => true,
                'timeout' => 20,
            ],
        ]);
        $responseBody = (string) file_get_contents('https://api.resend.com/emails', false, $context);
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $match)) {
            $statusCode = (int) $match[1];
        }
    }

    $decoded = json_decode($responseBody, true);
    if ($statusCode >= 200 && $statusCode < 300) {
        return ['success' => true, 'id' => $decoded['id'] ?? null];
    }

    $error = is_array($decoded) ? ($decoded['message'] ?? $decoded['error'] ?? $responseBody) : $responseBody;
    return ['success' => false, 'message' => 'Resend lỗi: ' . (is_string($error) ? $error : json_encode($error))];
}

function tamrehab_email_wrap(string $title, string $bodyHtml): string
{
    return '<!doctype html><html lang="vi"><body style="margin:0;background:#f4efe7;font-family:Segoe UI,Arial,sans-serif;color:#35291F;">'
        . '<div style="max-width:560px;margin:24px auto;background:#FFFBF5;border:1px solid #D8B877;border-radius:12px;overflow:hidden;">'
        . '<div style="background:#35291F;color:#F8F1E2;padding:18px 22px;font-weight:700;letter-spacing:.08em;">T.A.M REHAB</div>'
        . '<div style="padding:22px;line-height:1.6;font-size:15px;">'
        . '<h1 style="margin:0 0 14px;font-size:20px;color:#35291F;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
        . $bodyHtml
        . '</div>'
        . '<div style="padding:14px 22px;color:#6E5D4F;font-size:12px;border-top:1px solid #ead8bb;">hello@tamrehab.com · Dịch vụ giãn cơ trị liệu chuyên sâu</div>'
        . '</div></body></html>';
}

function tamrehab_sequence_email(string $type, array $data = []): array
{
    $name = trim((string) ($data['name'] ?? 'bạn'));
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

    switch ($type) {
        case 'welcome':
            return [
                'subject' => 'Chào mừng bạn đến với T.A.M REHAB',
                'html' => tamrehab_email_wrap('Rất vui được đồng hành cùng bạn',
                    "<p>Xin chào {$safeName},</p>"
                    . '<p>Cảm ơn bạn đã để lại thông tin cho T.A.M REHAB. Cơ thể cần được lắng nghe trước khi bị quá tải — chúng tôi ở đây để hỗ trợ bạn giãn cơ trị liệu chuyên sâu, 1:1.</p>'
                    . '<p>Nếu bạn đang căng vai gáy, đau lưng hoặc cần phục hồi sau tập luyện, hãy đặt một buổi trải nghiệm khi sẵn sàng.</p>'
                    . '<p>Thân ái,<br>T.A.M REHAB</p>'
                ),
            ];
        case 'nurture':
            return [
                'subject' => 'Vì sao giãn cơ chuyên sâu khác massage thông thường',
                'html' => tamrehab_email_wrap('Hiểu đúng cơ thể trước khi “cứ xoa là hết”',
                    "<p>Xin chào {$safeName},</p>"
                    . '<p>Nhiều người tìm T.A.M REHAB sau khi đã thử massage nhưng cơn đau vẫn quay lại. Giãn cơ trị liệu chuyên sâu tập trung vào điểm nghẽn, biên độ vận động và thói quen ngồi/tập của bạn.</p>'
                    . '<p>Một buổi 1:1 giúp xác định vùng cần can thiệp, thay vì chỉ làm dịu tạm thời.</p>'
                    . '<p>Khi bạn sẵn sàng, chúng tôi sẽ sắp lịch phù hợp.</p>'
                ),
            ];
        case 'close':
            return [
                'subject' => 'Sẵn sàng đặt buổi trị liệu cùng T.A.M REHAB?',
                'html' => tamrehab_email_wrap('Chốt một buổi — không cần mua thẻ',
                    "<p>Xin chào {$safeName},</p>"
                    . '<p>Bạn có thể đặt 1 buổi trải nghiệm, không bắt buộc thẻ tháng. Nếu đang đau mỏi kéo dài, đây là bước nhỏ để kiểm tra cơ thể đang cần gì.</p>'
                    . '<p>Trả lời email này hoặc đặt lịch trên tamrehab.com — chúng tôi sẽ xác nhận khung giờ.</p>'
                    . '<p>Hẹn gặp bạn,<br>T.A.M REHAB</p>'
                ),
            ];
        default:
            return [];
    }
}

function tamrehab_order_confirmation_email(array $order): array
{
    $name = htmlspecialchars((string) ($order['customer_name'] ?: 'bạn'), ENT_QUOTES, 'UTF-8');
    $orderId = htmlspecialchars((string) ($order['order_id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $product = htmlspecialchars((string) ($order['product_name'] ?? 'Dịch vụ T.A.M REHAB'), ENT_QUOTES, 'UTF-8');
    $amount = number_format((float) ($order['amount'] ?? 0), 0, ',', '.');
    $quantity = (int) ($order['quantity'] ?? 1);
    $content = htmlspecialchars((string) ($order['content'] ?? ''), ENT_QUOTES, 'UTF-8');

    $html = tamrehab_email_wrap(
        'Xác nhận đơn hàng của bạn',
        "<p>Xin chào {$name},</p>"
        . '<p>T.A.M REHAB đã nhận đơn hàng của bạn. Cảm ơn bạn đã tin tưởng dịch vụ giãn cơ trị liệu chuyên sâu.</p>'
        . '<table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;">'
        . '<tr><td style="padding:8px 0;color:#6E5D4F;">Mã đơn</td><td style="padding:8px 0;font-weight:700;">' . $orderId . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#6E5D4F;">Sản phẩm / dịch vụ</td><td style="padding:8px 0;">' . $product . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#6E5D4F;">Số lượng</td><td style="padding:8px 0;">' . $quantity . '</td></tr>'
        . '<tr><td style="padding:8px 0;color:#6E5D4F;">Số tiền</td><td style="padding:8px 0;font-weight:700;">' . $amount . ' VNĐ</td></tr>'
        . ($content !== '' ? '<tr><td style="padding:8px 0;color:#6E5D4F;">Ghi chú</td><td style="padding:8px 0;">' . $content . '</td></tr>' : '')
        . '</table>'
        . '<p><strong>Hướng dẫn nhận buổi trị liệu / nhận hàng:</strong></p>'
        . '<ol>'
        . '<li>Giữ email này và mã đơn khi đến buổi hẹn.</li>'
        . '<li>Nếu là dịch vụ tại chỗ: đến đúng giờ đã thống nhất, mặc đồ thoải mái để giãn cơ.</li>'
        . '<li>Nếu cần dời lịch, trả lời email này hoặc liên hệ hello@tamrehab.com.</li>'
        . '</ol>'
        . '<p>Cảm ơn bạn. Hẹn gặp lại trên bàn trị liệu.</p>'
        . '<p>Thân ái,<br>T.A.M REHAB</p>'
    );

    return [
        'subject' => 'T.A.M REHAB – Xác nhận đơn hàng ' . ($order['order_id'] ?? ''),
        'html' => $html,
    ];
}

function tamrehab_send_order_confirmation(PDO $pdo, string $orderId): array
{
    $stmt = $pdo->prepare(
        'SELECT o.*, p.name AS product_name
         FROM orders o
         LEFT JOIN products p ON p.id = o.product_id
         WHERE o.order_id = :order_id
         LIMIT 1'
    );
    $stmt->execute([':order_id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        return ['success' => false, 'message' => 'Không tìm thấy đơn hàng'];
    }

    $email = trim((string) ($order['email'] ?? ''));
    if ($email === '' && !empty($order['phone'])) {
        $lookup = $pdo->prepare('SELECT email FROM customers WHERE phone = :phone AND email IS NOT NULL AND email != "" LIMIT 1');
        $lookup->execute([':phone' => $order['phone']]);
        $email = trim((string) ($lookup->fetchColumn() ?: ''));
    }

    if ($email === '') {
        return ['success' => false, 'message' => 'Đơn hàng chưa có email khách hàng'];
    }

    $template = tamrehab_order_confirmation_email($order);
    return tamrehab_send_resend_email($email, $template['subject'], $template['html']);
}
