# SLSU BC Patrol Quality Assurance Evidence

This document maps practical project evidence to the ISO/IEC 25010 quality areas used in the capstone evaluation.

## 1. ESP32 API Token Protection

The ESP32 hardware endpoints require a shared device token:

- `POST /api/rfid-scan`
- `POST /api/rfid-enrollment`
- `POST /api/rfid-heartbeat`

Set this in Dokploy:

```env
RFID_DEVICE_TOKEN=change-this-to-a-long-random-device-token
```

The ESP32 must send either header:

```text
Authorization: Bearer change-this-to-a-long-random-device-token
```

or:

```text
X-Device-Token: change-this-to-a-long-random-device-token
```

Example ESP32 HTTP header:

```cpp
http.addHeader("Authorization", "Bearer change-this-to-a-long-random-device-token");
http.addHeader("Content-Type", "application/json");
```

Expected security result:

| Scenario | Expected Result |
| --- | --- |
| Missing token | `401 Unauthorized` |
| Wrong token | `401 Unauthorized` |
| Correct token | Request is processed |
| Token not configured in deployment | `503 Service Unavailable` |

## 2. Performance Test Results

Recommended target measurements:

| Feature | Target | Actual Result | Status |
| --- | ---: | ---: | --- |
| Login page load | Under 2 seconds | To record during pilot | Pending |
| Dashboard load | Under 2 seconds | To record during pilot | Pending |
| Patrol logs filter | Under 2 seconds | To record during pilot | Pending |
| Incident report save | Under 3 seconds | To record during pilot | Pending |
| RFID scan API | Under 1 second | To record during pilot | Pending |
| PDF preview | Under 5 seconds | To record during pilot | Pending |

Simple browser test:

1. Open Chrome DevTools.
2. Go to the Network tab.
3. Reload the page or submit the action.
4. Record the request duration shown by Chrome.

Simple API test from a terminal:

```bash
curl -w "Total time: %{time_total}s\n" -o /dev/null -s \
  -H "Authorization: Bearer YOUR_DEVICE_TOKEN" \
  "https://slsubcpatrol.site/api/rfid-heartbeat?device_uid=ESP32-GH-01"
```

## 3. Backup And Restore Procedure

Backup from the Dokploy database container:

```bash
mysqldump -u root -p campus_rfid_patrol > /tmp/campus_rfid_patrol_backup.sql
```

Restore into the Dokploy database container:

```bash
mysql -u root -p campus_rfid_patrol < /tmp/campus_rfid_patrol_backup.sql
```

Recommended backup schedule:

| Data | Frequency | Storage |
| --- | --- | --- |
| MySQL database | Daily during pilot testing | Downloaded backup file |
| Uploaded incident/checklist photos | Weekly or before demonstration | Server volume backup |
| `.env` values | After every configuration change | Private secure copy |

Restore proof:

| Step | Evidence |
| --- | --- |
| Backup command completed | Screenshot or terminal output |
| Restore command completed | Screenshot or terminal output |
| User can log in after restore | Screenshot |
| Patrol logs and incident reports still appear | Screenshot |

## 4. User Acceptance Testing Results

Use this table during pilot testing:

| Role | Test Scenario | Expected Result | Actual Result | Status |
| --- | --- | --- | --- | --- |
| Guard | Login using guard account | Guard dashboard opens | To record | Pending |
| Guard | Scan RFID checkpoint | Pending patrol appears | To record | Pending |
| Guard | Take area selfie | Selfie is accepted | To record | Pending |
| Guard | Submit checklist | Patrol log is completed | To record | Pending |
| Guard | Submit incident report | Incident appears for supervisor | To record | Pending |
| Supervisor | View patrol logs | Logs are visible and filterable | To record | Pending |
| Supervisor | Print patrol PDF | PDF preview modal opens | To record | Pending |
| Supervisor | View audit trail | Activity records appear | To record | Pending |
| Supervisor | Check reader status | Online/offline status is visible | To record | Pending |

## 5. Accessibility Checklist

| Item | Expected Result | Status |
| --- | --- | --- |
| Text is readable in light mode | Clear contrast | To verify |
| Text is readable in dark mode | Clear contrast | To verify |
| Buttons have clear labels | User can understand actions | To verify |
| Validation errors are shown near forms | User understands how to fix mistakes | To verify |
| Status labels do not rely on color only | Text says Valid, Offline, Active, etc. | To verify |
| Modals have close buttons | User can exit modals | To verify |
| Mobile layout is usable | No overlapping controls | To verify |
| PDF preview has download fallback | User can still download if preview fails | To verify |

## 6. Reader Offline Monitoring Proof

The ESP32 should regularly send:

```text
POST /api/rfid-heartbeat
```

with:

```json
{
  "device_uid": "ESP32-GH-01"
}
```

and the device token header.

Evidence procedure:

1. Turn on ESP32 reader.
2. Confirm checkpoint reader status shows online or recent heartbeat.
3. Stop ESP32 power or stop heartbeat request.
4. Wait for the offline threshold used by the system.
5. Capture screenshot showing the reader offline state.
6. Capture screenshot of restored online status after heartbeat resumes.

## 7. Security Testing Checklist

| Test | Expected Result | Status |
| --- | --- | --- |
| Unauthenticated user opens dashboard | Redirected to login | To verify |
| Guard opens supervisor-only pages | Forbidden or redirected | To verify |
| Guard opens another guard PDF | Forbidden | Covered by automated tests |
| ESP32 API request without token | `401 Unauthorized` | Covered by automated tests |
| ESP32 API request with wrong token | `401 Unauthorized` | Covered by automated tests |
| Passwords are hashed | No plaintext password in database | To verify |
| Audit trail records important activities | Activity appears in audit trail | To verify |
| Sensitive database tools are not public to users | Adminer not linked in app UI | To verify |

Automated checks added:

```bash
php artisan test --filter=RfidDeviceAuthenticationTest
```
