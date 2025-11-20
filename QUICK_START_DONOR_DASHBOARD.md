# Quick Start Guide - Dynamic Donor Dashboard

## How to Use the New Features

### For Donors

#### 1. View Emergency Blood Requests

1. **Login** to your donor account
2. **Emergency alerts** appear at the top if there are critical requests matching your blood type
3. **Click "Respond"** to indicate you're willing to donate
4. **Click "Contact"** to call the hospital directly

**What You'll See:**

- Blood type needed
- Number of units required
- Hospital name and location
- Urgency level (Critical/Emergency/High)
- Time since request was posted

#### 2. Check Nearby Hospitals

1. **Scroll down** to the "Nearby Hospitals & Blood Banks" section
2. **View real-time blood availability** for all blood types
3. **Color indicators** show stock levels:
   - 🟢 Green = Good stock (>10 units)
   - 🟡 Orange = Low stock (1-10 units)
   - 🔴 Red = Out of stock (0 units)
4. **Click "Book"** to schedule an appointment at that hospital

#### 3. Join Active Campaigns

1. **Scroll to** the "Active Blood Drives" section
2. **View campaign details**:
   - Campaign name
   - Hospital/organizer
   - Location
   - Progress (donors enrolled vs target)
   - Days remaining
3. **Click "Join Campaign"** to register
4. **Confirmation** message shows success

#### 4. Monitor Your Dashboard

- Dashboard **auto-refreshes every 60 seconds**
- **No need to reload** the page
- **Real-time updates** for:
  - Emergency requests
  - Blood availability
  - Campaign progress
  - Your statistics

### For Administrators

#### Setup Required Tables

If the `campaign_registrations` table doesn't exist, it will be created automatically when a donor first registers for a campaign.

**Manual Creation (Optional):**

```sql
CREATE TABLE IF NOT EXISTS campaign_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    user_id INT NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('registered', 'attended', 'cancelled') DEFAULT 'registered',
    notes TEXT,
    UNIQUE KEY unique_registration (campaign_id, user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### Create Emergency Requests

Emergency requests that match a donor's blood type will automatically appear on their dashboard.

**Example SQL:**

```sql
INSERT INTO requests (
    hospital_id,
    blood_type,
    units_needed,
    urgency_level,
    status,
    description,
    location,
    contact_person,
    phone
) VALUES (
    1,
    'A+',
    3,
    'critical',
    'pending',
    'Urgent need for surgery patient',
    'Kathmandu',
    'Dr. Smith',
    '01-1234567'
);
```

#### Create Blood Donation Campaigns

Campaigns are stored in the `hospital_activities` table with `activity_type = 'campaign_created'`.

**Example SQL:**

```sql
INSERT INTO hospital_activities (
    hospital_id,
    activity_type,
    description,
    activity_data
) VALUES (
    1,
    'campaign_created',
    'Annual Blood Donation Drive 2025',
    JSON_OBJECT(
        'title', 'Blood Donation Drive 2025',
        'description', 'Help us reach our goal of 100 donors',
        'start_date', '2025-11-20',
        'end_date', '2025-12-20',
        'target_donors', 100,
        'current_donors', 0,
        'status', 'active',
        'location', 'Kathmandu'
    )
);
```

#### Update Blood Inventory

Keep blood inventory updated for accurate displays on donor dashboards.

**Example SQL:**

```sql
UPDATE blood_inventory
SET units_available = 15
WHERE hospital_id = 1 AND blood_type = 'A+';
```

### Testing the Features

#### Test Emergency Alerts

1. Create an emergency request with your blood type
2. Login to donor dashboard
3. Verify alert appears at top
4. Test "Respond" button
5. Check response is logged

#### Test Hospital Display

1. Ensure hospitals are approved (`is_approved = 1`)
2. Add blood inventory data
3. Login to donor dashboard
4. Verify hospitals in your city appear
5. Check blood availability is correct

#### Test Campaign Registration

1. Create a campaign via hospital_activities
2. Login to donor dashboard
3. Click "Join Campaign"
4. Verify registration success message
5. Check database for campaign_registrations entry

### API Testing

#### Test Dashboard Data API

```bash
# Using curl (ensure you're logged in with a valid session)
curl -X GET http://localhost/HopeDrops/php/get_donor_dashboard_data.php \
  -H "Cookie: PHPSESSID=your_session_id"
