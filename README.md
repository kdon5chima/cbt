🎓 School Quiz & Academic Management System
A robust, PHP-based web application designed to streamline school assessments, automate grading, and generate comprehensive academic broadsheets. This system supports three distinct user roles: Administrators, Teachers, and Participants (Pupils).

🚀 Key Features
1. Unified Academic Broadsheet
Dynamic Pivot Table: Automatically transforms quiz attempts into a spreadsheet-style broadsheet with subjects as columns and pupils as rows.

Automated Ranking: Calculates class averages and assigns ordinal ranks (1st, 2nd, 3rd, etc.) based on performance.

Color-Coded Grading: Visual indicators for grades (A, B, C, D, F) based on customizable percentage scales.

Multi-Format Export: One-click downloads for Excel (.xls), Word (.doc), and Print-ready PDF.

2. Multi-Role Management
Admin Dashboard: Full control over the system, including the ability to manage the Teacher CRUD (Create, Read, Update, Delete) interface.

Teacher Portal: (In Progress) Dedicated space for educators to manage quizzes and view real-time class performance.

Participant Area: A clean interface for pupils to take assigned quizzes and view their personal results.

3. Smart Filtering
Class-Specific Views: Filter reports by class_year to ensure data privacy and organizational clarity.

Session Archiving: Filter results by academic year to track historical performance.

🛠️ Tech Stack
Backend: PHP 8.x

Database: MySQL (MariaDB)

Frontend: Bootstrap 5.3, FontAwesome 6

Server Environment: XAMPP / WAMP / Linux Apache

📂 Database Schema Overview
The project relies on a relational database structure:

users: Stores credentials and roles (admin, teacher, participant) along with class_year.

quizzes: Stores subject metadata and total possible scores.

attempts: Records student submissions, scores, and timestamps.

📝 Installation
Clone the repository to your htdocs folder.

Import the provided SQL schema via phpMyAdmin.

Configure db_connect.php with your local credentials.

Run the ALTER TABLE commands to enable the teacher role.
## 📩 Contact & Support
If you have any questions, feedback, or need help setting up the system:
* **GitHub Issues:** [Open an issue here](https://github.com/kdon5chima/cbt/issues)
* **Email:** kdonchima@gmail.com
* **LinkedIn:**www.linkedin.com/in/don-chima-345251a7
