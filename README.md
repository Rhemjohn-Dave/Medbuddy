# MedBuddy - Medical Management System

MedBuddy is a comprehensive medical management system designed to streamline healthcare operations, connecting doctors, patients, and staff in an efficient and user-friendly environment.

## Features

### For Patients
- User registration and profile management
- Appointment scheduling with doctors
- View medical records and prescriptions
- Real-time notifications for appointments and updates
- Secure messaging system

### For Doctors
- Profile management with specialization details
- Appointment management and scheduling
- Patient record management
- Prescription creation and management
- Staff management and assignment

### For Staff
- Profile management
- Doctor assignment
- Appointment assistance
- Patient record access
- Administrative support

### For Administrators
- User management (doctors, patients, staff)
- System monitoring and analytics
- Role-based access control
- System configuration
- Activity tracking

## Technical Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- XAMPP/WAMP/MAMP (for local development)
- Modern web browser with JavaScript enabled

## Installation

1. Clone the repository:
```bash
git clone https://github.com/yourusername/Medbuddy.git
```

2. Set up your local environment:
   - Install XAMPP/WAMP/MAMP
   - Start Apache and MySQL services

3. Database Setup:
   - Create a new database named 'medbuddy'
   - Import the `medbuddy.sql` file from the database directory

4. Configuration:
   - Navigate to `config/database.php`
   - Update database credentials if needed:
     ```php
     private $host = 'localhost';
     private $db_name = 'medbuddy';
     private $username = 'your_username';
     private $password = 'your_password';
     ```

5. Access the application:
   - Open your web browser
   - Navigate to `http://localhost/Medbuddy`

## Directory Structure

```
Medbuddy/
├── api/                    # API endpoints
│   ├── admin/             # Admin API endpoints
│   ├── auth/              # Authentication endpoints
│   ├── doctor/            # Doctor API endpoints
│   └── patient/           # Patient API endpoints
├── assets/                # Static assets
│   ├── css/              # Stylesheets
│   ├── js/               # JavaScript files
│   └── img/              # Images
├── config/                # Configuration files
├── database/              # Database files
├── includes/              # PHP includes
├── pages/                 # Page templates
│   ├── admin/            # Admin pages
│   ├── doctor/           # Doctor pages
│   └── patient/          # Patient pages
└── vendor/                # Third-party dependencies
```

## Security Features

- Password hashing using PHP's password_hash()
- Session-based authentication
- Role-based access control
- Input validation and sanitization
- Prepared statements for database queries
- CSRF protection
- XSS prevention

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support, email support@medbuddy.com or create an issue in the repository.

## Acknowledgments

- Bootstrap for the frontend framework
- Chart.js for data visualization
- SweetAlert2 for beautiful alerts
- Material Icons for the icon set 