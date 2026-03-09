# BARGAIN SPORT — ระบบจองคอร์ตแบดมินตัน & คลาสโยคะ

ระบบจัดการสำหรับศูนย์กีฬา รองรับการจองคอร์ตแบดมินตัน (ปกติ/VIP) และระบบคลาสโยคะพร้อม Package Management ครบวงจร

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.1+ |
| Database | MySQL 8.0 |
| Frontend | Tailwind CSS (CDN), SweetAlert2 |
| Runtime | Docker / Docker Compose |
| Library | PhpSpreadsheet |

---

## Features

### ระบบจองคอร์ตแบดมินตัน

**การจอง**
- จองคอร์ตปกติและห้อง VIP
- ตรวจสอบการซ้อนทับเวลาอัตโนมัติ (SQL overlap query)
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
- เพิ่มสมาชิกได้โดยตรง หรืออัตโนมัติเมื่อจองสำเร็จ
- ระดับ Bronze / Silver / Gold / Platinum
- ส่วนลดตามระดับ (0% / 5% / 10% / 15%)
- ติดตาม Points, จำนวนจอง, ยอดใช้จ่าย

**โปรโมชั่น**
- โค้ดส่วนลด กำหนดวันเริ่ม–สิ้นสุด
- ลดเป็น % หรือลดราคาลงตรงๆ
- ส่วนลดโปรโมชั่น override ส่วนลดสมาชิก

---

### ระบบคลาสโยคะ

**จัดการคลาส** (`admin/yoga_classes.php`)
- ดูตารางคลาสรายวัน พร้อม navigation ย้อน/ไปหน้า
- สร้างคลาสใหม่ (วัน / เวลา / ห้อง / ครูผู้สอน / จำนวนที่นั่ง)
- เพิ่มนักเรียนเข้าคลาส — ค้นหาจากเบอร์โทรเพื่อดึงแพ็กเกจอัตโนมัติ
- เช็คชื่อเข้าเรียน (attended) — หักครั้งจากแพ็กเกจทันที
- ยกเลิกการจอง — คืนครั้งให้แพ็กเกจหากเคยเช็คแล้ว

**จัดการแพ็กเกจ** (`admin/yoga_packages.php`)
- CRUD ประเภทแพ็กเกจ (เพิ่ม / แก้ไข / เปิด-ปิด / ลบ)
- ขายแพ็กเกจให้สมาชิก พร้อมกำหนดวันหมดอายุอัตโนมัติ
- รองรับครั้งโบนัส (เช่น 10+2 = 12 ครั้ง)
- ตรวจสอบครั้งที่เหลือ / สถานะแพ็กเกจ (active / expired / empty)

---

### แพ็กเกจคอร์ตแบดมินตัน (`admin/badminton_packages.php`)

- CRUD ประเภทแพ็กเกจแบดมินตัน (ชั่วโมงรวม / ราคา / วันหมดอายุ)
- ขายแพ็กเกจให้สมาชิก — ชำระครั้งเดียว ใช้จองได้หลายครั้ง
- อัปโหลดสลิปชำระแพ็กเกจ (drag-and-drop / preview / lightbox)
- ปรับข้อมูลย้อนหลัง (hours_used, purchase_date, expiry_date, notes)
- Progress bar ชั่วโมงคงเหลือ, ค้นหา/pagination
- แสดงข้อมูลโปรโมชั่น + แพ็กเกจใน timetable modal

### Admin

- จัดการคอร์ต, ราคา, สมาชิก, โปรโมชั่น, ผู้ใช้ระบบ
- จัดการคลาสโยคะ & แพ็กเกจ
- Export รายงาน Excel (2 sheets: การจอง + ยอดซื้อแพ็กเกจ)

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

**3. รัน Index Migration (ครั้งแรก)**

```bash
docker exec -i mysql-db mysql -u root -prootpassword badcourt < SQL/add_indexes.sql
```

**4. เข้าใช้งาน**

| Service | URL |
|---|---|
| ระบบหลัก | http://localhost:8085 |
| phpMyAdmin | http://localhost:8081 |

**Default Login**

| Role | Username | Password |
|---|---|---|
| Admin | admin | (ตั้งเอง) |
| User | user | (ตั้งเอง) |

