<?php

function tamrehab_log(string $message): void
{
    $logFile = __DIR__ . '/email_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $line = '[' . $timestamp . '] ' . $message;
    file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    error_log('[email-lib] ' . $line);
}

function tamrehab_email_sequence(): array
{
    return [
        [
            'type' => 'welcome',
            'subject' => 'Cảm ơn anh/chị đã để lại thông tin — T.A.M REHAB đây ạ',
            'body' => "Chào anh/chị,\n\nEm là Tâm, người đứng sau T.A.M REHAB — dịch vụ giãn cơ chuyên sâu 1:1 tại TP.HCM.\n\nCảm ơn anh/chị đã điền form danh sách chờ. Thật ra đây không phải danh sách chờ kiểu \"chờ xem có gì hot\" — em chỉ đang giữ chỗ cho những ai thật sự muốn hiểu cơ thể mình hơn, trước khi quyết định có nên thử một buổi giãn cơ hay không.\n\nVì sao anh/chị nên quan tâm:\n\n- T.A.M REHAB chỉ làm một việc: giãn cơ chuyên sâu 1:1. Không gym, không spa, không bán thêm gói thành viên.\n- Mỗi buổi 60 phút, em dành trọn cho một người — hỏi tình trạng, xem vùng cơ đang căng, điều chỉnh lực theo cảm nhận.\n- Em không hứa ai cũng hết đau sau buổi đầu. Nhưng em hứa sẽ nói thật tình trạng của anh/chị đang ở đâu.\n\nTrong 2 email tiếp theo (cách nhau vài ngày), em sẽ gửi:\n1. Một chia sẻ ngắn về điều hay gặp khi ngồi nhiều, tập nhiều mà không được giãn đúng cách.\n2. Thông tin cụ thể về buổi trải nghiệm giãn cơ 1:1 và ưu đãi dành cho anh/chị trong danh sách chờ.\n\nKhông cần làm gì lúc này. Cứ đọc khi rảnh, và reply lại cho em nếu muốn hỏi gì trước.\n\nCảm ơn anh/chị đã quan tâm đến T.A.M REHAB.\n\nTâm —\nT.A.M REHAB\nhttps://www.tamrehab.com",
        ],
        [
            'type' => 'nurture',
            'subject' => 'Ngồi 6 tiếng rồi vươn vai — mà vẫn cứng. Lý do thật ra không phải thiếu vận động',
            'body' => "Chào anh/chị,\n\nHôm trước em hỏi một khách: \"Anh/chị ngồi máy tính trung bình mỗi ngày mấy tiếng?\"\n\nCâu trả lời thường là 7–10 tiếng. Và điều khiến em buồn cười là — phần lớn mọi người đều vươn vai, xoay cổ, thậm chí đi bộ quanh phòng 5 phút. Nhưng tối về vẫn thấy cứng. Vẫn đau. Vẫn mỏi.\n\nThật ra, vấn đề không nằm ở việc anh/chị vận động ít.\n\nNó nằm ở chỗ: vùng cơ đang căng không tự biết cách thả ra.\n\nKhi ngồi lâu, một số nhóm cơ (cổ – vai – lưng trên, mặt trước hông, mặt sau đùi) bị rút ngắn và \"khoá\" lại ở trạng thái đó. Vươn vai hay xoay cổ chỉ tác động vài giây — chưa đủ để các thớ cơ sâu bên trong nhả ra.\n\nMột buổi giãn cơ chuyên sâu 1:1 khác ở chỗ đó: kỹ thuật viên đi vào đúng vùng đang khoá, giữ lực đủ lâu (thường 60–90 giây/vùng) cho đến khi cơ thật sự nhả — chứ không chỉ \"qua loa\" cho hết 60 phút.\n\n3 điều em hay thấy ở người ngồi nhiều:\n\n1. Vai không hẳn đau, nhưng cứ \"nặng\" cả ngày — thường là cơ nâng vai (trapezius trên) và cơ ức đòn chũm đang giữ quá nhiều.\n2. Đau lưng dưới mỗi khi đứng dậy — thường liên quan đến cơ thắt lưng – hông bị co rút, không phải thoát vị đĩa đệm ngay.\n3. Mỏi mắt kèm đau đầu sau gáy — cơ dưới chẩm (suboccipitals) đang khoá, ảnh hưởng lên thần kinh.\n\nEm không kể để chứng minh giãn cơ là \"thần thánh\". Em kể để anh/chị hiểu: nếu đã vươn vai, đi bộ, ngủ đủ mà vẫn cứng — rất có thể vấn đề nằm sâu hơn, và cần được xử đúng chỗ.\n\nỞ email sau, em sẽ chia sẻ cụ thể về buổi trải nghiệm giãn cơ 1:1 và ưu đãi dành riêng cho anh/chị trong danh sách chờ.\n\nReply email này nếu anh/chị muốn em gửi sớm hơn.\n\nTâm —\nT.A.M REHAB",
        ],
        [
            'type' => 'offer',
            'subject' => 'Ưu đãi 600K cho buổi giãn cơ 1:1 — dành riêng cho anh/chị trong danh sách chờ',
            'body' => "Chào anh/chị,\n\nHôm nay em gửi thông tin buổi trải nghiệm cụ thể — chỉ dành cho những anh/chị đã để lại thông tin trong danh sách chờ T.A.M REHAB.\n\nGói trải nghiệm giãn cơ chuyên sâu 1:1\n\n- Thời lượng: 60 phút, 1 kỹ thuật viên – 1 khách.\n- Giá gốc: 900.000 VND. Ưu đãi cho danh sách chờ: 600.000 VND (tiết kiệm 300K, áp dụng cho buổi đầu tiên).\n- Quy trình buổi đầu: hỏi tình trạng → xem biên độ vận động → xác định vùng cơ đang khoá → can thiệp đúng chỗ.\n- Không mua gói. Không membership. Không bán thêm. Hết buổi, anh/chị quyết định có quay lại hay không.\n\nAnh/chị sẽ nhận được gì sau buổi đầu tiên:\n\n1. Hiểu rõ vùng nào đang khoá, vùng nào chỉ đang mỏi thoáng qua — nhiều người đau một chỗ nhưng gốc rễ nằm chỗ khác.\n2. Cảm nhận ngay sự khác biệt về biên độ vận động — vặn vai sâu hơn, cúi sâu hơn, ngồi lâu không cứng bằng.\n3. Hướng dẫn 2–3 động tác tự chăm sóc tại nhà — phù hợp với tình trạng thật của anh/chị, không phải bài tập chung chung.\n4. Lời khuyên thẳng thắn — có nên quay lại không, bao lâu một lần, hay đang có vấn đề cần bác sĩ chuyên môn khác.\n\nEm không cam kết \"hết đau sau một buổi\". Cơ thể không vận hành theo công thức chung. Nhưng gần như 100% khách đều cảm nhận được sự khác biệt rõ rệt ngay khi đứng dậy — và đó mới là điều đáng để anh/chị tự kiểm chứng.\n\nĐặt lịch / Thanh toán ngay:\n👉 https://www.tamrehab.com/thanh-toan\n\nSlot đang mở theo tuần, em xếp lịch theo thứ tự đăng ký. Khi thanh toán xong, em sẽ nhắn Zalo xác nhận giờ cụ thể trong 24 giờ.\n\nNếu anh/chị còn phân vân — cứ reply email này hoặc nhắn Zalo 0902 499 162, em tư vấn trước, không cần quyết định ngay.\n\nCảm ơn anh/chị đã đọc đến email thứ 3. Đó đã là sự ưu tiên rồi ạ.\n\nTâm —\nT.A.M REHAB\nhttps://www.tamrehab.com",
        ],
    ];
}

