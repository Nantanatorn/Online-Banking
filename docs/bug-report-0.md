# Bug Report #0 — Online-Banking

วันที่: 2026-11-09
ขอบเขต: ตรวจทั้งโปรเจค (backend Laravel + JWT, migration, route, frontend Vue/Inertia, test)
ผลรวม: พบ 20 ข้อ แก้แล้ว 19 ข้อ, แก้บางส่วน 1 ข้อ (ข้อ 12)

## สรุปการทดสอบ

| รายการ | ผล |
|---|---|
| `php artisan test` บน Postgres (`online_banking_test`) | 39 passed (131 assertions) |
| ยิงพร้อมกันหลาย process: ถอน 100 x8 จากยอด 100 | สำเร็จ 1 ครั้ง ยอดเหลือ 0.00 |
| ยิงพร้อมกัน: ฝาก 10 x8 | ยอดรวม 80.00 ไม่มียอดหาย |
| ยิงพร้อมกัน: โอนสวนทาง A→B / B→A อย่างละ 8 ครั้ง | สำเร็จครบ ไม่ deadlock ผลรวมคงเดิม |
| `npm run build` | ผ่าน |

ยังไม่ได้ทำ: เปิดเบราว์เซอร์ลองกดหน้าเว็บจริง, ไม่ได้รันสคริปต์ยิงพร้อมกันกับโค้ดเวอร์ชันเก่าเพื่อเทียบ

## ร้ายแรง

| # | ปัญหา | การแก้ | ไฟล์ |
|---|---|---|---|
| 1 | ฝาก/ถอนไม่มี lock เช็กยอดนอก transaction ถอนพร้อมกันแล้วยอดติดลบหรือยอดฝากหายได้ | ครอบด้วย `DB::transaction` + `lockForUpdate()` เช็กยอดหลัง lock คำนวณด้วย bcmath | `app/Http/Controllers/TransactionController.php` |
| 2 | โอนสวนทางกันพร้อมกันอาจ deadlock (lock ต้นทางแล้วค่อยปลายทาง) | lock สองบัญชีในคำสั่งเดียว เรียงตาม `account_id` | `TransactionController.php` |
| 3 | route API ถูกโหลดซ้ำ (`web.php` require `api.php` + `withRouting(api:)`) ทำให้มี endpoint ซ้ำที่ `/login`, `/deposit` ฯลฯ ไม่มี prefix | ลบ `require` ออกจาก `web.php` | `routes/web.php` |

## ปานกลาง

| # | ปัญหา | การแก้ | ไฟล์ |
|---|---|---|---|
| 4 | ยอดรวมในกราฟเพี้ยน: Postgres คืน decimal เป็น string แล้ว `reduce` ต่อ string | เพิ่ม `$casts` `decimal:2` ฝั่ง model, ฝั่ง Vue ใช้ `Number()` | `app/Models/BankAccount.php`, `Transaction.php`, `pages/Transaction.vue`, `pages/dashboard.vue` |
| 5 | ประเภท `receive` ไม่แสดง ช่อง Type ว่าง และไม่นับในกราฟ | เพิ่ม label Receive และชิ้นส่วนกราฟ "Received" | `Transaction.vue`, `dashboard.vue` |
| 6 | สี amount ผิด (เช็ก `amount > 0` แต่ amount เป็นบวกเสมอ) ถอน/โอนออกเป็นสีเขียว | เช็กจากประเภทรายการ เงินเข้า `+` เขียว เงินออก `-` แดง | `Transaction.vue`, `dashboard.vue` |
| 7 | `$user->id` เป็น null (ตารางใช้ `userid` เป็น PK) | response ส่ง `userid` แทน | `app/Http/Controllers/AuthController.php` |
| 8 | Logout ค้างเมื่อ token หมดอายุ (catch แล้วไม่ลบ token ไม่ redirect) | ใช้ `finally` ล้าง token แล้ว redirect เสมอ | `components/Accsidebar.vue` |
| 9 | ฝาก/ถอน/โอนแล้วหน้าไม่รีเฟรชยอดและประวัติ | `Tranbutton` ส่ง event `done` ให้หน้าโหลดข้อมูลใหม่ | `components/Tranbutton.vue`, หน้า Vue ทั้งสอง |
| 10 | Interest แสดงเป็นบาท (`฿`) ทั้งที่เป็นเปอร์เซ็นต์ | แสดงเป็น `%` | `pages/dashboard.vue` |
| 11 | หน้า `/dashboard`, `/Tran` ไม่เช็กการล็อกอิน | เพิ่ม helper `requireLogin` / `handleAuthError` เด้งไป `/Ulogin` เมื่อไม่มี token หรือได้ 401 | `composables/useAuth.js` (ใหม่) + หน้า Vue |

