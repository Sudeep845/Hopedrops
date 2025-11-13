#!/bin/bash

echo "================================"
echo "HopeDrops Blood Bank System"
echo "Quick Setup Verification (Linux/Mac)"
echo "================================"
echo

# Check if XAMPP is installed
echo "Checking XAMPP installation..."
if [ -f "/opt/lampp/lampp" ]; then
    echo "✅ XAMPP found at /opt/lampp/"
    XAMPP_PATH="/opt/lampp"
elif [ -f "/Applications/XAMPP/xamppfiles/xampp" ]; then
    echo "✅ XAMPP found at /Applications/XAMPP/"
    XAMPP_PATH="/Applications/XAMPP/xamppfiles"
else
    echo "❌ XAMPP not found"
    echo "Please install XAMPP first from https://www.apachefriends.org/"
    exit 1
fi

echo
echo "Checking Apache service..."
if lsof -i :80 >/dev/null 2>&1; then
    echo "✅ Apache appears to be running on port 80"
else
    echo "⚠️  Apache may not be running. Start with: sudo $XAMPP_PATH/xampp start"
fi

echo
echo "Checking MySQL service..."
if lsof -i :3306 >/dev/null 2>&1; then
    echo "✅ MySQL appears to be running on port 3306"
else
    echo "⚠️  MySQL may not be running. Start with: sudo $XAMPP_PATH/xampp start"
fi

echo
echo "Checking application files..."
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [ -f "$SCRIPT_DIR/index.html" ]; then
    echo "✅ Application files found"
else
    echo "❌ Application files not found in current directory"
    echo "Please ensure you're running this from the HopeDrops folder"
fi

if [ -f "$SCRIPT_DIR/sql/bloodbank_complete.sql" ]; then
    echo "✅ Database SQL file found"
else
    echo "❌ Database SQL file not found"
    echo "Please ensure sql/bloodbank_complete.sql exists"
fi

echo
echo "================================"
echo "Next steps:"
echo "1. Start XAMPP: sudo $XAMPP_PATH/xampp start"
echo "2. Import sql/bloodbank_complete.sql in phpMyAdmin"
echo "3. Open http://localhost/HopeDrops in your browser"
echo "================================"
echo

# Check if GUI is available (for opening browser)
if command -v xdg-open >/dev/null 2>&1; then
    echo "Opening phpMyAdmin for database import..."
    sleep 2
    xdg-open "http://localhost/phpmyadmin" >/dev/null 2>&1 &
    
    echo "Opening HopeDrops application..."
    sleep 3
    xdg-open "http://localhost/HopeDrops" >/dev/null 2>&1 &
elif command -v open >/dev/null 2>&1; then
    echo "Opening phpMyAdmin for database import..."
    sleep 2
    open "http://localhost/phpmyadmin" >/dev/null 2>&1 &
    
    echo "Opening HopeDrops application..."
    sleep 3
    open "http://localhost/HopeDrops" >/dev/null 2>&1 &
else
    echo "Please manually open:"
    echo "- phpMyAdmin: http://localhost/phpmyadmin"
    echo "- HopeDrops: http://localhost/HopeDrops"
fi

echo "Setup verification complete!"