---

## Project Structure

```
/
├── admin/
│   ├── courts.php                   จัดการคอร์ต
│   ├── members.php                  จัดการสมาชิก (เพิ่ม/แก้ไข/ลบ/ปรับแต้ม)
│   ├── pricing.php                  จัดการราคา
│   ├── promotions.php               โปรโมชั่น
│   ├── users.php                    จัดการผู้ใช้ระบบ
│   ├── badminton_packages.php       จัดการแพ็กเกจแบดมินตัน
│   ├── upload_badminton_slip_ajax.php AJAX อัปโหลดสลิปแพ็กเกจแบดมินตัน
│   ├── yoga_classes.php             จัดการคลาสโยคะ
│   ├── yoga_packages.php            จัดการแพ็กเกจโยคะ
│   └── yoga_pkg_ajax.php            AJAX ค้นหาแพ็กเกจ
├── auth/                   login, logout, guard middleware
├── bookings/               create, index, update, cancel, AJAX endpoints
├── config/                 db.php (PDO connection)
├── includes/               header, footer, helpers, pagination
├── members/                ค้นหาและดูโปรไฟล์สมาชิก
├── reports/                export Excel
├── SQL/
│   ├── badcourt.sql        schema + seed data
│   └── add_indexes.sql     performance indexes
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
| bookings | การจอง พร้อม payment_slip_path, member_badminton_package_id |
| pricing_rules | กฎราคาตาม group_id, day_type, ช่วงเวลา |
| pricing_groups | กลุ่มราคา |
| members | สมาชิก พร้อม level และ points |
| promotions | โปรโมชั่น / โค้ดส่วนลด |
| users | ผู้ใช้ระบบ (admin / user) |
| booking_logs | log การเปลี่ยนแปลงการจอง |
| point_transactions | ประวัติ points สมาชิก |
| badminton_package_types | ประเภทแพ็กเกจแบดมินตัน (ชั่วโมง / โบนัส / ราคา) |
| member_badminton_packages | แพ็กเกจที่สมาชิกซื้อ + hours_used + payment_slip_path |

### Yoga

| Table | Description |
|---|---|
| yoga_package_types | ประเภทแพ็กเกจ (ครั้ง / โบนัส / ราคา / อายุ) |
| yoga_courses | ตารางคลาส (วัน / เวลา / ห้อง / ครู) |
| member_yoga_packages | แพ็กเกจที่สมาชิกซื้อ + ครั้งที่เหลือ + วันหมดอายุ |
| yoga_bookings | การจองคลาส (booked / attended / cancelled) |

---

## Changelog

### v1.7 — 2026-03-09
**Security Improvements**

- ย้าย database credentials ออกจาก `config/db.php` ไปใช้ `.env` (ป้องกัน credential exposure)
- เพิ่ม `.env.example` เป็น template สำหรับ setup ใหม่
- อัปเดต `docker-compose.yml` ให้ PHP container รับ env vars จาก `env_file`
- แก้ XSS ใน `includes/swal_flash.php` — เปลี่ยน `addslashes()` เป็น `json_encode()` ใน JavaScript context

### v1.6 — 2026-03-09
**New Theme & Badminton Package System**

**UI Theme**
- เปลี่ยน color theme ทั้งเว็บจากน้ำเงิน → แดง/ส้ม/เหลือง
  - Primary: `#D32F2F` | Dark: `#B71C1C` | Orange: `#F57C00` | Yellow: `#FBC02D` | Teal: `#00897B`
- อัปเดต 17 ไฟล์ (header, login, timetable, bookings, admin pages, reports)

**รายงาน Excel (export_excel.php)**
- เพิ่ม Sheet 2 "ยอดซื้อแพ็กเกจ" — แสดงยอดชำระครั้งเดียวของแต่ละแพ็กเกจแบดมินตัน
- เพิ่ม stats card ยอดรายได้จากแพ็กเกจ + preview table ในหน้า HTML
- Sheet 1 เพิ่ม 3 คอลัมน์: แพ็กเกจที่ใช้, ชม.จากแพ็กเกจ, สลิปแพ็กเกจ

### v1.5 — 2026-03-09
**Badminton Package System**

