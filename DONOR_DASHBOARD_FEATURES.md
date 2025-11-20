# Dynamic Donor Dashboard - Feature Documentation

## Overview

The donor dashboard has been enhanced with dynamic content that fetches real-time data from hospitals, admin systems, and emergency blood request databases. This provides donors with up-to-date information to make informed decisions about blood donation.

## New Features

### 1. Emergency Blood Request Alerts

**Location:** Top of dashboard (conditionally displayed)

**Features:**

- Real-time display of critical, emergency, and high-priority blood requests
- Filtering based on donor's blood type
- Urgency level indicators with visual animations
- Direct contact options and response buttons
- Time-since-posted information

**Visual Elements:**

- Pulsing animation for critical requests
- Color-coded urgency badges (red for critical, orange for high)
- Slide-down animation when alerts appear

**User Actions:**

- View emergency details
- Contact hospital directly
- Respond to emergency request

### 2. Nearby Hospitals with Blood Availability

**Location:** Middle section of dashboard

**Features:**

- Lists approved hospitals in donor's city/state
- Real-time blood inventory for all blood types
- Color-coded availability status:
  - Green (Available): More than 10 units
  - Orange (Low): 1-10 units
  - Red (Unavailable): 0 units
- Hospital contact information
- Quick appointment booking

**Data Displayed:**

- Hospital name and address
- Contact phone and email
- Blood inventory grid for all 8 blood types
- Visual indicators for stock levels

**User Actions:**

- Book appointment at specific hospital
- View hospital details
- Contact hospital

### 3. Active Blood Donation Campaigns

**Location:** Bottom right section

**Features:**

- Displays active blood drives and campaigns
- Progress tracking (current donors vs. target)
- Days remaining countdown
- Campaign location and organizer information
- One-click registration

**Visual Elements:**

- Progress bar showing campaign status
- Active badge
- Days remaining counter
- Gradient background

**User Actions:**

- Join campaign
- View campaign details
- Track participation

### 4. System Alerts and Notifications

**Features:**

- Automatic toast notifications for critical blood shortages
- Alerts for nearby campaigns
- Personalized notifications based on donor's blood type and location

## API Endpoints

### Main Dashboard Data API

**File:** `php/get_donor_dashboard_data.php`

**Endpoint:** `GET /php/get_donor_dashboard_data.php`

**Response Structure:**

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

### Campaign Registration API

**File:** `php/register_campaign.php`

**Endpoint:** `POST /php/register_campaign.php`

**Request Body:**

```json
{
  "campaign_id": 123
}
```

**Response:**

```json
{
  "success": true,
  "message": "Successfully registered for the campaign",
  "registration_id": 456
}
```

## Data Flow

### 1. Emergency Requests

```
Database: requests table
Filter: urgency_level IN ('critical', 'emergency', 'high')
Filter: blood_type matches donor
Filter: status = 'pending'
Sort: By urgency level, then creation date
Limit: 5 most recent
```

### 2. Nearby Hospitals

```
Database: hospitals table + blood_inventory table
Filter: is_approved = 1, is_active = 1
Filter: city or state matches donor location
Join: Blood inventory data
Sort: Same city first, then alphabetically
Limit: 10 hospitals
```

### 3. Blood Availability

```
Database: blood_inventory table
Aggregate: SUM(units_available) per blood type
Filter: Only approved and active hospitals
Group: By blood type
```

### 4. Active Campaigns

```
Database: hospital_activities table
Filter: activity_type = 'campaign_created'
Filter: Hospital is approved and active
Sort: Most recent first
Limit: 5 campaigns
```

## Styling Features

### Emergency Alerts

