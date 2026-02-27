# BARGAIN SPORT — ระบบจองคอร์ตแบดมินตัน

ระบบจัดการและจองคอร์ตแบดมินตัน สำหรับใช้งานภายในองค์กร รองรับคอร์ตปกติและห้อง VIP พร้อมระบบสมาชิก โปรโมชั่น และอัปโหลดสลิปชำระเงิน

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.5 |
| Database | MySQL 8.0 |
| Frontend | Tailwind CSS (CDN) |
| Runtime | Docker / Docker Compose |

---

## Features

**การจอง**
- จองคอร์ตปกติและห้อง VIP
- ตรวจสอบการซ้อนทับเวลาอัตโนมัติ
- อัปโหลดสลิปการชำระเงิน (JPG / PNG / WebP สูงสุด 10MB)
- แก้ไข / เลื่อน / ยกเลิกการจอง

**ตารางคอร์ต**
- Timeline แนวนอน (06:00–23:00) พร้อมเส้นเวลาปัจจุบัน real-time
- มุมมองแบบการ์ด — สลับระหว่างสองมุมมองได้
- หน้าสาธารณะดูตารางโดยไม่ต้อง login

**ระบบราคา**
- กำหนดราคาตามกลุ่ม (Pricing Group) วันธรรมดา / วันหยุด / ช่วงเวลา
- ราคาคงที่ต่อคอร์ต (VIP / ปกติ)
- Priority: กลุ่มราคา > ราคาคงที่ > ราคา Global

**สมาชิก**
- ระดับ Bronze / Silver / Gold / Platinum
- ส่วนลดตามระดับ (0% / 5% / 10% / 15%)
- ติดตาม Points, จำนวนจอง, ยอดใช้จ่าย

**โปรโมชั่น**
- โค้ดส่วนลด กำหนดวันเริ่ม–สิ้นสุด
- ส่วนลดโปรโมชั่น override ส่วนลดสมาชิก

**Admin**
- จัดการคอร์ต, ราคา, สมาชิก, โปรโมชั่น, ผู้ใช้ระบบ
- Export รายงาน Excel

---

## Setup

**1. Clone และเริ่มต้น**

```bash
git clone https://github.com/B0atByte/Badcourd.git
cd Badcourd
docker compose up -d
```

**2. Import ฐานข้อมูล**

```bash
docker exec -i mysql-db mysql -u root -prootpassword badcourt < SQL/badcourt.sql
```

**3. เข้าใช้งาน**

| Service | URL |
|---|---|
| ระบบหลัก | http://localhost:8085 |
| phpMyAdmin | http://localhost:8081 |

---

## Project Structure

```
/
├── admin/               จัดการข้อมูล (courts, pricing, members, promotions, users)
├── auth/                login, logout, guard middleware
├── bookings/            create, index, update, cancel, AJAX endpoints
├── config/              db.php (PDO connection)
├── includes/            header, footer, helpers (pricing functions)
├── members/             ค้นหาและดูโปรไฟล์สมาชิก
├── reports/             export Excel
├── SQL/                 migration files
├── uploads/slips/       ไฟล์สลิปที่อัปโหลด
├── timetable.php        ตารางสาธารณะ (ไม่ต้อง login)
└── timetable_detail.php ตารางพร้อมรายละเอียด (ต้อง login)
```

---

## Database Tables

| Table | Description |
|---|---|
| courts | คอร์ต (court_type, vip_room_name, pricing_group_id) |
| bookings | การจอง พร้อม payment_slip_path |
| pricing_rules | กฎราคาตาม group_id, day_type, ช่วงเวลา |
| pricing_groups | กลุ่มราคา |
| members | สมาชิก พร้อม level และ points |
| promotions | โปรโมชั่น / โค้ดส่วนลด |
| users | ผู้ใช้ระบบ (admin / user) |
| booking_logs | log การเปลี่ยนแปลงการจอง |
| point_transactions | ประวัติ points สมาชิก |

---

## Author

Boat Patthanapong &nbsp;|&nbsp; BARGAIN SPORT
