# 🩸 HopeDrops - Blood Bank Management System

![HopeDrops Banner](https://img.shields.io/badge/HopeDrops-Blood%20Bank%20Management-dc3545?style=for-the-badge&logo=heart&logoColor=white)

**Every Drop Counts, Every Life Matters**

HopeDrops is a comprehensive Blood Bank Management System designed to streamline blood donation processes, connect donors with hospitals, and save lives through efficient blood inventory management.

## 🚀 Quick Deployment

### For New Installation:

1. **Install XAMPP** from [apachefriends.org](https://www.apachefriends.org/)
2. **Copy HopeDrops folder** to `C:\xampp\htdocs\`
3. **Import database**: Use `sql/bloodbank_complete.sql` in phpMyAdmin
4. **Run setup check**: Double-click `setup_check.bat` (Windows) or `./setup_check.sh` (Linux/Mac)
5. **Access system**: Open `http://localhost/HopeDrops`

📖 **Complete guide**: See [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) for detailed instructions.

## 🌟 Features

### 👥 Three User Roles

- **🛡️ Admin**: Complete system oversight and management
- **🏥 Hospital**: Blood inventory and donor management
- **❤️ Donor**: Donation scheduling and reward tracking

### 🎯 Core Functionality

- ✅ **Real-time Blood Inventory Tracking**
- ✅ **Emergency Blood Request System**
- ✅ **Donation Scheduling & Management**
- ✅ **Comprehensive Rewards Program**
- ✅ **Multi-role Authentication System**
- ✅ **Responsive Mobile-Friendly Design**
- ✅ **Advanced Reporting & Analytics**
- ✅ **Notification System**
- ✅ **Campaign Management**

## 🏗️ Technology Stack

### Frontend

- **HTML5** - Semantic markup
- **CSS3** - Modern styling with Flexbox/Grid
- **JavaScript (ES6+)** - Interactive functionality
- **Font Awesome 6** - Icons
- **SweetAlert2** - Beautiful alerts

### Backend

- **PHP 8.0+** - Server-side logic
- **MySQL 8.0+** - Database management
- **PDO** - Database abstraction layer

### Development Environment

- **XAMPP** - Local development server
- **Apache** - Web server
- **phpMyAdmin** - Database administration

## 📁 Project Structure

```
HopeDrops/
├── index.html                 # Landing page
├── login.html                # Authentication page
├── register.html             # User registration
├── README.md                 # Documentation
├── admin/                    # Admin dashboard files
│   ├── dashboard.html
│   ├── manage_hospitals.html
│   └── view_campaigns.html
├── hospital/                 # Hospital portal files
│   ├── dashboard.html
│   ├── manage_donors.html
│   └── update_status.html
├── user/                     # Donor portal files
│   ├── dashboard.html
│   ├── search_blood.html
│   ├── history.html
│   └── rewards.html
├── css/
│   └── style.css            # Main stylesheet
├── js/
│   └── main.js              # Core JavaScript
├── php/                     # Backend API files
│   ├── db_connect.php       # Database connection
│   ├── login.php            # Authentication handler
│   ├── register.php         # Registration handler
│   ├── logout.php           # Logout handler
│   ├── check_session.php    # Session management
│   ├── check_username.php   # Username availability
│   ├── get_blood_availability.php
│   ├── get_campaigns.php
│   ├── get_hospitals.php
│   └── get_statistics.php
└── sql/
    └── bloodbank_db.sql     # Database schema
```

## 🚀 Installation & Setup

### Prerequisites

- **XAMPP 8.0+** (includes Apache, MySQL, PHP)
- **Web browser** (Chrome, Firefox, Safari, Edge)
- **Text editor** (VS Code, Sublime Text, etc.)

### Step 1: Download and Install XAMPP

1. **Download XAMPP** from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. **Install XAMPP** with default settings
3. **Start XAMPP Control Panel**

### Step 2: Setup the Project

1. **Clone or download** this project
2. **Copy the HopeDrops folder** to your XAMPP htdocs directory:
   ```
   C:\xampp\htdocs\HopeDrops\
   ```
   _Note: On macOS/Linux, the path might be `/Applications/XAMPP/htdocs/` or `/opt/lampp/htdocs/`_

### Step 3: Start XAMPP Services

1. **Open XAMPP Control Panel**
2. **Start Apache** by clicking the "Start" button
3. **Start MySQL** by clicking the "Start" button
4. **Verify services** are running (green indicators)

### Step 4: Setup the Database

#### Method 1: Using phpMyAdmin (Recommended)

1. **Open your browser** and go to `http://localhost/phpmyadmin`
2. **Click "Import"** in the top menu
3. **Click "Choose File"** and select `HopeDrops/sql/bloodbank_db.sql`
4. **Click "Go"** to execute the SQL script
5. **Verify** the database `bloodbank_db` is created with all tables

#### Method 2: Using MySQL Command Line

```bash
# Navigate to MySQL bin directory
cd C:\xampp\mysql\bin

# Login to MySQL
mysql -u root -p

# Source the SQL file
source C:\xampp\htdocs\HopeDrops\sql\bloodbank_db.sql
```

### Step 5: Access the Application

1. **Open your browser**
2. **Navigate to**: `http://localhost/HopeDrops/`
3. **You should see** the HopeDrops homepage

## 🔐 Demo Login Credentials

### Admin Access

- **Username**: `admin`
- **Password**: `admin123`
- **Role**: Administrator

### Hospital Access

- **Username**: `hospital1`
- **Password**: `hospital123`
- **Role**: Hospital/Organization

### Donor Access

- **Username**: `donor1`
- **Password**: `donor123`
- **Role**: Blood Donor

## 📊 Database Schema

### Core Tables

#### Users Table

```sql
users (
    id, username, password, role, full_name, email, phone,
    address, date_of_birth, blood_type, gender, created_at,
    updated_at, is_active
)
```

#### Hospitals Table

```sql
hospitals (
    id, user_id, hospital_name, license_number, address,
    city, state, pincode, contact_person, contact_phone,
    contact_email, blood_storage, is_approved
)
```

#### Donations Table

```sql
donations (
    id, donor_id, hospital_id, blood_type, donation_date,
    donation_time, status, units_donated, certificate_generated
)
```

#### Blood Inventory Table

```sql
blood_inventory (
    id, hospital_id, blood_type, units_available,
    units_required, expiry_date, last_updated
)
```

### Additional Tables

- **campaigns** - Donation campaigns and drives
- **notifications** - User notifications
- **rewards** - Donor reward system
- **reward_catalog** - Available rewards
- **reward_claims** - Reward redemptions
- **blood_requests** - Emergency blood requests
- **user_tokens** - Remember me & password reset tokens
- **activity_logs** - System audit trail

## 🎨 Design Features

### Color Palette

- **Primary Red**: `#dc3545` - Medical emergency theme
- **Secondary Gray**: `#6c757d` - Professional contrast
- **Accent White**: `#ffffff` - Clean backgrounds
- **Success Green**: `#28a745` - Positive actions
- **Warning Orange**: `#ffc107` - Attention alerts

### Responsive Design

- **Mobile-First Approach**
- **Flexible Grid System**
- **Touch-Friendly Interface**
- **Cross-Browser Compatibility**

### User Experience

- **Intuitive Navigation**
- **Real-Time Data Updates**
- **Form Validation**
- **Loading States**
- **Error Handling**
- **Accessibility Features**

## ⚙️ Configuration

### Database Configuration

Edit `php/db_connect.php` to modify database settings:

```php
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', ''); // Set if you have a password
define('DB_NAME', 'bloodbank_db');
```

### Application Settings

```php
define('APP_NAME', 'HopeDrops');
define('BASE_URL', 'http://localhost/HopeDrops/');
define('SESSION_TIMEOUT', 3600); // 1 hour
```

## 🔧 Troubleshooting

### Common Issues

#### "Cannot connect to database"

- ✅ Ensure MySQL service is running in XAMPP
- ✅ Check database credentials in `db_connect.php`
- ✅ Verify database exists in phpMyAdmin

#### "404 Not Found" Errors

- ✅ Ensure Apache service is running
- ✅ Check file paths are correct
- ✅ Verify project is in htdocs directory

#### "Permission Denied" Errors

- ✅ Check folder permissions
- ✅ Run XAMPP as administrator (Windows)
- ✅ Ensure proper file ownership (Linux/Mac)

#### Login Issues

- ✅ Verify database is properly imported
- ✅ Check if default users exist
- ✅ Clear browser cache and cookies

### Debug Mode

Enable debug mode in `js/main.js`:

```javascript
const HopeDrops = {
  debug: true, // Set to false for production
};
```

## 🚀 Deployment

### For Production Deployment

1. **Choose a hosting provider** (AWS, DigitalOcean, shared hosting)
2. **Upload project files** to server
3. **Create production database**
4. **Update configuration** files with production settings
5. **Set proper file permissions**
6. **Enable HTTPS** for security
7. **Configure backup** procedures

### Security Considerations

- ✅ Change default passwords
- ✅ Use strong database credentials
- ✅ Enable HTTPS/SSL
- ✅ Regular security updates
- ✅ Input validation and sanitization
- ✅ SQL injection prevention
- ✅ XSS protection
- ✅ CSRF token implementation

## 📱 Browser Compatibility

| Browser | Version | Status             |
| ------- | ------- | ------------------ |
| Chrome  | 90+     | ✅ Fully Supported |
| Firefox | 88+     | ✅ Fully Supported |
| Safari  | 14+     | ✅ Fully Supported |
| Edge    | 90+     | ✅ Fully Supported |
| Opera   | 76+     | ✅ Fully Supported |

## 🤝 Contributing

We welcome contributions! Here's how you can help:

1. **Fork the repository**
2. **Create a feature branch**
3. **Make your changes**
4. **Test thoroughly**
5. **Submit a pull request**

### Development Guidelines

- Follow PSR coding standards for PHP
- Use semantic HTML5
- Write clean, commented code
- Test across different browsers
- Ensure responsive design

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆘 Support

### Getting Help

- **Documentation**: Check this README first
- **Issues**: Report bugs via GitHub Issues
- **Community**: Join our discussion forums
- **Email**: support@hopedrops.com

### Feature Requests

We're always looking to improve! Submit feature requests through:

- GitHub Issues with "enhancement" label
- Community feedback forms
- Direct email suggestions

## 🙏 Acknowledgments

### Special Thanks

- **Medical professionals** for domain expertise
- **Open source community** for tools and libraries
- **Blood donation organizations** for inspiration
- **Beta testers** for valuable feedback

### Technologies Used

- Font Awesome for beautiful icons
- SweetAlert2 for elegant notifications
- PHP community for robust backend tools
- MySQL for reliable data storage

## 📈 Roadmap

### Version 2.0 (Planned)

- 📱 Mobile app development
- 🔔 Push notifications
- 📍 GPS-based hospital finder
- 🤖 AI-powered donation scheduling
- 📊 Advanced analytics dashboard
- 🌐 Multi-language support
- 📧 Email automation system
- 🔐 Two-factor authentication

### Long-term Goals

- 🌍 Multi-country support
- 🏥 Hospital network integration
- 📱 IoT device integration
- 🤖 Machine learning predictions
- 🔗 Blockchain verification
- ☁️ Cloud-native architecture

---

## 📞 Contact Information

**HopeDrops Development Team**

- 🌐 **Website**: [www.hopedrops.com](http://www.hopedrops.com)
- 📧 **Email**: support@hopedrops.com
- 🐦 **Twitter**: @HopeDrops
- 📘 **Facebook**: HopeDropsOfficial
- 💼 **LinkedIn**: HopeDrops

**Emergency Support**: Available 24/7 for critical issues

---

_"Every drop of blood you donate can save up to three lives. With HopeDrops, saving lives has never been easier."_

**Built with ❤️ for humanity by the HopeDrops team**
#   H o p e d r o p s 
 
 