- **Animation:** Slide-down entrance, pulsing critical badges
- **Colors:** Red (#d32f2f) for critical, Orange (#ff9800) for high
- **Icons:** Exclamation triangle, tint (blood), hospital

### Hospital Cards

- **Hover Effect:** Elevation shadow, border color change
- **Grid Layout:** 4-column blood type display (responsive to 2 columns on mobile)
- **Status Colors:**
  - Available: Green (#e8f5e9)
  - Low: Orange (#fff3e0)
  - Unavailable: Red (#ffebee)

### Campaign Cards

- **Background:** Gradient from white to light gray
- **Progress Bar:** Bootstrap success color
- **Badge:** Green for active campaigns

## Responsive Design

### Desktop (> 768px)

- 4-column blood inventory grid
- Side-by-side recent activity and campaigns
- Full hospital details visible

### Mobile (< 768px)

- 2-column blood inventory grid
- Stacked sections
- Compact hospital cards

## Auto-Refresh

- Dashboard data refreshes every 60 seconds
- User stats update on each refresh
- No page reload required

## User Interactions

### Emergency Response Flow

1. Donor sees emergency request matching their blood type
2. Clicks "Respond" button
3. Confirmation dialog appears
4. System sends response to hospital
5. Hospital receives notification with donor contact info
6. Success message displayed to donor

### Campaign Registration Flow

1. Donor views active campaign
2. Clicks "Join" button
3. Confirmation dialog appears
4. Registration saved to database
5. Activity logged
6. Success notification shown

### Hospital Appointment Flow

1. Donor views nearby hospitals
2. Clicks "Book" on desired hospital
3. Appointment modal opens with hospital pre-selected
4. Donor selects date, time, and donation type
5. Appointment created and confirmed

## Database Tables Used

### Existing Tables

- `users` - Donor information
- `hospitals` - Hospital details
- `blood_inventory` - Blood availability
- `requests` - Emergency blood requests
- `hospital_activities` - Campaigns and activities

### New Tables

- `campaign_registrations` - Tracks donor campaign participation

## Error Handling

### API Failures

- Graceful degradation - sections hide if no data
- Error logging for debugging
- User-friendly error messages
- Retry mechanisms for transient failures

### Missing Data

- Default values provided
- "No data" messages displayed
- Icons and helpful text guide users

## Security Features

### Session Validation

- User authentication checked on every API call
- Role-based access control
- SQL injection prevention with prepared statements

### Data Sanitization

- Input validation on all user inputs
- HTML escaping for display
- JSON validation

## Performance Optimizations

### Database Queries

- Indexed columns for faster searches
- Limit clauses to prevent large result sets
- Efficient JOINs with proper foreign keys

### Frontend

- Lazy loading of sections
- Debounced refresh intervals
- Cached user session data

## Future Enhancements

### Planned Features

1. Real-time notifications using WebSockets
2. GPS-based distance calculation for hospitals
3. Blood type compatibility calculator
4. Donation reminder system
5. Gamification with achievement badges
6. Social sharing for campaigns
7. Multi-language support
8. Dark mode theme

### API Improvements

1. Pagination for large result sets
2. Advanced filtering options
3. Search functionality
4. Sorting preferences
5. User preferences for alert types

## Testing Recommendations

### Unit Tests

- API endpoint responses
- Data validation
- Error handling
- Session management

### Integration Tests

- End-to-end user flows
- Database transactions
- External API calls
- Email notifications

### UI Tests

- Responsive design on various devices
- Browser compatibility
- Animation performance
- Accessibility compliance

## Deployment Notes

### Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache with mod_rewrite
- HTTPS enabled for production

### Configuration

- Update database credentials in `db_connect.php`
- Configure email settings for notifications
- Set appropriate session timeout values
- Enable error logging in production

## Support and Maintenance

### Monitoring

- Log API response times
- Track error rates
- Monitor database performance
- User engagement metrics

### Regular Updates

- Database optimization (monthly)
- Security patches (as released)
- Feature enhancements (quarterly)
- User feedback incorporation (ongoing)

---

**Created:** November 20, 2025  
**Version:** 1.0  
**Author:** HopeDrops Development Team
