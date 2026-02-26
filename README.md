# BARGAIN SPORT — ระบบจัดการคอร์ตแบดมินตัน

> Badminton Court Booking & Management System

ระบบบริหารจัดการคอร์ตแบดมินตันครบวงจร พัฒนาด้วย PHP + MySQL รองรับการใช้งานผ่าน Docker รองรับทั้งการจองคอร์ต การจัดการสมาชิก โปรโมชั่น และการออกรายงาน

---

## Features

### การจองและตารางคอร์ต
- **ตารางคอร์ต Real-time** — แสดงสถานะคอร์ตทุกคอร์ตรายชั่วโมง มองเห็นช่วงว่าง/ถูกจองได้ทันที
- **จองคอร์ต** — ฟอร์มจองพร้อมเช็คสมาชิกอัตโนมัติจากเบอร์โทร, รองรับหลายชื่อต่อเบอร์ (dropdown เลือก)
- **แนบสลิปการชำระเงิน** — อัปโหลดรูปสลิปพร้อม preview ก่อน submit (JPG/PNG/WEBP ไม่เกิน 10MB)
- **คำนวณราคาอัตโนมัติ** — คำนวณราคาตาม pricing rules (วันธรรมดา/วันหยุด, ช่วงเวลา) และประเภทคอร์ต
- **รองรับคอร์ต VIP** — ห้อง VIP มีราคาและชื่อแยกต่างหาก
- **เลื่อน/ยกเลิกการจอง** — จัดการรายการจองที่มีอยู่ได้

### ระบบสมาชิก
- **สมัครสมาชิกอัตโนมัติ** — เมื่อมีการจองครั้งแรกด้วยเบอร์โทรใหม่ ระบบสร้างสมาชิกให้อัตโนมัติ
- **ระดับสมาชิก 4 ระดับ** — Bronze → Silver → Gold → Platinum (อิงจากยอดใช้จ่ายสะสม)
- **ระบบคะแนนสะสม** — ทุก ฿100 = 1 แต้ม บันทึกในตาราง point_transactions
- **ส่วนลดตามระดับ** — Silver 5%, Gold 10%, Platinum 15%
- **โปรไฟล์สมาชิก** — แสดงประวัติการจอง, ยอดใช้จ่าย, คะแนน, ระดับ

### ระบบโปรโมชั่น
- **สร้างโปรโมชั่น** — กำหนด code, ชื่อ, % ส่วนลด, วันเริ่ม-สิ้นสุด
- **ใช้ได้ 2 วิธี** — เลือกจาก dropdown หรือพิมพ์รหัสโปรโมชั่นเอง
- **ตรวจสอบ real-time** — AJAX ตรวจสอบรหัสโปรโมชั่นก่อน submit
- **โปรโมชั่นแทนส่วนลดสมาชิก** — ใช้ได้อย่างใดอย่างหนึ่ง (ไม่ซ้อน)

### รายงานและส่งออก
- **Export Excel** — ดาวน์โหลดรายงานการจองกรองตามช่วงวันที่ได้
- **Dashboard สถิติ** — ยอดจอง, รายได้รวม, จำนวนสมาชิก

### ระบบหลังบ้าน (Admin)
- **จัดการคอร์ต** — เพิ่ม/แก้ไข/ปิดใช้งานคอร์ต พร้อม search + filter
- **ตั้งค่าราคา** — กำหนด pricing rules ตามวันและช่วงเวลา
- **จัดการสมาชิก** — ดูและค้นหาสมาชิกทั้งหมด
- **จัดการผู้ใช้งาน** — เพิ่ม/แก้ไข/เปิด-ปิดบัญชีผู้ใช้ระบบ
- **จัดการโปรโมชั่น** — CRUD โปรโมชั่น พร้อม filter สถานะ (active/expired)

### UI/UX
- **Responsive Design** — รองรับทั้ง Desktop และ Mobile
- **Smooth Navigation** — CSS View Transitions API (ไม่กระพริบเมื่อเปลี่ยนหน้า)
- **Pagination ทุกหน้า** — เลือกจำนวนรายการที่แสดงได้, รองรับข้อมูลปริมาณมาก
- **Corporate Design** — ใช้ SVG icons, สีหลัก `#005691`, ไม่มี emoji

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2 |
| Database | MySQL 8.0 |
| Frontend | Tailwind CSS (CDN) |
| Excel Export | PhpSpreadsheet 5.x |
| Containerization | Docker + Docker Compose |
| Web Server | Apache (mod_php) |

---

## Project Structure

