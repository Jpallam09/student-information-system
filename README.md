# 📚 Student Records Information System (SRIS) 
🎓 BS Information Technology Capstone Project

**Current Working Directory:** `c:/xampp/htdocs/Student_Info/`

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-8892BF?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-10.4%2B-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![XAMPP](https://img.shields.io/badge/XAMPP-8.2%2B-orange?style=flat&logo=apachefriends&logoColor=white)](https://www.apachefriends.org/)
[![JavaScript](https://img.shields.io/badge/JavaScript-ES6%2B-yellow?style=flat&logo=javascript&logoColor=white)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)

## 📖 Table of Contents
- [Project Overview](#-project-overview)
- [🎯 Objectives](#-objectives)
- [🚀 Key Features](#-key-features)
- [🏗️ System Architecture](#️-system-architecture)
- [🛠️ Technologies Used](#️-technologies-used)
- [⚡ Quick Start](#-quick-start)
- [📸 Main Interface View](#-Main-Interface-View)
- [📁 Project Structure](#-project-structure)
- [🤝 Contributing](#-contributing)

## 📖 Project Overview

The **Student Records Information System (SRIS)** is a web-based platform developed to modernize and streamline the management of student information within educational institutions.

Traditional record-keeping methods often rely on manual processes, leading to inefficiencies, data inconsistencies, and limited accessibility. This system addresses these challenges by providing a centralized, secure, and user-friendly digital solution.

The application enables administrators, teachers, and students to efficiently manage and access academic records, class schedules, and personal information in real time.

## 🎯 Objectives

### General Objective
To develop a web-based system that improves the efficiency, accuracy, and accessibility of managing student records.

### Specific Objectives
- To digitize student and teacher information management
- To provide role-based access for secure data handling
- To enable real-time viewing and updating of academic records
- To reduce administrative workload and manual errors
- To implement a reliable and secure login system

## 🚀 Key Features

| Feature | Students | Teachers | Admin |
|---------|----------|----------|-------|
| **Dashboard** | ✅ View grades, tasks, schedule | ✅ View students, tasks, announcements | ✅ Full system access |
| **Authentication** | ✅ Login/Register | ✅ Login/Register | ✅ Seeded admin |
| **Profile Management** | ✅ View/Edit profile & pic | ✅ View assigned classes | - |
| **Grades** | ✅ View grades | ✅ Manage grades | - |
| **Attendance** | ✅ View attendance | ✅ Mark attendance | - |
| **Class Schedule** | ✅ View schedule | ✅ View/manage subjects | - |
| **Tasks** | ✅ Submit tasks/files/notes | ✅ Create/view submissions/unread | ✅ |
| **Announcements** | ✅ View | ✅ Create/Post | ✅ |
| **Subjects & Students** | - | ✅ Filter by year/section | - |
| **School Year Mgmt** | Auto-detect active | Filtered views | ✅ Manage active year |
| **Evaluations** | ✅ Evaluate teachers | ✅ View evaluations | - |
| **Real-time** | ✅ SSE updates | ✅ SSE tasks | - |

**Advanced:**
- File uploads (tasks/profiles) with secure paths
- Prepared statements & password hashing
- Active school year/semester filtering
- Dynamic teacher year-level/section assignment

## 🏗️ System Architecture

The system follows a **3-Tier Architecture**:

```
Presentation Layer    Application Layer    Data Layer
     (UI)                 (PHP)            (MySQL)
HTML/CSS/JS ──────────► Business Logic ───► Database
```

- **Presentation Layer**: User Interface (HTML, CSS, JavaScript)
- **Application Layer**: Business Logic (PHP)
- **Data Layer**: Database Management (MySQL)

## 🛠️ Technologies Used

| Category | Technology | Details |
|----------|------------|---------|
| **Backend** | PHP 8.2+ | MVC-like, mysqli prepared stmts |
| **Frontend** | HTML5/CSS3/JS ES6+ | Responsive, SSE real-time |
| **Database** | MySQL 10.4+ (MariaDB) | UTF8mb4, foreign keys |
| **Server** | XAMPP Apache | Local dev server |
| **Security** | PHP `password_hash/verify` | Session mgmt, SQL injection prevention |
| **Storage** | File system | Profile pics/task uploads |
| **Tools** | VSCode, phpMyAdmin | Development & DB mgmt

## ⚡ Quick Start

### Prerequisites
- [XAMPP 8.2+](https://www.apachefriends.org/) (Apache + MySQL/MariaDB)

### Setup (5 mins)
```
1. Start XAMPP → Apache & MySQL
2. Open phpMyAdmin: http://localhost/phpmyadmin
3. Create DB `studentinfo` → Import `studentinfo.sql`
4. Access: http://localhost/Student_Info/
```

### Sample Login Credentials (from DB)
**Students:**
| ID | Name | Password (default) |
|----|------|--------------------|
| 26-1111 | Maccoy Tabios | `maccoy123` |
| 26-2222 | Juliana Pariscova | `juliana123` |
| 26-4444 | Pedro Penduko | `pedro123` |

**Teachers:**
| ID | Name | Course | Password |
|----|------|--------|----------|
| 321 | Maccoy Malittay | BSIT | `maccoy321` |
| 123 | Mark Tabios | BSIT | `mark123` |

**Admin:** Run `http://localhost/Student_Info/admin/seed_admin_teacher.php`

### URLs
- **Student Login:** `Accesspage/student_login.php`
- **Teacher Login:** `Accesspage/teacher_login.php`
- **Dashboard:** Auto-redirect after login

## 📸 Main Interface View

<p align="center">
  <img src="images/Screenshot 2026-03-22 211936.png" width="100%" alt="SRIS Dashboard">
</p>

<p align="center"><i>Main dashboard interface of the Student Records Information System</i></p>

## 📁 Project Structure

```
Student_Info/                    # Root (htdocs/Student_Info)
├── index.php                   # Landing
├── README.md                   # This doc
├── studentinfo.sql             # Full DB schema + sample data
├── .htaccess                   # Security
├── config/
│   ├── database.php            # DB connection
│   ├── paths.php              # BASE_URL, PROJECT_ROOT
│   ├── current_school_year.php # Active year/semester
│   └── teacher_filter.php      # Teacher views
├── Accesspage/                 # Auth
│   └── *.php (login/register)
├── studentsportal/             # Student portal
│   ├── students_*.php         # Dashboard, grades, tasks, etc.
│   └── components/            # Sidebar, warnings
├── teachersportal/             # Teacher portal
│   └── *.php                  # Students, subjects, grades, tasks
├── tasks/                      # Task system
│   ├── *.php (CRUD APIs)      # Submit/get/mark read
│   ├── uploads/               # Teacher files
│   └── student_uploads/       # Student submissions
├── admin/                      # Admin tools
│   └── manage_school_year.php # Active year mgmt
├── css/                        # Styles (student.css etc.)
├── images/ & profile_pics/    # Assets/uploads
└── ...                        # Fee structure, logs
```

**Key Config:** Edit `config/database.php` for prod DB creds.

## 🤝 Contributing

1. Fork the repo
2. Create branch: `git checkout -b feature/AmazingFeature`
3. Commit: `git commit -m 'Add some AmazingFeature'`
4. Push: `git push origin feature/AmazingFeature`
5. Open Pull Request
---

*Built with ❤️ for educational institutions.*