```

**Expected Response:**

```json
{
  "success": true,
  "emergency_requests": [...],
  "nearby_hospitals": [...],
  "blood_availability": [...],
  "active_campaigns": [...],
  "system_alerts": [...],
  "metadata": {
    "fetched_at": "2025-11-20 10:30:00",
    "user_blood_type": "A+",
    "user_location": "Kathmandu"
  }
}
```

#### Test Campaign Registration API

```bash
curl -X POST http://localhost/HopeDrops/php/register_campaign.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{"campaign_id": 1}'
```

**Expected Response:**

```json
{
  "success": true,
  "message": "Successfully registered for the campaign",
  "registration_id": 123
}
```

### Troubleshooting

#### Emergency Alerts Not Showing

**Check:**

- ✅ Request `urgency_level` is 'critical', 'emergency', or 'high'
- ✅ Request `status` is 'pending'
- ✅ Request `blood_type` matches donor's blood type
- ✅ Request was created recently

**Debug:**

```sql
-- Check emergency requests
SELECT * FROM requests
WHERE urgency_level IN ('critical', 'emergency', 'high')
AND status = 'pending';
```

#### Hospitals Not Appearing

**Check:**

- ✅ Hospital `is_approved = 1`
- ✅ Hospital `is_active = 1`
- ✅ Hospital city/state matches donor location
- ✅ Donor has city/state in their profile

**Debug:**

```sql
-- Check approved hospitals
SELECT id, hospital_name, city, is_approved, is_active
FROM hospitals
WHERE is_approved = 1 AND is_active = 1;
```

#### Blood Inventory Not Showing

**Check:**

- ✅ `blood_inventory` table has data
- ✅ `hospital_id` foreign key is correct
- ✅ `units_available` values are set

**Debug:**

```sql
-- Check blood inventory
SELECT h.hospital_name, bi.blood_type, bi.units_available
FROM blood_inventory bi
JOIN hospitals h ON bi.hospital_id = h.id
WHERE h.is_approved = 1;
```

#### Campaigns Not Displaying

**Check:**

- ✅ Campaign exists in `hospital_activities`
- ✅ `activity_type = 'campaign_created'`
- ✅ Hospital is approved
- ✅ `activity_data` contains valid JSON

**Debug:**

```sql
-- Check campaigns
SELECT ha.id, h.hospital_name, ha.activity_data
FROM hospital_activities ha
JOIN hospitals h ON ha.hospital_id = h.id
WHERE ha.activity_type = 'campaign_created'
AND h.is_approved = 1;
```

#### Auto-Refresh Not Working

**Check:**

- ✅ JavaScript console for errors
- ✅ Browser supports setInterval
- ✅ No console errors blocking execution
- ✅ API endpoints are accessible

**Debug:**
Open browser console and check for errors:

```javascript
// Manually trigger refresh
loadDynamicDashboardData();
```

### Performance Tips

#### Optimize Database

```sql
-- Add indexes for faster queries
CREATE INDEX idx_requests_blood_type ON requests(blood_type);
CREATE INDEX idx_requests_urgency ON requests(urgency_level);
CREATE INDEX idx_hospitals_city ON hospitals(city);
CREATE INDEX idx_blood_inventory_type ON blood_inventory(blood_type);
```

#### Cache Session Data

- Session data is cached to reduce database queries
- User info loaded once per session
- Blood type and location stored in session

#### Limit Result Sets

- Emergency requests: Limited to 5
- Nearby hospitals: Limited to 10
- Active campaigns: Limited to 5
- Auto-refresh: 60-second interval (adjustable)

### Browser Compatibility

**Tested and Working:**

- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+

**Required Features:**

- JavaScript enabled
- Cookies enabled
- LocalStorage available
- CSS3 animations support

### Mobile Responsiveness

**Features:**

- Responsive grid layouts
- Touch-friendly buttons
- Optimized for small screens
- 2-column blood inventory on mobile
- Stacked sections on tablets

### Security Notes

**Session Management:**

- All APIs check for valid session
- User authentication required
- Role-based access control

**SQL Injection Prevention:**

- All queries use prepared statements
- Input validation on all parameters
- Parameterized queries

**XSS Prevention:**

- HTML escaping for user-generated content
- JSON validation for API responses
- Content Security Policy headers

### Support

**Issues or Questions?**

- Check console for JavaScript errors
- Review PHP error logs
- Verify database structure
- Test API endpoints independently

**Common Solutions:**

- Clear browser cache
- Check XAMPP/Apache is running
- Verify MySQL connection
- Ensure session cookies are set

---

**Ready to Go!**
The dynamic donor dashboard is now set up and ready to use. Donors will see real-time information from hospitals, emergency requests, and active campaigns as soon as they log in.

**Next Steps:**

1. Test with sample data
2. Verify all features work
3. Monitor performance
4. Gather user feedback
5. Iterate and improve

**Documentation:**

- Full features: `DONOR_DASHBOARD_FEATURES.md`
- Implementation summary: `DONOR_DASHBOARD_SUMMARY.md`
- Visual guide: `DASHBOARD_VISUAL_GUIDE.md`
- Quick start: This file
