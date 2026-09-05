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
                'subject' => 'Chào bạn, T.A.M REHAB xin gửi lời cảm ơn!',
                'html' => tamrehab_email_wrap('Cảm ơn bạn đã để lại thông tin',
                    "<p>Xin chào {$safeName},</p>"
                    . '<p>Cảm ơn bạn đã tin tưởng và tìm đến T.A.M REHAB — dịch vụ giãn cơ trị liệu chuyên sâu 1:1 tại TP.HCM.</p>'
                    . '<p>Chúng tôi hiểu cơ thể cần được lắng nghe trước khi bị quá tải. Nếu bạn đang căng vai gáy, đau lưng do ngồi nhiều, hoặc cần phục hồi sau tập luyện — đội ngũ sẽ cùng bạn tìm ra vùng cơ đang bị bó và cách chăm sóc phù hợp.</p>'
                    . '<p>Khi bạn sẵn sàng, hãy đặt một buổi trải nghiệm. Không bắt buộc thẻ, không phát sinh chi phí gì thêm.</p>'
                    . '<p>Thân ái,<br>Đội ngũ T.A.M REHAB</p>'
                ),
            ];
        case 'nurture':
            return [
                'subject' => 'Ngồi 6 tiếng rồi? Cơ thể bạn đang kêu cứu đấy!',
                'html' => tamrehab_email_wrap('Cơ thể không cần thêm một chỗ để đau',
                    "<p>Xin chào {$safeName},</p>"
                    . '<p>Ngồi nhiều giờ mỗi ngày khiến vai gáy và lưng dần bị bó cứng — nhưng nhiều người chỉ nhận ra khi đã thành cơn đau kéo dài.</p>'
                    . '<p>Giãn cơ trị liệu chuyên sâu khác massage thông thường ở chỗ: chúng tôi tập trung vào điểm nghẽn, biên độ vận động và thói quen ngồi/tập của riêng bạn — thay vì chỉ làm dịu tạm thời.</p>'
                    . '<p>Một buổi 1:1 giúp xác định vùng cần can thiệp đúng cách. Khi bạn sẵn sàng, đội ngũ sẽ sắp lịch phù hợp cho bạn.</p>'
                    . '<p>Thân ái,<br>Đội ngũ T.A.M REHAB</p>'
                ),
            ];
        case 'close':
            return [
                'subject' => 'Ưu đãi 600K cho buổi giãn cơ 1:1 – dành riêng cho bạn',
                'html' => tamrehab_email_wrap('Thử một buổi trước — không cần cam kết gì',
                    "<p>Xin chào {$safeName},</p>"
                    . '<p>Giá trải nghiệm 60 phút 1:1 là <strong>600.000đ</strong>, gồm đánh giá vận động, giãn cơ chuyên sâu và hướng dẫn tự chăm sóc tại nhà. Không membership, không phát sinh chi phí gì thêm.</p>'
                    . '<p>Nếu bạn đang đau mỏi kéo dài, một buổi đầu là đủ để biết cơ thể cần gì và có phù hợp hay không. Bạn không cần cam kết dài hạn.</p>'
                    . '<p>Đặt lịch trên tamrehab.com hoặc nhắn Zalo <strong>0902 499 162</strong> — chúng tôi sẽ xác nhận khung giờ cho bạn.</p>'
                    . '<p>Hẹn gặp bạn,<br>Đội ngũ T.A.M REHAB</p>'
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
        . '<p><strong>Chúng tôi sẽ gửi xác nhận lịch hẹn qua Zalo trong 15 phút tới.</strong></p>'
        . '<p><strong>Hướng dẫn nhận buổi trị liệu / nhận hàng:</strong></p>'
        . '<ol>'
        . '<li>Giữ email này và mã đơn khi đến buổi hẹn.</li>'
        . '<li>Nếu là dịch vụ tại chỗ: đến đúng giờ đã thống nhất, mặc đồ thoải mái để giãn cơ.</li>'
        . '<li>Nếu cần dời lịch, trả lời email này hoặc liên hệ Zalo 0902 499 162.</li>'
        . '</ol>'
        . '<p>Cảm ơn bạn. Hẹn gặp lại trên bàn trị liệu.</p>'
        . '<p>Thân ái,<br>Đội ngũ T.A.M REHAB</p>'
    );

    return [
        'subject' => 'Đã nhận đơn hàng ' . ($order['order_id'] ?? '') . ' – T.A.M REHAB sẽ xác nhận qua Zalo',
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
