# 🎓 Timetable Management System

A comprehensive web-based timetable management system built with PHP, MySQL, and modern web technologies. This system provides role-based access control for administrators, faculty, and students to manage academic schedules efficiently.

## 🌟 Features

### 👨‍💼 Admin Features
- **User Management**: Create, edit, and manage users (students, faculty, admin)
- **Department Management**: Organize academic departments
- **Subject Management**: Manage courses and subjects
- **Classroom Management**: Allocate and manage classroom resources
- **Time Slot Management**: Configure class timing schedules
- **Timetable Generation**: Create and manage academic timetables
- **System Settings**: Configure system-wide settings and preferences
- **Backup Management**: Create and manage database backups
- **Reports & Analytics**: Generate comprehensive system reports
- **Notification System**: Send system-wide notifications

### 👨‍🏫 Faculty Features
- **Personal Dashboard**: View teaching schedule and statistics
- **My Schedule**: View personal timetable
- **My Subjects**: Manage assigned subjects
- **Student Management**: View enrolled students
- **Export Functionality**: Export schedules in PDF/Excel formats
- **Profile Management**: Update personal information
- **Notifications**: Receive and manage notifications

### 👨‍🎓 Student Features
- **Personal Dashboard**: View academic schedule and enrollments
- **My Timetable**: View personal class schedule
- **Course Enrollments**: View enrolled courses
- **Export Schedule**: Export personal timetable
- **Profile Management**: Update personal information
- **Notifications**: Receive academic notifications

## 🛠️ Technical Stack

- **Backend**: PHP 8.0+
- **Database**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **CSS Framework**: Bootstrap 5.3
- **Icons**: Font Awesome 6
- **PDF Generation**: TCPDF
- **Excel Export**: PhpSpreadsheet
- **Email**: PHPMailer with SMTP
- **Authentication**: Session-based with remember me functionality
- **Architecture**: MVC Pattern with Object-Oriented PHP

## 📋 Requirements

- **PHP**: 8.0 or higher
- **MySQL**: 8.0 or higher
- **Web Server**: Apache/Nginx
- **Extensions**: PDO, MySQLi, GD, Zip, CURL
- **Composer**: For dependency management

## 🚀 Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/PaulOdartey/Timetable-Management-System.git
   cd Timetable-Management-System
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Database Setup**
   - Create a MySQL database
   - Import the SQL file: `database/timetable_management_system.sql`

4. **Configuration**
   - Copy `config/config.example.php` to `config/config.php`
   - Update database credentials and other settings
   - Configure email settings for notifications

5. **Set Permissions**
   ```bash
   chmod 755 uploads/ exports/ cache/ logs/
   ```

6. **Access the System**
   - Navigate to your web server URL
   - Default admin credentials will be provided in documentation

## 📁 Project Structure

```
timetable-management/
├── admin/                  # Admin interface
├── faculty/               # Faculty interface  
├── student/               # Student interface
├── auth/                  # Authentication pages
├── public/                # Public pages
├── classes/               # Core PHP classes
├── includes/              # Shared components & APIs
├── config/                # Configuration files
├── database/              # SQL schema & documentation
├── uploads/               # User uploads
├── exports/               # Generated exports
├── cache/                 # System cache
├── logs/                  # Application logs
├── vendor/                # Composer dependencies
├── composer.json          # Composer configuration
└── index.php             # Main entry point
```

## 🔐 Security Features

- **Role-based Access Control**: Admin, Faculty, Student roles
- **Session Management**: Secure session handling
- **CSRF Protection**: Cross-site request forgery protection
- **SQL Injection Prevention**: Prepared statements
- **XSS Protection**: Input sanitization and validation
- **Password Security**: Bcrypt hashing
- **Remember Me**: Secure token-based authentication
- **Email Verification**: Account verification system

## 📊 Export & Reporting

- **PDF Export**: Professional PDF generation with TCPDF
- **Excel Export**: Comprehensive Excel reports with PhpSpreadsheet
- **CSV Export**: Data export in CSV format
- **Automatic Cleanup**: Old export files auto-deletion
- **Secure Downloads**: Protected file serving

## 📧 Email System

- **PHPMailer Integration**: Professional email sending
- **SMTP Support**: Gmail, Outlook, custom SMTP
- **Email Templates**: Professional HTML templates
- **Verification Emails**: Account verification system
- **Password Reset**: Secure password reset via email
- **Notifications**: System and academic notifications

## 🎨 UI/UX Features

- **Responsive Design**: Mobile-first approach with Bootstrap
- **Dark/Light Mode**: Theme switching capability
- **Glass Morphism**: Modern UI design with glass effects
- **Real-time Updates**: Dynamic content updates
- **Professional Animations**: Smooth transitions and effects
- **Cross-browser Support**: Works on all modern browsers

## 🔄 Backup System

- **Manual Backups**: Admin-initiated database backups
- **Automatic Cleanup**: Old backup file management
- **Backup History**: Track all backup operations
- **Secure Storage**: Protected backup file storage

## 📱 Mobile Support

- **Responsive Design**: Optimized for all screen sizes
- **Touch-friendly**: Mobile-optimized interactions
- **Mobile Navigation**: Collapsible sidebar and navigation

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Author

**Paul Odartey**
- GitHub: [@PaulOdartey](https://github.com/PaulOdartey)

## 🙏 Acknowledgments

- Bootstrap team for the excellent CSS framework
- Font Awesome for the comprehensive icon library
- TCPDF and PhpSpreadsheet for export functionality
- PHPMailer for email capabilities

## 📞 Support

For support, email your-email@example.com or create an issue in this repository.

---

⭐ **Star this repository if you find it helpful!**
