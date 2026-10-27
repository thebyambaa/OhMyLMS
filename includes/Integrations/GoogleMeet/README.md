# Google Meet Integration for OhMyLMS

## Overview
This integration enables OhMyLMS to create and manage Google Meet live classes, similar to the existing Zoom integration.

## Features
- Create Google Meet sessions directly from OhMyLMS
- Schedule live classes with date, time, and timezone support
- Automatic meeting link generation
- Session countdown timer
- Live meeting status indicators
- OAuth 2.0 authentication with Google
- Session reminder emails
- Responsive meeting templates

## File Structure

### Backend (PHP) - ohmylms
```
includes/Integrations/GoogleMeet/
├── GoogleMeet.php                          # Main integration class
├── Assets/
│   ├── Images/
│   │   └── googlemeet-icon.svg            # Google Meet icon
│   └── js/
│       └── content-googlemeet.js          # Frontend JavaScript
├── Includes/
│   ├── Ajax.php                           # AJAX handlers
│   ├── Hooks.php                          # WordPress hooks
│   ├── SessionReminderScheduler.php       # Email reminders
│   ├── Api/
│   │   ├── GoogleMeetApiInterface.php     # API interface
│   │   └── GoogleMeetApiClient.php        # API client
│   ├── Helpers/
│   │   └── GoogleMeetApiHelper.php        # Helper functions
│   ├── Rest/
│   │   └── GoogleMeetSettingsController.php # REST API endpoints
│   └── Services/
│       ├── TokenService.php               # OAuth token management
│       └── MeetingService.php             # Meeting operations
└── Templates/
    └── content-googlemeet.php             # Meeting display template
```

### Frontend (React) - ohmylms
```
src/
├── features/
│   ├── Lessons/
│   │   └── GoogleMeet/
│   │       └── GoogleMeetClassSetupModal.jsx  # Meeting creation modal
│   └── integrations/
│       └── Settings/
│           └── GoogleMeetSettings.jsx         # Settings page
└── shared/
    └── components/
        └── icons/
            └── GoogleMeetIcon.jsx             # Icon component
```

## Setup Instructions

### 1. Google Cloud Console Setup
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable Google Calendar API
4. Go to "APIs & Services" > "Credentials"
5. Create OAuth 2.0 Client ID
6. Add authorized redirect URI (your WordPress admin URL)
7. Note down Client ID and Client Secret

### 2. Plugin Configuration
1. Navigate to OhMyLMS > Settings > Integrations > Google Meet
2. Enter your Client ID
3. Enter your Client Secret
4. Enter your Redirect URI (provided in the settings)
5. Click "Save"
6. Click "Authorize with Google"
7. Grant necessary permissions

### 3. Creating a Google Meet Session
1. Create or edit a course
2. Add a new lesson/session
3. Select "Google Meet" as the platform
4. Fill in:
   - Session Topic
   - Agenda
   - Start Date & Time
   - Duration (in minutes)
   - Timezone
5. Click "Create Session"
6. Meeting link will be automatically generated

## API Endpoints

### Settings
- `GET /wp-json/ohmylms/v1/googlemeet/settings/credentials` - Get saved credentials
- `POST /wp-json/ohmylms/v1/googlemeet/settings/credentials` - Save credentials
- `POST /wp-json/ohmylms/v1/googlemeet/settings/oauth-callback` - Handle OAuth callback

### Meetings (AJAX)
- `ohmylms_create_googlemeet` - Create new meeting
- `ohmylms_update_googlemeet` - Update existing meeting
- `ohmylms_delete_googlemeet` - Delete meeting
- `ohmylms_get_googlemeet` - Get meeting details

## Google Calendar API Integration

The integration uses Google Calendar API v3 to:
- Create calendar events with Google Meet conference links
- Update event details
- Delete events
- Retrieve event information

### Required OAuth Scopes
- `https://www.googleapis.com/auth/calendar`
- `https://www.googleapis.com/auth/calendar.events`

## Features Comparison: Zoom vs Google Meet

| Feature | Zoom | Google Meet |
|---------|------|-------------|
| OAuth Authentication | ✅ | ✅ |
| Create Meetings | ✅ | ✅ |
| Update Meetings | ✅ | ✅ |
| Delete Meetings | ✅ | ✅ |
| Schedule Future Sessions | ✅ | ✅ |
| Timezone Support | ✅ | ✅ |
| Meeting Settings | Host Video, Participant Video, etc. | Managed by Google |
| Session Reminders | ✅ | ✅ |
| Live Status Indicator | ✅ | ✅ |
| Countdown Timer | ✅ | ✅ |

## Development Notes

### Token Management
- Access tokens are refreshed automatically when expired
- Tokens are stored per user in WordPress user meta
- Refresh tokens allow long-term access without re-authentication

### Meeting Status
- **Upcoming**: Meeting scheduled for future
- **Live**: Meeting currently in progress
- **Ended**: Meeting has finished

### Customization
The template (`content-googlemeet.php`) can be customized by:
1. Copying to your theme: `your-theme/ohmylms/integrations/googlemeet/content-googlemeet.php`
2. Modifying the styles and layout

## Troubleshooting

### Common Issues

**"Authorization failed"**
- Verify Client ID and Client Secret are correct
- Ensure redirect URI matches exactly in Google Cloud Console
- Check that Google Calendar API is enabled

**"Token expired"**
- The integration automatically refreshes tokens
- If issues persist, re-authorize in settings

**"Meeting not creating"**
- Check PHP error logs
- Verify Google Calendar API quota hasn't been exceeded
- Ensure proper permissions in Google Cloud Console

## Security Considerations

1. **OAuth Tokens**: Stored securely in WordPress user meta
2. **API Credentials**: Sanitized and validated before storage
3. **AJAX Requests**: Nonce verification and capability checks
4. **API Calls**: Proper error handling and timeout settings

## Performance

- Token caching reduces API calls
- Automatic token refresh prevents unnecessary re-authentication
- Meeting data cached in WordPress post meta

## Future Enhancements

- [ ] Add participants management
- [ ] Recording support (if available via API)
- [ ] Calendar sync
- [ ] Bulk session creation
- [ ] Advanced meeting analytics
- [ ] Attendance tracking
- [ ] Breakout rooms support

## Support

For issues or questions:
1. Check WordPress debug log
2. Verify Google Cloud Console settings
3. Review API quotas and limits
4. Contact OhMyLMS support

## Version History

- **1.0.0** - Initial release
  - Basic Google Meet integration
  - OAuth 2.0 authentication
  - Meeting creation, update, delete
  - Session reminders
  - Frontend templates

## Credits

Developed for OhMyLMS Pro
Following Zoom integration patterns and company best practices

## License

Proprietary - Part of OhMyLMS Pro