```
BARGAIN_SPORT/
├── admin/
│   ├── courts.php          # จัดการคอร์ต (CRUD + search/filter/pagination)
│   ├── members.php         # จัดการสมาชิก
│   ├── pricing.php         # ตั้งค่าราคาตามช่วงเวลา
│   ├── promotions.php      # จัดการโปรโมชั่น (CRUD)
│   └── users.php           # จัดการผู้ใช้งานระบบ
├── auth/
│   ├── guard.php           # ตรวจสอบสิทธิ์ (redirect ถ้าไม่ได้ login)
│   ├── login.php           # หน้าเข้าสู่ระบบ
│   └── logout.php          # ออกจากระบบ
├── bookings/
│   ├── create.php          # สร้างการจอง + แนบสลิป + auto-member
│   ├── index.php           # รายการจองทั้งหมด + ดูสลิป modal
│   ├── update.php          # เลื่อนการจอง
│   ├── cancel.php          # ยกเลิกการจอง
│   ├── get_price_ajax.php  # AJAX คำนวณราคา
│   └── check_promotion.php # AJAX ตรวจสอบรหัสโปรโมชั่น
├── config/
│   ├── db.php              # PDO connection (MySQL)
│   └── base_path.php       # Base path config
├── includes/
│   ├── header.php          # Navigation bar (SVG icons, dropdown admin)
│   ├── footer.php          # Footer (brand + copyright)
│   ├── helpers.php         # ฟังก์ชัน: pick_price_per_hour, compute_total, has_overlap
│   └── pagination.php      # Reusable pagination component
├── members/
│   ├── check.php           # AJAX ตรวจสอบสมาชิกจากเบอร์โทร
│   ├── profile.php         # โปรไฟล์สมาชิก + ประวัติการจอง
│   └── search.php          # ค้นหาสมาชิก (search/filter/pagination)
├── reports/
│   └── export_excel.php    # Export รายงาน Excel (PhpSpreadsheet)
├── SQL/
│   ├── badcourt.sql        # Schema หลัก + ข้อมูลตั้งต้น
│   ├── members_system.sql  # ตาราง members + point_transactions
│   ├── promotions_system.sql # ตาราง promotions + ข้อมูลตัวอย่าง
│   └── slip_migration.sql  # ADD COLUMN payment_slip_path
├── uploads/
│   └── slips/              # รูปสลิปการชำระเงิน (ignored by git)
├── index.php               # หน้า Dashboard หลัก
├── timetable.php           # ตารางคอร์ต 24 ชม.
├── timetable_detail.php    # รายละเอียดตารางรายวัน
├── Dockerfile
├── docker-compose.yml
├── composer.json
└── .htaccess               # PHP upload limits (10MB)
```

---

## Getting Started

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (รองรับ Windows / macOS / Linux)

### Installation

**1. Clone repository**
```bash
git clone https://github.com/B0atByte/Badcourd.git
cd Badcourd
```

**2. Start containers**
```bash
docker-compose up -d
```

รอประมาณ 30–60 วินาทีให้ MySQL พร้อมใช้งาน

**3. Import ฐานข้อมูล**

Import ไฟล์ SQL ตามลำดับ:
```bash
# วิธีที่ 1: ผ่าน Terminal
docker exec -i mysql-db mysql -u root -prootpassword badcourt < SQL/badcourt.sql
docker exec -i mysql-db mysql -u root -prootpassword badcourt < SQL/members_system.sql
docker exec -i mysql-db mysql -u root -prootpassword badcourt < SQL/promotions_system.sql
docker exec -i mysql-db mysql -u root -prootpassword badcourt < SQL/slip_migration.sql
```

หรือ

```
# วิธีที่ 2: ผ่าน phpMyAdmin
1. เปิด http://localhost:8081
2. Login: root / rootpassword
3. เลือก database: badcourt
4. Import ไฟล์ใน SQL/ ตามลำดับ
```

**4. ติดตั้ง PHP Dependencies**
```bash
docker exec php-app composer install
```

**5. เข้าใช้งาน**

| Service | URL |
|---|---|
| Web Application | http://localhost:8085 |
| phpMyAdmin | http://localhost:8081 |

---

## Default Accounts

| Role | Username | Password | สิทธิ์ |
|---|---|---|---|
| Admin | `admin` | `admin` | เข้าถึงได้ทุกหน้า รวม /admin/ |
| Staff | `user` | `user` | จอง, ดูรายการ, ค้นหาสมาชิก |

> แนะนำให้เปลี่ยน password หลัง deploy จริง

---

## Database Schema

| Table | คำอธิบาย |
|---|---|
| `users` | บัญชีผู้ใช้งานระบบ (role: admin / user) |
| `courts` | คอร์ตแบดมินตัน (ปกติ / VIP) พร้อมราคาและสถานะ |
| `bookings` | รายการจอง พร้อม payment_slip_path |
| `booking_logs` | Log ประวัติการแก้ไข/ยกเลิกการจอง |
| `pricing_rules` | กฎราคาตามวัน (weekday/weekend) และช่วงเวลา |
| `members` | ข้อมูลสมาชิก (ระดับ Bronze–Platinum, คะแนน) |
| `point_transactions` | ประวัติการรับ/ใช้คะแนนสะสม |
| `promotions` | โปรโมชั่นส่วนลด (code, %, วันเริ่ม-สิ้นสุด) |

### Member Level Thresholds

| ระดับ | ยอดใช้จ่ายสะสม | ส่วนลด |
|---|---|---|
| Bronze | < ฿5,000 | 0% |
| Silver | ฿5,000+ | 5% |
| Gold | ฿10,000+ | 10% |
| Platinum | ฿20,000+ | 15% |

---

## Development

### PHP Syntax Check
```bash
docker exec php-app sh -c "php -l /var/www/html/path/to/file.php"
```

### View PHP Logs
```bash
docker logs php-app
```

### Connect to MySQL
```bash
docker exec -it mysql-db mysql -u root -prootpassword badcourt
```

---

## License

© 2025 Boat Patthanapong — BARGAIN SPORT