function tamrehab_resend_api_key(): string
{
    $file = __DIR__ . '/resend_config.txt';
    $envKey = getenv('RESEND_API_KEY');

    if (is_file($file)) {
        $key = trim((string) file_get_contents($file));
        if ($key !== '') {
            $masked = substr($key, 0, 8) . '...' . substr($key, -4);
            tamrehab_log('Resend API key loaded from file (masked: ' . $masked . ')');
            return $key;
        }
    }

    if (is_string($envKey) && trim($envKey) !== '') {
        $envKey = trim($envKey);
        $masked = substr($envKey, 0, 8) . '...' . substr($envKey, -4);
        tamrehab_log('Resend API key loaded from env RESEND_API_KEY (masked: ' . $masked . ')');
        return $envKey;
    }

    tamrehab_log('Resend API key missing from resend_config.txt and env RESEND_API_KEY');
    return '';
}

function tamrehab_send_resend_email(string $toEmail, string $subject, string $body): array
{
    tamrehab_log('send_resend_email called: to=' . $toEmail . ' subject=' . $subject);
    $apiKey = tamrehab_resend_api_key();
    if ($apiKey === '') {
        tamrehab_log('Resend send failed: missing API key for ' . $toEmail);
        return ['success' => false, 'message' => 'Thiếu API key Resend trong backend/resend_config.txt'];
    }

    $payload = [
        'from' => 'hello@tamrehab.com',
        'to' => [$toEmail],
        'subject' => $subject,
        'html' => nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'), false),
        'reply_to' => 'hello@tamrehab.com',
    ];

    $ch = curl_init('https://api.resend.com/emails');
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
    $curlError = curl_error($ch);
    curl_close($ch);

    $apiKeyPreview = substr($apiKey, 0, 8) . '...' . substr($apiKey, -4);
    tamrehab_log('Resend call: to=' . $toEmail . ' subject=' . $subject . ' http_code=' . (string) $httpCode . ' api_key=' . $apiKeyPreview . ' response=' . (string) $response . ' curl_error=' . ($curlError ?: 'none'));

    if ($curlError !== '') {
        return ['success' => false, 'message' => 'Curl error: ' . $curlError];
    }

    $decoded = json_decode((string) $response, true);
    if ($httpCode >= 400 || !isset($decoded['id'])) {
        return ['success' => false, 'message' => $response ?: 'Resend request failed'];
    }

    return ['success' => true, 'provider_message_id' => (string) $decoded['id']];
}

