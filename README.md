# 📚 Student Records Information System (SRIS) 
🎓 Self study Project

Bachelor of Science in Information Technology

[![PHP](https://img.shields.io/badge/PHP-7.0%2B-8892BF?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![XAMPP](https://img.shields.io/badge/XAMPP-8.0%2B-orange?style=flat&logo=apachefriends&logoColor=white)](https://www.apachefriends.org/)

## 📖 Table of Contents
- [Project Overview](#-project-overview)
- [🎯 Objectives](#-objectives)
- [🚀 Key Features](#-key-features)
- [🏗️ System Architecture](#️-system-architecture)
- [🛠️ Technologies Used](#️-technologies-used)
- [⚡ Quick Start](#-quick-start)
- [📸 Screenshots](#-screenshots)
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

<details>
<summary>👨‍🎓 Student Management System</summary>

- Add, update, delete, and view student records
- Store personal details and academic information

</details>

<details>
<summary>👩‍🏫 Teacher Management</summary>

- Manage teacher profiles
- Assign teachers to specific classes and sections

</details>

<details>
<summary>🗂️ Section & Schedule Management</summary>

- Organize students into sections
- Generate and manage class schedules

</details>

<details>
<summary>🔐 Role-Based Access Control</summary>

- **Administrator** – Full access to system features
- **Teachers** – Manage assigned students and schedules
- **Students** – View personal records and schedules

</details>

<details>
<summary>🔍 Search and Filtering System</summary>

- Dynamic search functionality
- Efficient filtering of records

</details>

<details>
<summary>🔑 Secure Authentication System</summary>

- Login/logout functionality
- Session-based user authentication
- Protected system access

</details>

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

| Category       | Technology             |
|----------------|------------------------|
| Frontend       | HTML, CSS, JavaScript |
| Backend        | PHP                    |
| Database       | MySQL                  |
| Development Tool | Visual Studio Code   |
| Server         | XAMPP (Apache & MySQL)|

## ⚡ Quick Start

1. **Install XAMPP** (Apache + MySQL): Download from [apachefriends.org](https://www.apachefriends.org/).
2. **Start Servers**: Open XAMPP Control Panel, start Apache & MySQL.
3. **Deploy Project**:
   - Copy project to `c:/xampp/htdocs/Student Info/`
4. **Import Database**:
   - Open phpMyAdmin (`http://localhost/phpmyadmin`)
   - Import `studentinfo.sql`
5. **Access**:
   - Main: `http://localhost/Student%20Info/`
   - Student Login: `Accesspage/student_login.php`
   - Teacher Login: `Accesspage/teacher_login.php`
6. **Test**: Use seed scripts like `admin/seed_admin_teacher.php`.

## 📸 Screenshots

![SRIS Screenshot](images/Screenshot 2026-03-22 211936.png#width=100%)

*(Displays the main [describe based on filename: likely dashboard or portal UI]. Student/Teacher portals, task management, etc. View in Markdown preview or browser for image display.)*

## 📁 Project Structure

```
Student Info/
├── index.php              # Landing page
├── README.md              # This file
├── studentinfo.sql        # Database schema
├── Accesspage/            # Login/Register
├── admin/                 # Admin panels
├── config/                # Config files (DB, paths)
├── css/                   # Styles
├── js/                    # Scripts
├── studentsportal/        # Student features (grades, schedule, tasks)
├── teachersportal/        # Teacher features
├── task/                  # Task submission system
└── ...                    # Images, uploads
```

## 🤝 Contributing

1. Fork the repo
2. Create branch: `git checkout -b feature/AmazingFeature`
3. Commit: `git commit -m 'Add some AmazingFeature'`
4. Push: `git push origin feature/AmazingFeature`
5. Open Pull Request

**License**: MIT License - see [LICENSE](LICENSE) for details.

---

*Built with ❤️ for educational institutions.*

