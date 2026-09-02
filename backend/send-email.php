<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';

function tamrehab_log(string $message): void
{
    $logFile = __DIR__ . '/email_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, '[' . $timestamp . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
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
            return $key;
        }
    }

    if (is_string($envKey) && trim($envKey) !== '') {
        return trim($envKey);
    }

    tamrehab_log('Resend API key missing from resend_config.txt and env RESEND_API_KEY');
    return '';
}

function tamrehab_send_resend_email(string $toEmail, string $subject, string $body): array
{
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

function tamrehab_upsert_customer(PDO $pdo, string $name, string $phone, string $email): int
{
    $email = trim($email);
    $phone = trim($phone);
    $name = trim($name);

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Email không hợp lệ');
    }

    if ($email !== '') {
        $stmt = $pdo->prepare('SELECT id FROM customers WHERE LOWER(email) = LOWER(:email) LIMIT 1');
        $stmt->execute([':email' => $email]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($customer) {
            $customerId = (int) $customer['id'];
            $pdo->prepare('UPDATE customers SET name = :name, phone = :phone WHERE id = :id')->execute([
                ':name' => $name,
                ':phone' => $phone,
                ':id' => $customerId,
            ]);
            return $customerId;
        }
    }

    if ($phone !== '') {
        $stmt = $pdo->prepare('SELECT id FROM customers WHERE phone = :phone LIMIT 1');
        $stmt->execute([':phone' => $phone]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($customer) {
            $customerId = (int) $customer['id'];
            if ($email !== '') {
                $pdo->prepare('UPDATE customers SET name = :name, email = :email WHERE id = :id')->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':id' => $customerId,
                ]);
            }
            return $customerId;
        }
    }

    $stmt = $pdo->prepare('INSERT INTO customers (name, phone, email, zalo, registered_at, created_at) VALUES (:name, :phone, :email, :zalo, CURRENT_DATE, CURRENT_TIMESTAMP)');
    $stmt->execute([
        ':name' => $name,
        ':phone' => $phone !== '' ? $phone : null,
        ':email' => $email !== '' ? $email : null,
        ':zalo' => '',
    ]);

    return (int) $pdo->lastInsertId();
}

function tamrehab_queue_email(PDO $pdo, int $customerId, string $emailType, string $toEmail, string $subject, string $body, string $sendAt): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO email_queue (customer_id, email_type, to_email, subject, body, send_at, status, created_at) VALUES (:customer_id, :email_type, :to_email, :subject, :body, :send_at, :status, CURRENT_TIMESTAMP)'
    );
    $stmt->execute([
        ':customer_id' => $customerId,
        ':email_type' => $emailType,
        ':to_email' => $toEmail,
        ':subject' => $subject,
        ':body' => $body,
        ':send_at' => $sendAt,
        ':status' => 'pending',
    ]);
}

function tamrehab_process_due_email_queue(PDO $pdo): void
{
    $rows = $pdo->query("SELECT * FROM email_queue WHERE status = 'pending' AND send_at <= datetime('now') ORDER BY send_at ASC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $result = tamrehab_send_resend_email((string) $row['to_email'], (string) $row['subject'], (string) $row['body']);
        $status = $result['success'] ? 'sent' : 'failed';
        $pdo->prepare(
            'UPDATE email_queue SET status = :status, sent_at = :sent_at, provider_message_id = :provider_message_id, error_message = :error_message WHERE id = :id'
        )->execute([
            ':status' => $status,
            ':sent_at' => $result['success'] ? date('Y-m-d H:i:s') : null,
            ':provider_message_id' => $result['provider_message_id'] ?? null,
            ':error_message' => $result['success'] ? null : ($result['message'] ?? 'Unknown error'),
            ':id' => (int) $row['id'],
        ]);
    }
}

function tamrehab_is_test_email(string $email): bool
{
    return preg_match('/\+test@/i', $email) === 1;
}

$pdo = tamrehab_db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$name = trim((string) ($input['name'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$phone = trim((string) ($input['phone'] ?? ''));
$source = trim((string) ($input['source'] ?? 'waitlist'));

tamrehab_log('Incoming request: source=' . $source . ' name=' . $name . ' email=' . $email . ' phone=' . $phone);

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email không hợp lệ']);
    exit;
}

if ($name === '' || ($email === '' && $phone === '')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập tên và ít nhất một thông tin liên hệ']);
    exit;
}

try {
    $customerId = tamrehab_upsert_customer($pdo, $name, $phone, $email);
    $sequence = tamrehab_email_sequence();
    $testMode = tamrehab_is_test_email($email);

    if ($testMode) {
        $sent = [];
        foreach ($sequence as $emailItem) {
            $result = tamrehab_send_resend_email($email, $emailItem['subject'], $emailItem['body']);
            $sent[] = ['type' => $emailItem['type'], 'success' => $result['success'], 'message' => $result['message'] ?? null];
        }

        echo json_encode([
            'success' => true,
            'test' => true,
            'message' => 'Đã gửi email test',
            'customer_id' => $customerId,
            'sent' => $sent,
            'source' => $source,
        ]);
        exit;
    }

    $now = new DateTimeImmutable('now');
    $finalPayload = [
        'customer_id' => $customerId,
        'source' => $source,
        'test' => false,
    ];

    $first = $sequence[0];
    $firstResult = tamrehab_send_resend_email($email, $first['subject'], $first['body']);
    $finalPayload['email_1'] = $firstResult;

    if ($firstResult['success']) {
        $firstQueueData = [
            'customer_id' => $customerId,
            'email_type' => $first['type'],
            'to_email' => $email,
            'subject' => $first['subject'],
            'body' => $first['body'],
            'send_at' => $now->format('Y-m-d H:i:s'),
            'status' => 'sent',
            'sent_at' => $now->format('Y-m-d H:i:s'),
            'provider_message_id' => $firstResult['provider_message_id'] ?? null,
        ];
        $pdo->prepare('INSERT INTO email_queue (customer_id, email_type, to_email, subject, body, send_at, status, sent_at, provider_message_id, created_at) VALUES (:customer_id, :email_type, :to_email, :subject, :body, :send_at, :status, :sent_at, :provider_message_id, CURRENT_TIMESTAMP)')->execute($firstQueueData);
    }

    $queueAt = [
        1 => $now->modify('+2 days')->format('Y-m-d H:i:s'),
        2 => $now->modify('+3 days')->format('Y-m-d H:i:s'),
    ];

    for ($i = 1; $i <= 2; $i++) {
        $item = $sequence[$i];
        tamrehab_queue_email($pdo, $customerId, $item['type'], $email, $item['subject'], $item['body'], $queueAt[$i]);
    }

    tamrehab_process_due_email_queue($pdo);

    echo json_encode([
        'success' => true,
        'test' => false,
        'message' => 'Đã lưu khách hàng và lên lịch email sequence',
        'customer_id' => $customerId,
        'email_1' => $firstResult,
        'scheduled' => [
            'email_2' => $queueAt[1],
            'email_3' => $queueAt[2],
        ],
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi khi gửi email sequence: ' . $e->getMessage(),
    ]);
}
