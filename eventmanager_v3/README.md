# CampusConnect - Simple PHP Page Structure

This version is reorganized in the style of the supplied `student_mini_website` example:
- Each page contains its own PHP processing and SQL queries at the top, followed by its HTML interface.
- `config.php` contains only the shared PDO database connection, session setup, escaping, and access/CSRF helpers.
- No API controller, repository classes, or JavaScript fetch calls are required.
- `assets/style.css` contains the shared CampusConnect styling.

## Setup with XAMPP
1. Extract the `eventmanager` folder into `C:\xampp\htdocs\`.
2. Start Apache and MySQL in XAMPP.
3. Open `http://localhost/phpmyadmin`.
4. Import `eventmanager/database/database.sql`.
5. Check the MySQL username and password in `config.php` (default: `root` with a blank password).
6. Visit `http://localhost/eventmanager/`.
7. Create an account using `register.php`, then sign in.

## Pages
- `login.php` - verifies the account using `password_verify()`.
- `register.php` - creates student/staff accounts only, using `password_hash()`, and collects year level and block/section. It cannot create administrator accounts.
- `dashboard.php` - shows real event and registration counts from MySQL; shows an empty state if there are no upcoming events.
- `events.php` - administrator event CRUD; student/staff event browsing and registration.
- `registrations.php` - administrator attendance management, participants grouped by year and block, unique per-registration QR generation, camera scanning and automatic Present check-in; students/staff can view their QR codes.
- `logout.php` - destroys the session.

## QR attendance setup
For a fresh database, the `qr_token` column is already included in `database/database.sql`. If you already have a database, run `database/add_qr_attendance.sql` once in phpMyAdmin.

The admin generates a unique QR token from the Registrations page, previews it, and can download the QR image to send to the student. The student can also see the same QR on their own Registrations page. At the event, the admin opens the same page and starts the camera scanner. Camera access works on `localhost` or an HTTPS website; the browser will ask for camera permission. The QR scanner and QR image libraries are loaded from CDNs, so the browser needs an internet connection. The QR contains a random token only, not personal information.

## Existing database migration
If you already imported the old database schema, do not import the create script over your existing data. Instead, run `database/migrate_existing.sql` in phpMyAdmin to change the attendance values from the old `attended` label to `present`. Existing rows whose attendance was `attended` may need to be mapped first:

```sql
UPDATE registrations SET attendance = 'present' WHERE attendance = 'attended';
ALTER TABLE registrations MODIFY attendance ENUM('registered','present','absent') NOT NULL DEFAULT 'registered';
```

The migration file is a short reference; run the two statements above in that order if the old `attended` value is already present.

## Important
For a real deployment, administrator account creation should be restricted to authorized users. The administrator option is left available here for classroom demonstration and testing.


## Year and block fields / administrator account
For an existing database, run `database/add_year_block.sql` once in phpMyAdmin. For a fresh database, the `year_level` and `block` fields are already included in `database/database.sql`.

The public registration form creates only student/staff accounts. It asks for year and block/section; staff can choose `Staff` for year and enter `N/A` for block. The admin Registrations page groups participants first by year and then by block.

Administrator accounts are created directly in MySQL. Run `database/create_admin_account.sql` in phpMyAdmin. Demo credentials are `admin@campusconnect.local` / `Admin@12345`. Change the demo password before deployment. Do not expose the admin SQL file or default credentials publicly.