function tamrehab_is_test_email(string $email): bool
{
    return preg_match('/\+test@/i', $email) === 1;
}

function tamrehab_send_sequence_now(string $toEmail): array
{
    $sent = [];
    foreach (tamrehab_email_sequence() as $emailItem) {
        $result = tamrehab_send_resend_email($toEmail, $emailItem['subject'], $emailItem['body']);
        $sent[] = [
            'type' => $emailItem['type'],
            'success' => $result['success'],
            'message' => $result['message'] ?? null,
        ];
    }
    return $sent;
}

function tamrehab_format_vnd($amount): string
{
    return number_format((float) $amount, 0, ',', '.') . ' VNĐ';
}

function tamrehab_order_receive_guide(string $productType): string
{
    if ($productType === 'physical') {
        return "Hướng dẫn nhận hàng:\n"
            . "- Em sẽ nhắn Zalo 0902 499 162 trong 24 giờ để chốt cách nhận (giao hoặc lấy trực tiếp).\n"
            . "- Nhận trực tiếp tại: 59/32 Đường số 4, Cư Xá Đô Thành, P. Bàn Cờ, TP.HCM.\n"
            . "- Giờ mở cửa: T2–T6 18:00–21:00; T7 13:00–21:00; CN 08:00–21:00.\n"
            . "- Khi đến, anh/chị chỉ cần nói mã đơn — em sẽ chuẩn bị sẵn.";
    }

    if ($productType === 'digital') {
        return "Hướng dẫn nhận hàng:\n"
            . "- File / hướng dẫn sẽ được gửi lại qua email này trong 24 giờ.\n"
            . "- Nếu chưa thấy, anh/chị kiểm tra hộp thư Spam / Quảng cáo, hoặc reply email này / nhắn Zalo 0902 499 162.";
    }

    return "Hướng dẫn nhận buổi:\n"
        . "- Em sẽ nhắn Zalo 0902 499 162 trong 24 giờ để chốt giờ cụ thể — anh/chị không cần tự xếp lịch trên web.\n"
        . "- Địa chỉ: 59/32 Đường số 4, Cư Xá Đô Thành, P. Bàn Cờ, TP.HCM.\n"
        . "- Giờ mở cửa: T2–T6 18:00–21:00; T7 13:00–21:00; CN 08:00–21:00.\n"
        . "- Anh/chị mặc đồ thoải mái, dễ cử động. Nếu đang đau sắc, tê, hoặc vừa chấn thương — nhắn em trước để mình xem buổi này có phù hợp không.";
}

