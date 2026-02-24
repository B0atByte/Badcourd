# BARGAIN SPORT - ระบบจองคอร์ตแบดมินตัน

ระบบจัดการและจองคอร์ตแบดมินตัน (Badminton Court Booking System) พัฒนาด้วย PHP + MySQL รองรับ Docker

## Features

- **จองคอร์ต** — จองคอร์ตแบดมินตันพร้อมระบุชื่อลูกค้า เบอร์โทร ช่วงเวลา และจำนวนชั่วโมง
- **ตารางคอร์ต 24 ชม.** — แสดงตารางการใช้งานคอร์ตทั้งหมดแบบ real-time
- **ระบบราคาอัตโนมัติ** — คำนวณราคาตามช่วงเวลา (วันธรรมดา/วันหยุด) และประเภทคอร์ต (ปกติ/VIP)
- **จัดการคอร์ต** — เพิ่ม แก้ไข ลบคอร์ต รองรับทั้งคอร์ตปกติและห้อง VIP
- **ระบบสมาชิก** — แยกสิทธิ์ Admin / User
- **ออกรายงาน Excel** — ดาวน์โหลดรายงานการจองในรูปแบบ Excel
- **ยกเลิกการจอง** — ยกเลิกและแก้ไขรายการจองได้

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.2 |
| Database | MySQL 8.0 |
| Frontend | TailwindCSS (CDN), Font Awesome |
| Excel Export | PhpSpreadsheet |
| Containerization | Docker, Docker Compose |

## Project Structure

```
├── admin/              # หน้าจัดการสำหรับ Admin
│   ├── courts.php      #   จัดการคอร์ต
│   ├── pricing.php     #   ตั้งค่าราคา
│   └── users.php       #   จัดการผู้ใช้
├── auth/               # ระบบ Authentication
│   ├── guard.php       #   ตรวจสอบสิทธิ์เข้าใช้งาน
│   ├── login.php       #   หน้าเข้าสู่ระบบ
│   └── logout.php      #   ออกจากระบบ
├── bookings/           # ระบบการจอง
│   ├── create.php      #   สร้างการจองใหม่
│   ├── index.php       #   รายการจองทั้งหมด
│   ├── update.php      #   แก้ไขการจอง
│   ├── cancel.php      #   ยกเลิกการจอง
│   └── get_price_ajax.php  # API คำนวณราคาแบบ AJAX
├── config/             # การตั้งค่า
│   ├── db.php          #   เชื่อมต่อฐานข้อมูล
│   └── base_path.php   #   ตั้งค่า base path
├── includes/           # Components ที่ใช้ร่วมกัน
│   ├── header.php
│   ├── footer.php
│   └── helpers.php
├── reports/            # รายงาน
│   └── export_excel.php
├── SQL/                # ไฟล์ฐานข้อมูล
│   └── badcourt.sql
├── index.php           # หน้าหลัก
├── timetable.php       # ตารางคอร์ต 24 ชม.
├── Dockerfile
├── docker-compose.yml
└── composer.json
```

## Getting Started

### Prerequisites

- [Docker](https://www.docker.com/) & Docker Compose

### Installation

1. **Clone repository**
   ```bash
   git clone https://github.com/B0atByte/Badcourd.git
   cd Badcourd
   ```

2. **รันด้วย Docker Compose**
   ```bash
   docker-compose up -d
   ```

3. **Import ฐานข้อมูล**
   - เปิด phpMyAdmin ที่ `http://localhost:8081`
   - Import ไฟล์ `SQL/badcourt.sql` เข้า database `badcourt`

4. **เข้าใช้งาน**
   - Web App: `http://localhost:8085`
   - phpMyAdmin: `http://localhost:8081`

### Default Accounts

| Role | Username | Password |
|------|----------|----------|
| Admin | `admin` | `admin` |
| User | `user` | `user` |

## Database Schema

| Table | Description |
|-------|-------------|
| `users` | ข้อมูลผู้ใช้งานและสิทธิ์ |
| `courts` | ข้อมูลคอร์ต (ปกติ / VIP) |
| `bookings` | รายการจองคอร์ต |
| `booking_logs` | ประวัติการดำเนินการ |
| `pricing_rules` | กฎการคำนวณราคาตามช่วงเวลา |

## Screenshots

> _เพิ่มภาพหน้าจอได้ที่นี่_

## License

Boat Patthanapong
