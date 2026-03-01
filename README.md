# BARGAIN SPORT — ระบบจองคอร์ตแบดมินตัน & คลาสโยคะ

ระบบจัดการสำหรับศูนย์กีฬา รองรับการจองคอร์ตแบดมินตัน (ปกติ/VIP) และระบบคลาสโยคะพร้อม Package Management ครบวงจร

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.1+ |
| Database | MySQL 8.0 |
| Frontend | Tailwind CSS (CDN) |
| Runtime | Docker / Docker Compose |
| Library | SweetAlert2, PhpSpreadsheet |

---

## Features

### 🏸 ระบบจองคอร์ตแบดมินตัน

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
- ลดเป็น % หรือลดราคาลงตรงๆ
- ส่วนลดโปรโมชั่น override ส่วนลดสมาชิก

---

### 🧘 ระบบคลาสโยคะ (ใหม่)

**จัดการคลาส** (`admin/yoga_classes.php`)
- ดูตารางคลาสรายวัน พร้อม navigation ย้อน/ไปหน้า
- สร้างคลาสใหม่ (วัน / เวลา / ห้อง / ครูผู้สอน / จำนวนที่นั่ง)
- เพิ่มนักเรียนเข้าคลาส — ค้นหาจากเบอร์โทรเพื่อดึงแพ็กเกจอัตโนมัติ
- เช็คชื่อเข้าเรียน (attended) — หักครั้งจากแพ็กเกจทันที
- ยกเลิกการจอง — คืนครั้งให้แพ็กเกจหากเคยเช็คแล้ว
- ลบคลาส

**จัดการแพ็กเกจ** (`admin/yoga_packages.php`)
- CRUD ประเภทแพ็กเกจ (เพิ่ม / แก้ไข / เปิด-ปิด / ลบ)
- ขายแพ็กเกจให้สมาชิก พร้อมกำหนดวันหมดอายุอัตโนมัติ
- ตรวจสอบครั้งที่เหลือ / สถานะแพ็กเกจ (active / expired / empty)
- ค้นหาสมาชิกจากชื่อหรือเบอร์โทร

**Package Logic**
- รองรับครั้งโบนัส (เช่น 10+2 = 12 ครั้ง)
- กำหนดอายุแพ็กเกจ (validity_days) หรือไม่มีวันหมดอายุ
- ป้องกันลบประเภทแพ็กเกจที่มีสมาชิกใช้งาน

---

### 🔧 Admin

- จัดการคอร์ต, ราคา, สมาชิก, โปรโมชั่น, ผู้ใช้ระบบ
- จัดการคลาสโยคะ & แพ็กเกจ
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
├── admin/
│   ├── courts.php          จัดการคอร์ต
│   ├── members.php         จัดการสมาชิก
│   ├── promotions.php      โปรโมชั่น
│   ├── yoga_classes.php    จัดการคลาสโยคะ  ← ใหม่
│   ├── yoga_packages.php   จัดการแพ็กเกจโยคะ  ← ใหม่
│   └── yoga_pkg_ajax.php   AJAX ค้นหาแพ็กเกจ  ← ใหม่
├── auth/                   login, logout, guard middleware
├── bookings/               create, index, update, cancel, AJAX
├── config/                 db.php (PDO connection)
├── includes/               header, footer, helpers
├── members/                ค้นหาและดูโปรไฟล์สมาชิก
├── reports/                export Excel
├── SQL/                    migration files
├── uploads/slips/          ไฟล์สลิปที่อัปโหลด
├── timetable.php           ตารางสาธารณะ
└── timetable_detail.php    ตารางพร้อมรายละเอียด
```

---

## Database Tables

### Badminton

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

### Yoga (ใหม่)

| Table | Description |
|---|---|
| yoga_package_types | ประเภทแพ็กเกจ (ครั้ง / โบนัส / ราคา / อายุ) |
| yoga_courses | ตารางคลาส (วัน / เวลา / ห้อง / ครู) |
| member_yoga_packages | แพ็กเกจที่สมาชิกซื้อ + ครั้งที่เหลือ + วันหมดอายุ |
| yoga_bookings | การจองคลาส (booked / attended / cancelled) |

---

## Changelog

### v1.1 — 2026-03-02
- ✨ เพิ่มระบบคลาสโยคะ (CRUD คลาส, เพิ่มนักเรียน, เช็คชื่อ)
- ✨ เพิ่มระบบ Package Management (เพิ่ม/แก้ไข/ลบ ประเภทแพ็กเกจ)
- ✨ ขายแพ็กเกจ + ตัด/คืนครั้งอัตโนมัติ
- 🐛 แก้ `session_start()` ซ้ำใน yoga_classes.php
- 🐛 แก้ encoding ภาษาไทยใน MySQL ENUM → VARCHAR
- 🐛 แก้ NULL value ใน admin/members.php stats
- 💄 ลบ emoji icon ที่ไม่จำเป็นออกจาก UI
- 🔧 เพิ่มเมนู คลาสโยคะ / แพ็กเกจโยคะ ใน header

### v1.0 — 2026-02-25
- 🎉 Initial release: ระบบจองคอร์ตแบดมินตันเต็มรูปแบบ

---

## Author

Boat Patthanapong &nbsp;|&nbsp; BARGAIN SPORT