function tamrehab_order_confirmation_email(array $order): array
{
    $name = trim((string) ($order['customer_name'] ?? ''));
    $greeting = $name !== '' ? ('Chào anh/chị ' . $name . ',') : 'Chào anh/chị,';
    $productName = trim((string) ($order['product_name'] ?? '')) ?: 'Dịch vụ T.A.M REHAB';
    $orderId = trim((string) ($order['order_id'] ?? ''));
    $quantity = max(1, (int) ($order['quantity'] ?? 1));
    $amount = tamrehab_format_vnd($order['amount'] ?? 0);
    $productType = trim((string) ($order['product_type'] ?? 'service')) ?: 'service';
    $guide = tamrehab_order_receive_guide($productType);

    $subject = ($orderId !== '' ? ('Em đã nhận đơn ' . $orderId) : 'Em đã nhận đơn hàng của anh/chị') . ' — cảm ơn vì đã tin T.A.M REHAB';

    $body = $greeting . "\n\n"
        . "Em là Tâm. Em vừa ghi nhận đơn của anh/chị trên hệ thống T.A.M REHAB.\n"
        . "Cảm ơn anh/chị đã tin một chỗ chỉ làm đúng một việc: giãn cơ chuyên sâu 1:1.\n\n"
        . "Thông tin đơn:\n"
        . "- Mã đơn: " . ($orderId !== '' ? $orderId : 'đang cập nhật') . "\n"
        . "- Sản phẩm / dịch vụ: " . $productName . "\n"
        . "- Số lượng: " . $quantity . "\n"
        . "- Số tiền: " . $amount . "\n\n"
        . $guide . "\n\n"
        . "Nếu anh/chị cần đổi giờ hoặc hỏi thêm trước buổi — cứ reply email này hoặc nhắn Zalo 0902 499 162. Em trả lời thẳng, không vòng vo.\n\n"
        . "Cảm ơn anh/chị một lần nữa. Em sẽ chăm sóc buổi này tử tế.\n\n"
        . "Tâm —\n"
        . "T.A.M REHAB\n"
        . "https://www.tamrehab.com";

    return [
        'subject' => $subject,
        'body' => $body,
    ];
}

function tamrehab_send_order_confirmation(PDO $pdo, array $order): array
{
    $toEmail = trim((string) ($order['email'] ?? ''));
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        tamrehab_log('Order confirmation skipped: missing/invalid email for order ' . (string) ($order['order_id'] ?? ''));
        return ['success' => false, 'skipped' => true, 'message' => 'Đơn đã lưu. Chưa gửi email vì thiếu địa chỉ email hợp lệ.'];
    }

    $content = tamrehab_order_confirmation_email($order);
    $result = tamrehab_send_resend_email($toEmail, $content['subject'], $content['body']);

    $customerId = isset($order['customer_id']) ? (int) $order['customer_id'] : null;
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO email_queue (customer_id, email_type, to_email, subject, body, send_at, status, sent_at, provider_message_id, error_message, created_at)
             VALUES (:customer_id, :email_type, :to_email, :subject, :body, :send_at, :status, :sent_at, :provider_message_id, :error_message, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            ':customer_id' => $customerId ?: null,
            ':email_type' => 'order_confirmation',
            ':to_email' => $toEmail,
            ':subject' => $content['subject'],
            ':body' => $content['body'],
            ':send_at' => date('Y-m-d H:i:s'),
            ':status' => $result['success'] ? 'sent' : 'failed',
            ':sent_at' => $result['success'] ? date('Y-m-d H:i:s') : null,
            ':provider_message_id' => $result['provider_message_id'] ?? null,
            ':error_message' => $result['success'] ? null : ($result['message'] ?? 'Unknown error'),
        ]);
    } catch (Throwable $e) {
        tamrehab_log('Order confirmation queue log failed: ' . $e->getMessage());
    }

    if ($result['success']) {
        $result['message'] = 'Đã gửi email xác nhận đơn hàng tới ' . $toEmail;
        if (tamrehab_is_test_email($toEmail)) {
            $result['sequence'] = tamrehab_send_sequence_now($toEmail);
            $ok = 0;
            foreach ($result['sequence'] as $item) {
                if (!empty($item['success'])) {
                    $ok++;
                }
            }
            $result['message'] = 'Đã gửi email xác nhận và ' . $ok . '/3 email chăm sóc (chế độ +test) tới ' . $toEmail;
        }
    }

    return $result;
}
