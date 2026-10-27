# Google Meet Integration - Quick Start Guide

## 🚀 Quick Setup (5 Minutes)

### Step 1: Get Google Credentials (2 min)

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create new project or select existing
3. Enable **Google Calendar API**:
   - APIs & Services → Library → Search "Calendar" → Enable
4. Create OAuth credentials:
   - APIs & Services → Credentials → Create Credentials → OAuth 2.0 Client ID
   - Application type: Web application
   - Authorized redirect URIs: `https://your-site.com/wp-admin/`
5. Copy **Client ID** and **Client Secret**

### Step 2: Configure Plugin (1 min)

1. WordPress Admin → OhMyLMS → Settings → Integrations
2. Click on **Google Meet** tab
3. Paste **Client ID**
4. Paste **Client Secret**
5. Verify **Redirect URI** matches your Google Console setting
6. Click **Save**
7. Click **Authorize with Google**
8. Grant permissions in popup window

### Step 3: Create Test Meeting (2 min)

1. Go to Courses → Select a course → Edit
2. Click **Add Lesson** or edit existing
3. In lesson settings:
   - **Platform**: Select "Google Meet"
   - **Topic**: "Test Google Meet Session"
   - **Agenda**: "Testing the new integration"
   - **Date/Time**: Tomorrow at 2:00 PM
   - **Duration**: 60 minutes
   - **Timezone**: Your timezone
4. Click **Create Session**
5. ✅ Meeting link generated automatically!

### Step 4: Verify (30 sec)

1. View the lesson on frontend
2. Should see:
   - ✅ Google Meet icon
   - ✅ Meeting details
   - ✅ Countdown timer
   - ✅ "Upcoming" badge
   - ✅ Join button with Meet link

---

## 🧪 Testing Scenarios

### Scenario 1: Upcoming Meeting
- Create meeting for tomorrow
- **Expected**: Countdown timer, "Upcoming" badge
- **Action**: Click join link → Opens Google Meet (may show "too early")

### Scenario 2: Live Meeting
- Create meeting for current time
- **Expected**: "Live Now" badge (red, pulsing), prominent join button
- **Action**: Click join → Enters meeting immediately

### Scenario 3: Past Meeting
- Create meeting for yesterday
- **Expected**: "Ended" badge (gray), disabled/no join button

### Scenario 4: Update Meeting
- Edit existing meeting, change time
- **Expected**: Updated countdown, new time reflected

### Scenario 5: Delete Meeting
- Delete a meeting
- **Expected**: Meeting removed from course, calendar event deleted

---

## 🐛 Troubleshooting

