# Chuỗi email T.A.M REHAB

Người gửi: `hello@tamrehab.com`

Nội dung email được gửi từ code trong `backend/mailer.php` (không đọc trực tiếp từ file markdown này — file này là tài liệu để theo dõi).

## 1. Chào mừng (`welcome`)
**Subject:** `Chào bạn, T.A.M REHAB xin gửi lời cảm ơn!`

Cảm ơn khách đã để lại thông tin, giới thiệu dịch vụ giãn cơ trị liệu chuyên sâu 1:1, nhắc giá trải nghiệm, mời đặt buổi khi sẵn sàng (không ép).

## 2. Nurture (`nurture`)
**Subject:** `Ngồi 6 tiếng rồi? Cơ thể bạn đang kêu cứu đấy!`

Chia sẻ insight về việc ngồi lâu làm bó cứng vai gáy/lưng, phân biệt giãn cơ chuyên sâu vs massage thông thường. Không bán hàng.

## 3. Chốt (`close`)
**Subject:** `Ưu đãi 600K cho buổi giãn cơ 1:1 – dành riêng cho bạn`

Giới thiệu gói trải nghiệm 600.000đ/60 phút 1:1 (đánh giá vận động + giãn cơ chuyên sâu + hướng dẫn tự chăm sóc), không membership, CTA đặt lịch / Zalo 0902 499 162.

## 4. Xác nhận đơn hàng (`order`)
**Subject:** `Đã nhận đơn hàng {order_id} – T.A.M REHAB sẽ xác nhận qua Zalo`

Gửi tự động khi admin thêm đơn hàng mới (nếu có email khách). Nội dung gồm: tên sản phẩm/dịch vụ, số tiền, mã đơn, dòng xác nhận lịch hẹn qua Zalo trong 15 phút, hướng dẫn nhận buổi/hàng, lời cảm ơn.

---

## Lưu ý
- Chính tả chuẩn: **giãn cơ** (không viết "giấm cờ").
- Giọng văn: gần gũi, thẳng thắn, không hoa mỹ, không ép mua, không hứa chữa khỏi.
- Địa chỉ người gửi: `hello@tamrehab.com`.

## Test
```
https://tamrehab-backend.onrender.com/send-email.php?type=order+test&to=you@email.com
https://tamrehab-backend.onrender.com/backend/send-email.php?type=welcome+test&to=you@email.com
https://tamrehab-backend.onrender.com/backend/send-email.php?type=nurture+test&to=you@email.com
https://tamrehab-backend.onrender.com/backend/send-email.php?type=close+test&to=you@email.com
```
