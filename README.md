# Mini Campus Event Hub

Mini Campus Event Hub is a web-based campus event management system developed using PHP 8+, MySQL or MariaDB, HTML, CSS, and JavaScript. It allows students to explore campus events, register for activities, and manage their event registrations. Administrators can create and manage events and monitor student registrations.

## Features

- Student account registration, login, and logout.
- Secure password hashing and session management.
- Event listings with keyword search and category filters.
- Detailed event information, including date, time, venue, organizer, and available slots.
- Student event registration with duplicate-registration prevention.
- Event capacity validation to prevent overbooking.
- Personal registration history and cancellation.
- Administrator dashboard for managing campus events.
- Create, view, edit, archive, and restore events.
- Attendee lists and registration status monitoring.
- Event capacity tracking and event history.
- PDO prepared statements and server-side input validation.
- HTML escaping and CSRF protection.
- Role-based access control for students and administrators.
- Responsive green-themed design for desktop and mobile devices.

## Technologies Used

- **Backend:** PHP 8+
- **Database:** MySQL or MariaDB
- **Frontend:** HTML5, CSS3, JavaScript
- **UI Framework:** Bootstrap 5
- **Local Development Environment:** XAMPP
- **Database Management:** phpMyAdmin

## System Requirements

Before running the application, make sure you have:

- Windows, Linux, or another supported operating system.
- XAMPP or another PHP development environment.
- PHP 8 or later.
- MySQL or MariaDB.
- A modern web browser.
- Visual Studio Code or another code editor.

## Installation and Setup

### Step 1: Install XAMPP

Download and install XAMPP if it is not yet installed.

Start the following services in the XAMPP Control Panel:

- Apache
- MySQL

### Step 2: Place the Project Folder

Copy or extract the project into the XAMPP `htdocs` directory.

Expected project location:

```text
C:\xampp\htdocs\campus-event-hub
```

### Step 3: Create the Database

Open phpMyAdmin in your browser:

http://localhost/phpmyadmin/

Import the SQL file located at:

```text
database/campus_event_hub.sql
```

Follow the import instructions in phpMyAdmin. Verify that the SQL file creates the `campus_event_hub` database and contains the required tables and sample data.

**Important:** Back up any existing database before importing SQL or changing its structure. Importing a schema does not automatically update existing tables when `CREATE TABLE IF NOT EXISTS` is used.

### Step 4: Configure the Database Connection

Open the following file in Visual Studio Code:

```text
config/database.php
```

Check the database connection settings. The default local XAMPP configuration may use:

- Host: `localhost`
- Database: `campus_event_hub`
- Username: `root`
- Password: Empty

Use the actual credentials configured on your computer if they are different.

### Step 5: Run the Application

Open your browser and visit:

http://localhost/campus-event-hub/

The homepage should display the available campus events.

### Step 6: Register a Student Account

1. Open the application.
2. Click **Create Account** or **Register**.
3. Enter the required information.
4. Submit the registration form.
5. Log in using your registered account.

### Step 7: Create an Administrator Account

Register a normal account first. Then open phpMyAdmin and select the `campus_event_hub` database.

Run the following query, replacing `YOUR_REGISTERED_USERNAME` with the actual username of the account you want to promote:

```sql
UPDATE users
SET role = 'admin'
WHERE username = 'YOUR_REGISTERED_USERNAME';
```

Log out and log in again to refresh the account session.

**Security reminder:** Public registration must not allow users to choose the administrator role. Grant administrator access only to authorized personnel.

## Main Project Structure

```text
campus-event-hub/
│
├── admin/
│   ├── dashboard.php
│   ├── create-event.php
│   ├── edit-event.php
│   ├── registrations.php
│   └── restore-event.php
│
├── assets/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── script.js
│   └── images/
│
├── config/
│   └── database.php
│
├── database/
│   └── campus_event_hub.sql
│
├── includes/
│   ├── auth.php
│   ├── header.php
│   └── footer.php
│
├── index.php
├── events.php
├── event-details.php
├── login.php
├── register.php
├── logout.php
├── register-event.php
├── my-registrations.php
├── cancel-registration.php
└── README.md
```

*Note: This is an example structure. Your actual project may contain additional files or use different filenames.*

## User Roles

### Student

Students can:

- Create an account and log in.
- Browse and search campus events.
- View event details.
- Register for available events.
- Check registration history.
- Cancel eligible registrations.

### Administrator

Administrators can:

- Access the administrator dashboard.
- Create and update events.
- Archive and restore events.
- View event registrations.
- Monitor event capacity and attendee information.
- Review event history, if implemented.

## Security Features

The application is designed to use the following security practices:

- **Password Hashing:** Passwords should be stored using PHP password-hashing functions.
- **Prepared Statements:** PDO prepared statements help prevent SQL injection.
- **Input Validation:** User input is validated on the server.
- **Output Escaping:** Dynamic HTML output is escaped to help prevent cross-site scripting (XSS).
- **CSRF Protection:** Forms that change data should validate CSRF tokens.
- **Role-Based Access Control:** Student and administrator permissions are checked on the server.
- **Session Management:** Sessions should be handled securely during login and logout.

These protections must be implemented correctly in the PHP files; listing them here does not automatically enable them.

## Testing Checklist

Before submitting or deploying the application, test the following:

1. The homepage displays upcoming events.
2. Students can register for an account.
3. Valid login credentials allow access to the application.
4. Invalid login credentials display an appropriate error message.
5. Students can browse and search events.
6. Event details display the correct information.
7. Students can register for available events.
8. Duplicate registrations are prevented.
9. Registration is rejected when an event is full.
10. Registration is rejected when an event is closed or unavailable.
11. Students can view their registration history.
12. Students can cancel eligible registrations.
13. Administrators can create and edit events.
14. Administrators can view event attendees.
15. Students cannot directly access administrator-only pages.
16. Database records remain saved after refreshing the browser.
17. The application works on desktop and mobile devices.

## Troubleshooting

### Database Connection Error

- Make sure MySQL is running in XAMPP.
- Check the settings in `config/database.php`.
- Verify that the `campus_event_hub` database exists.
- Confirm that the database username and password are correct.

### Page Not Found

- Make sure the project folder is inside `C:\xampp\htdocs\`.
- Verify the folder name is `campus-event-hub`.
- Check the requested PHP filename.
- Confirm that Apache is running.

### Unknown Column or Missing Table

- Check whether the required table or column exists in phpMyAdmin.
- Compare the database structure with the SQL queries used in the PHP files.
- Back up the database before applying schema changes.

### Login or Registration Problems

- Check the PHP error logs.
- Verify that the database connection works.
- Confirm that the required user columns exist.
- Make sure passwords are hashed during registration and verified during login.

## Before Deployment

Before hosting the application online:

- Configure database credentials using environment variables or protected server configuration.
- Enable HTTPS.
- Disable public error display and configure secure error logging.
- Back up the database regularly.
- Test authentication, registration, cancellation, and administrator permissions.
- Never publish database credentials or real user passwords on GitHub.
- Update `APP_BASE_URL` in `includes/auth.php` if the application is hosted at a different path.
- Use a hosting provider that supports PHP and MySQL or MariaDB.

**Note:** GitHub Pages does not execute PHP. A PHP-compatible hosting service is required to run this application.

## Project Purpose

Mini Campus Event Hub aims to provide a convenient way for students to discover campus activities and manage their event registrations. It also helps administrators organize events, monitor participation, and manage event information in one centralized system.

## License

This project is intended for educational and academic purposes. Add a specific open-source license if you plan to distribute or publish the source code publicly.