### "Authorization failed"
**Cause**: Incorrect credentials or redirect URI mismatch  
**Fix**: 
1. Verify Client ID/Secret in Google Console
2. Ensure redirect URI matches exactly (including protocol https://)
3. Clear browser cache and retry

### "Token expired"
**Cause**: Access token expired and refresh failed  
**Fix**: 
1. Re-authorize in settings (automatic refresh should handle this)
2. Check refresh token is saved in database
3. Verify Google project hasn't been deleted

### "Meeting not creating"
**Cause**: API quota exceeded or permissions issue  
**Fix**:
1. Check Google Console → APIs & Services → Quotas
2. Verify Calendar API is enabled
3. Check PHP error logs: `wp-content/debug.log`
4. Ensure user has calendar access

### Meeting link is empty
**Cause**: Conference data not returned  
**Fix**:
1. Verify using `conferenceDataVersion=1` parameter
2. Check API response in browser console
3. Ensure Google Workspace account (if applicable)

---

## 🔍 Debug Checklist

```php
// Enable WordPress debugging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Check Token Storage
```php
$user_id = get_current_user_id();
$access_token = get_user_meta($user_id, 'ohmylms_googlemeet_access_token', true);
$refresh_token = get_user_meta($user_id, 'ohmylms_googlemeet_refresh_token', true);
$expires = get_user_meta($user_id, 'ohmylms_googlemeet_token_expires', true);

var_dump([
    'has_access_token' => !empty($access_token),
    'has_refresh_token' => !empty($refresh_token),
    'expires_at' => date('Y-m-d H:i:s', $expires),
    'is_expired' => time() > $expires
]);
```

### Test API Connection
```php
use OhMyLMS\Integrations\GoogleMeet\Includes\Services\TokenService;

$token_service = new TokenService();
$token = $token_service->get_valid_access_token();
echo $token ? 'Token valid ✅' : 'Token invalid ❌';
```

### Check Meeting Data
```php
global $post;
$meeting_id = get_post_meta($post->ID, '_googlemeet_meeting_id', true);
$meet_link = get_post_meta($post->ID, '_googlemeet_link', true);
$start_time = get_post_meta($post->ID, '_session_start_time', true);

var_dump([
    'meeting_id' => $meeting_id,
    'meet_link' => $meet_link,
    'start_time' => date('Y-m-d H:i:s', $start_time)
]);
```

---

## 📊 Verification Points

### Backend Verification
- [ ] Files exist in correct directories
- [ ] REST API endpoints respond
- [ ] OAuth flow completes successfully
- [ ] Tokens are stored in database
- [ ] Meetings create in Google Calendar
- [ ] Meeting links are generated

### Frontend Verification
- [ ] Settings page loads
- [ ] Modal opens for meeting creation
- [ ] Form validation works
- [ ] Success/error messages display
- [ ] Icon renders correctly

### Integration Verification
- [ ] Platform shows in dropdown
- [ ] Template loads for lessons
- [ ] Countdown timer counts down
- [ ] Status changes (upcoming → live → ended)
- [ ] Join button links to Google Meet
- [ ] Styles applied correctly

---

## 🎯 Expected Results

### After Setup
✅ Google Meet appears in integration settings  
✅ Authorization completes without errors  
✅ "Google Meet" option in lesson platform dropdown

### After Creating Meeting
✅ Meeting ID saved to lesson meta  
✅ Google Calendar event created  
✅ Meet link generated and saved  
✅ Event shows in your Google Calendar

### On Frontend
✅ Meeting displays with correct template  
✅ Countdown shows time remaining  
✅ Status badge shows correct state  
✅ Join button works (opens Google Meet)  
✅ Responsive on mobile devices

---

## 🔄 Testing Flow

```
1. Install/Activate Plugin
         ↓
2. Configure Google OAuth
         ↓
3. Authorize with Google
         ↓
4. Create Test Course
         ↓
5. Add Google Meet Lesson
         ↓
6. Fill Meeting Details
         ↓
7. Save & View Frontend
         ↓
8. Verify Display & Countdown
         ↓
9. Test Join Button
         ↓
10. Update Meeting
         ↓
11. Verify Changes
         ↓
12. Delete Meeting
         ↓
13. Verify Cleanup
         ↓
✅ All Tests Pass!
```

---

## 💪 Advanced Testing

### Load Testing
```bash
# Create 10 meetings simultaneously
for i in {1..10}; do
    curl -X POST http://your-site.com/wp-admin/admin-ajax.php \
    -d "action=ohmylms_create_googlemeet" \
    -d "topic=Load Test $i" \
    -d "..." &
done
```

### Token Expiry Simulation
```php
// Force token expiry
update_user_meta(get_current_user_id(), 
    'ohmylms_googlemeet_token_expires', 
    time() - 3600
);

// Next API call should trigger refresh
```

### Network Failure Handling
```php
// Temporarily disable network
add_filter('pre_http_request', function() {
    return new WP_Error('network_error', 'Simulated failure');
}, 10, 3);

// Try creating meeting
// Should show graceful error message
```

---

## 📞 Quick Help

### Need Help?
1. **Check logs**: `wp-content/debug.log`
2. **Browser console**: Look for JavaScript errors
3. **Network tab**: Check API request/response
4. **Database**: Verify meta keys exist
5. **Google Console**: Check API usage/errors

### Common Commands
```bash
# Watch debug log
tail -f wp-content/debug.log

# Check plugin files
ls -la wp-content/plugins/ohmylms/includes/Integrations/GoogleMeet/

# Database check
wp db query "SELECT * FROM wp_usermeta WHERE meta_key LIKE '%googlemeet%'"
```

---

## ✅ Success Criteria

Your integration is working when:
- ✅ Can authorize without errors
- ✅ Can create meetings
- ✅ Meet links are generated
- ✅ Frontend displays correctly
- ✅ Countdown timer works
- ✅ Join button opens Google Meet
- ✅ Can update meeting details
- ✅ Can delete meetings
- ✅ Email reminders send (optional)

---

**Testing Time**: ~15-20 minutes for complete verification  
**Support**: See README.md for detailed troubleshooting
