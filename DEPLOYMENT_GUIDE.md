# 🩸 HopeDrops Blood Bank System - Complete Deployment Guide

## 📋 System Requirements

### Minimum Requirements:

- **Operating System**: Windows 7/10/11 or Linux or macOS
- **XAMPP**: Version 7.4+ (includes Apache, MySQL, PHP)
- **RAM**: 4GB minimum, 8GB recommended
- **Storage**: 2GB free space
- **Browser**: Chrome, Firefox, Safari, or Edge (latest versions)

---

## 🚀 Step-by-Step Installation Guide

### Step 1: Install XAMPP

1. **Download XAMPP** from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. **Install XAMPP** with default settings
3. **Start Services**:
   - Open XAMPP Control Panel
   - Start **Apache** and **MySQL** services
   - Ensure both show "Running" status

### Step 2: Deploy Application Files

1. **Copy Project Files**:

   ```
   Copy the entire "HopeDrops" folder to:
   Windows: C:\xampp\htdocs\HopeDrops\
   Linux/Mac: /opt/lampp/htdocs/HopeDrops/
   ```

2. **Verify File Structure**:
   ```
   htdocs/HopeDrops/
   ├── index.html
   ├── login.html
   ├── register.html
   ├── admin/
   ├── hospital/
   ├── user/
   ├── css/
   ├── js/
   ├── php/
   └── sql/
       └── bloodbank_complete.sql
   ```

### Step 3: Setup Database

1. **Access phpMyAdmin**:

   - Open browser and go to: `http://localhost/phpmyadmin`
   - Login with username: `root`, password: (leave empty)

2. **Import Database**:

   - Click **"Import"** tab
   - Choose **"Choose File"**
   - Select: `HopeDrops/sql/bloodbank_complete.sql`
   - Click **"Go"** to import
   - ✅ Success message should appear

3. **Verify Database Creation**:
   - Check left sidebar for `bloodbank_db` database
   - Expand to see all tables (users, hospitals, donations, etc.)

### Step 4: Configure Database Connection

1. **Check Database Settings** in `php/db_connect.php`:

   ```php
   define('DB_HOST', 'localhost');
   define('DB_USERNAME', 'root');
   define('DB_PASSWORD', ''); // Default XAMPP password is empty
   define('DB_NAME', 'bloodbank_db');
   ```

2. **If using different MySQL credentials**, update the file accordingly.

### Step 5: Test Installation

1. **Access Application**:

   - Open browser: `http://localhost/HopeDrops`
   - You should see the HopeDrops homepage

2. **Test Login System**:
   - Try registering a new account
   - Test login functionality
   - Access different dashboards (donor, hospital, admin)

---

## 🔧 Troubleshooting Guide

### Common Issues & Solutions:

#### ❌ **"Database connection failed"**

**Solution:**

- Ensure MySQL service is running in XAMPP
- Verify database name is `bloodbank_db`
- Check phpMyAdmin access: `http://localhost/phpmyadmin`

#### ❌ **"404 Page Not Found"**

**Solution:**

- Verify Apache service is running
- Check file path: `C:\xampp\htdocs\HopeDrops\`
- Access: `http://localhost/HopeDrops` (not `http://localhost/HopeDrops/index.html`)

#### ❌ **"API 500 Errors"**

**Solution:**

- Check PHP error logs in XAMPP Control Panel
- Verify all PHP files are in correct locations
- Ensure database is properly imported

#### ❌ **"Permission Denied" (Linux/Mac)**

**Solution:**

```bash
sudo chmod -R 755 /opt/lampp/htdocs/HopeDrops/
sudo chown -R www-data:www-data /opt/lampp/htdocs/HopeDrops/
```

---

## 🗂️ Database Structure Overview

The `bloodbank_complete.sql` file includes:

### Core Tables:

- **users** - User authentication and profiles
- **hospitals** - Hospital/organization details
- **donations** - Blood donation records
- **blood_inventory** - Blood stock management
- **requests** - Blood requests and campaigns
- **appointments** - Donation appointments

### Sample Data:

- ✅ Admin account: `admin` / `admin123`
- ✅ Sample hospital accounts
- ✅ Sample donor accounts
- ✅ Test blood inventory data

---

## 🚀 Quick Start Commands

### Windows:

```powershell
# Start XAMPP services
cd C:\xampp
.\xampp-control.exe

# Access application
start http://localhost/HopeDrops
```

### Linux:

```bash
# Start XAMPP services
sudo /opt/lampp/lampp start

# Access application
firefox http://localhost/HopeDrops
```

### macOS:

```bash
# Start XAMPP services
sudo /Applications/XAMPP/xamppfiles/xampp start

# Access application
open http://localhost/HopeDrops
```

---

## 📊 Default Login Credentials

After database import, use these accounts for testing:

| Role     | Username     | Password      | Access Level        |
| -------- | ------------ | ------------- | ------------------- |
| Admin    | `admin`      | `admin123`    | Full system access  |
| Hospital | `cityhosp`   | `hospital123` | Hospital management |
| Donor    | `john_donor` | `donor123`    | Donor portal        |

---

## 🔒 Security Considerations

### For Production Deployment:

1. **Change default passwords** for all accounts
2. **Set strong MySQL root password**
3. **Enable PHP security settings**
4. **Configure proper file permissions**
5. **Set up SSL/HTTPS** for secure communication

---

## 📞 Support & Documentation

### File Locations:

- **Application**: `htdocs/HopeDrops/`
- **Database**: `sql/bloodbank_complete.sql`
- **Configuration**: `php/db_connect.php`
- **Logs**: XAMPP Control Panel → Logs

### Key Features:

- ✅ User Registration & Authentication
- ✅ Multi-role Dashboard (Admin, Hospital, Donor)
- ✅ Blood Inventory Management
- ✅ Donation Campaign System
- ✅ Emergency Blood Requests
- ✅ Appointment Scheduling
- ✅ Real-time Analytics

---

## ✅ Deployment Checklist

- [ ] XAMPP installed and services running
- [ ] HopeDrops files copied to htdocs
- [ ] Database imported from `bloodbank_complete.sql`
- [ ] Database connection tested
- [ ] Application accessible at `http://localhost/HopeDrops`
- [ ] Login system functional
- [ ] All dashboards working properly

**🎉 Congratulations! Your HopeDrops Blood Bank System is ready to use!**