**แพ็กเกจคอร์ตแบดมินตัน**
- สร้าง `admin/badminton_packages.php` — แสดงรายการสมาชิกแบบเดียวกับ yoga packages
- อัปโหลดสลิปชำระแพ็กเกจ: drag-and-drop, preview, lightbox
- ปรับข้อมูลย้อนหลัง: hours_used, purchase_date, expiry_date, notes
- Progress bar ชั่วโมงคงเหลือ, ค้นหา/pagination
- สร้าง `admin/upload_badminton_slip_ajax.php` — AJAX endpoint อัปโหลดสลิป (ตรวจ MIME, max 10MB)

**Timetable**
- `timetable_detail.php` — modal แสดงข้อมูลโปรโมชั่น + แพ็กเกจแบดมินตัน (ชม.ใช้/คงเหลือ/progress bar/สลิป)

**Database**
- `ALTER TABLE member_badminton_packages ADD COLUMN payment_slip_path VARCHAR(255) NULL`

### v1.4 — 2026-03-06
**Bug Fixes & Security**
- แก้ `?>` ใน comment ของ `includes/swal_flash.php` ที่ทำให้ PHP code แสดงเป็น raw text ทุกหน้า
- เพิ่ม `guard.php` ใน `members/check.php` (security: unauthenticated access)
- เพิ่ม `require_permission('members')` ใน `members/profile.php`
- แก้ปุ่มเข้าสู่ระบบสีแดง `#FF0000` → `#005691` ใน `timetable.php` (2 จุด) และ `auth/login.php`
- เพิ่ม redirect ถ้า login อยู่แล้วใน `auth/login.php`
- ลบ SweetAlert2 `<script>` ที่โหลดซ้ำใน `admin/members.php`, `admin/yoga_classes.php`, `admin/yoga_packages.php`, `timetable_detail.php`
- แก้ `swalDelete()` ให้รองรับ parameter `title` แทนที่จะ hardcode
- แก้ `bookings/index.php` — เรียก `swalDelete()` ผิด parameter
- เพิ่ม `discount_type` ใน `bookings/create.php` และ `bookings/check_promotion.php` รองรับส่วนลดแบบ fixed บาท
- ลบ debug button "ทดสอบแจ้งเตือน" และ `window.testAlert` ออกจาก `timetable_detail.php`
- แก้ `confirmCancel()` ใน `timetable_detail.php` จาก native `confirm()` เป็น SweetAlert2

### v1.3 — 2026-03-06
- เพิ่ม SweetAlert2 ทั้งระบบ (toast สำเร็จ, popup error, dialog ยืนยันลบ/ยกเลิก)
- สร้าง `includes/swal_flash.php` — shared utility สำหรับ flash messages ทุกหน้า
- เพิ่ม SweetAlert2 CDN ใน `header.php` ให้ใช้ได้ทุกหน้าโดยไม่ต้องโหลดซ้ำ
- อัปเดต 10 หน้า: courts, promotions, members, users, pricing, yoga_classes, yoga_packages, bookings/index, create, update

### v1.2 — 2026-03-06
- เพิ่มฟีเจอร์เพิ่มสมาชิกด้วยตนเอง (ไม่ต้องรอผ่านการจอง)
- แก้ `has_overlap()` จาก PHP loop เป็น SQL query (ประสิทธิภาพสูงขึ้นมาก)
- รวม stats queries 3 อันเป็น 1 conditional aggregation query
- เพิ่ม 6 performance indexes สำหรับรองรับข้อมูลจำนวนมาก

### v1.1 — 2026-03-02
- เพิ่มระบบคลาสโยคะ (CRUD คลาส, เพิ่มนักเรียน, เช็คชื่อ)
- เพิ่มระบบ Package Management (เพิ่ม/แก้ไข/ลบ ประเภทแพ็กเกจ)
- ขายแพ็กเกจ + ตัด/คืนครั้งอัตโนมัติ
- เพิ่มเมนู คลาสโยคะ / แพ็กเกจโยคะ ใน header

### v1.0 — 2026-02-25
- Initial release: ระบบจองคอร์ตแบดมินตันเต็มรูปแบบ

---

## Author

Boat Patthanapong &nbsp;|&nbsp; BARGAIN SPORT