## ความปลอดภัย / config

| # | ปัญหา | การแก้ | ไฟล์ |
|---|---|---|---|
| 12 | server ไม่บังคับ PIN/idcard/phone เป็นตัวเลข (เช็กแค่ฝั่งฟอร์ม) | บังคับ `digits:13`, `digits_between:9,15`, PIN `regex:/^\d{4,6}$/` ทั้ง register และ login | `AuthController.php` |
| 13 | register คืนข้อความ exception ดิบ (เผย schema/SQL) | คืนข้อความทั่วไป เขียนรายละเอียดลง log | `AuthController.php` |
| 14 | log request ทั้งก้อนตอนโอน และ log userid ตอน login | ลบ log เหล่านั้น | `TransactionController.php`, `AuthController.php` |
| 15 | `config/auth.php` มี key `guard` ซ้ำ ทำให้ `AUTH_GUARD` ไม่มีผล | เหลือ `env('AUTH_GUARD', 'api')` | `config/auth.php` |
| 16 | `VerifyCsrfToken` ไม่ได้ใช้ (โค้ดตาย) | ลบไฟล์ | `app/Http/Middleware/VerifyCsrfToken.php` |

## โครงสร้าง / test

| # | ปัญหา | การแก้ | ไฟล์ |
|---|---|---|---|
| 17 | test ของ starter kit ไม่ตรงกับระบบ (Auth, Settings, Dashboard) | ลบทิ้ง เหลือ `BankingApiTest` + `ExampleTest` | `tests/Feature/*` |
| 18 | `DatabaseSeeder` พัง (ไม่มี factory, ใช้คอลัมน์ `name`) | สร้างผู้ใช้ทดสอบ + บัญชีด้วย model โดยตรง | `database/seeders/DatabaseSeeder.php` |
| 19 | migration `receive` ใช้ได้เฉพาะ Postgres และ `down()` ลบข้อมูล | เพิ่มทาง SQLite/MySQL ผ่าน `->change()`, `down()` แปลง receive เป็น transfer | `database/migrations/2026_11_09_000001_...` |
| 20 | `BankAccount::boot` ทับ `account_id` ทุกครั้ง และ register สุ่มซ้ำ | สุ่มเฉพาะเมื่อยังไม่มี ตัดการสุ่มซ้ำใน register | `app/Models/BankAccount.php`, `AuthController.php` |

## สิ่งที่เปลี่ยนแล้วกระทบ API / ข้อควรรู้

- **รูปแบบตัวเลขเงินเปลี่ยน:** `balance`, `amount`, `interest` ตอนนี้ออกเป็นสตริงทศนิยม 2 ตำแหน่ง (`"500.00"`) ไม่ใช่ตัวเลข ถ้ามี client อื่นนอกจากหน้าเว็บนี้ต้องแปลงเอง เทสสองจุดใน `BankingApiTest` ถูกปรับให้เทียบเป็นตัวเลขตามนี้
- **response ของ login และ `/api/user`:** ฟิลด์ `id` (เป็น null เสมอ) ถูกแทนด้วย `userid`
- **ต้องมี PHP extension `bcmath`:** เพิ่ม `ext-bcmath` ใน `composer.json` แล้ว
- **error ของ 422 บน register/login:** ข้อความตรวจรูปแบบใหม่ (ตัวเลขล้วน) จะตอบ 422 ในกรณีที่เดิมผ่าน

## ที่ยังไม่ได้แก้ / ความเสี่ยงที่เหลือ

1. **Rate limit login ยังเลี่ยงได้ด้วยการหมุน IP** — ตัว limiter นับต่อ `userid|ip` PIN มีแค่ 10^4–10^6 ค่า ถ้าต้องการป้องกันจริงควรเพิ่มการล็อกบัญชีหลังพลาดหลายครั้งหรือกันที่ระดับ infra (ไม่ได้แก้ในรอบนี้)
2. **`.env.example` ยังตั้ง `DB_CONNECTION=sqlite`** ทั้งที่ระบบจริงใช้ Postgres
3. **โค้ด starter kit ที่เหลือ** (`app/Http/Requests/Auth|Settings`, `resources/js/layouts/auth|settings`, component UI บางส่วน) ยังไม่ได้ตรวจว่ามีอะไรอ้างถึงอยู่หรือไม่ จึงยังไม่ได้ลบ
4. ทุกการเปลี่ยนแปลงยังไม่ได้ commit
