# Module 06: Door Access Control Integration

## UI Requirement

All screens within this module shall follow the UI standards in:

```text
00-ui-architecture.md
```

Use Laravel Blade + Bootstrap 5 only. Do not use React, Vue, Svelte, Livewire, InertiaJS, or SPA architecture.

---


## Objective
Synchronize membership and RFID card status with the door access controller.

## Hardware Assumption

- Dahua ASI1201E-D standalone access terminal
- 1st Floor Door IP address is configured in Settings, current live value `192.168.100.11`
- 2nd Floor Door IP address is configured in Settings, current planned value `192.168.100.12`
- Dahua SDK port is `37777`
- Local LAN connection
- Controller stores authorized card list locally
- System must continue to work without internet access

## Access Logic

| Membership Status | RFID Card Status | Door Access |
|---|---|---|
| Active | Active | Granted |
| Expired | Disabled | Denied |
| Suspended | Disabled | Denied |
| Cancelled | Disabled | Denied |

## Features

- Sync member card to controller
- Disable expired card
- Re-enable renewed card
- Manual sync button
- Sync log
- Controller connection status

## Sync Triggers

- New member card assigned
- Membership renewed
- Membership expired
- Member suspended
- Card replaced
- Manual sync requested

## Suggested Database Tables

### access_sync_logs
Fields:

- id
- member_id
- rfid_card_id
- action
- status
- request_payload
- response_payload
- error_message
- synced_at
- created_at
- updated_at

### access_controller_settings
Fields:

- id
- controller_name
- controller_ip
- controller_port
- username
- password_encrypted
- status
- last_sync_at
- created_at
- updated_at

## Sync Actions

- ADD_CARD
- UPDATE_CARD
- DELETE_CARD
- DISABLE_CARD
- ENABLE_CARD
- FULL_SYNC

## Important Design Rule

The access controller should contain the latest authorized card list. This allows the door to operate even if the gym software is temporarily unavailable.

## Dahua ASI1201E-D Offline Integration Plan

The integration must run fully on the local network. The Laravel application should not call any internet service for door access.

Recommended architecture:

1. Store each door device in `access_controller_settings`.
   - Door name
   - IP address
   - Port
   - Enabled status
   - Encrypted local device credential
   - Last sync status
   - Last sync time
   - Last error
2. Laravel queues access changes in `access_sync_logs`.
   - Assign card
   - Disable card
   - Re-enable card
   - Replace card
   - Full sync
3. A local Laravel scheduled command pushes pending changes to each configured Dahua door device.
4. The door device keeps the authorized card list locally, so entry can still work if internet is down.
5. The login/sidebar status capsule shows whether the Dahua device is ready to receive data from the system:
   - `1st Floor Door Online` in green when the device is configured and ready for sync
   - `1st Floor Door Offline` in red when the device is failed, disabled, or missing required config

## Integration Method Options

### Preferred: Dahua NetSDK / Local Device SDK Bridge

Use Dahua NetSDK or the Dahua access-control SDK locally on the same LAN.

Because Laravel/PHP is not the best runtime for vendor C/C++ SDK bindings, the safer production design is:

- Laravel remains the main system.
- A small local bridge service runs on the same machine or LAN.
- Laravel sends local HTTP requests to the bridge, for example `http://127.0.0.1:8787/dahua/sync-card`.
- The bridge talks to the Dahua device using the vendor SDK.
- The bridge returns success/failure to Laravel.

Bridge service options:

- Node.js bridge using a Dahua SDK wrapper, if a stable wrapper is available.
- Python bridge using SDK bindings, if available.
- Small native service using Dahua NetSDK directly.

This keeps the offline requirement intact because all calls stay inside the local machine/LAN.

### Manual V1.0.3 Alignment

The supplied `Access Standalone User Manual V1.0.3` confirms the Dahua standalone device is a local TCP/IP access device. The actual ASI1201E-D unit at site answers on Dahua SDK port `37777`; browser ports `80` and `443` are refused.

Relevant implementation assumptions now used by Laravel:

- Door settings store `driver = dahua_standalone`.
- Door settings default to port `37777`.
- Device username, password, card number format, bridge URL, and bridge token are stored in `encrypted_credentials`.
- Card number format must match SmartPSS Lite card type configuration. The manual notes hexadecimal is the platform default, but the system allows Decimal or Hexadecimal because many counter workflows capture printed decimal card IDs.
- Dahua local user ID must be numeric, so Laravel derives the device user ID from digits in `member_no`, falling back to the member database ID.
- The device can store authorized card data locally, with a documented capacity of 30,000 valid cards and 60,000 records.
- The manual does not document direct HTTP endpoints for creating/deleting card users. Automatic sync therefore targets a local SDK bridge endpoint such as `http://127.0.0.1:8787/dahua/sync-card`.
- Manual door commands are connected through the same local SDK bridge:
  - `php artisan access:door-command unlock "1st Floor Door" --seconds=5`
  - `php artisan access:door-command lock "1st Floor Door"`
  - `php artisan access:door-command status "1st Floor Door"`
- Laravel posts these commands to `http://127.0.0.1:8787/dahua/door-command`. The bridge must load Dahua NetSDK/access SDK and perform the actual relay command locally.

Until a local Dahua SDK bridge or confirmed model-specific HTTP/CGI endpoint is installed, card sync and lock/unlock commands should fail clearly rather than showing fake success. Device online/offline display is based on local TCP reachability to the configured Dahua port.

### Alternative: Dahua Local HTTP/CGI API

Some Dahua devices expose local HTTP/CGI APIs on the device IP address.

If ASI1201E-D firmware exposes the needed access-control endpoints, Laravel can call the device directly using Laravel HTTP client:

```text
Laravel -> http://192.168.100.11/...local-device-endpoint...
Laravel -> http://192.168.100.12/...local-device-endpoint...
```

This is simpler than SDK integration, but it must be confirmed on the actual ASI1201E-D firmware because not every Dahua model exposes the same card/user management endpoints.

## Data Laravel Must Send To Door Device

For each active member with access allowed:

- Member number
- Member name
- RFID card number
- Valid start date
- Valid end date
- Access enabled/disabled status
- Door group or door permission, if supported by device

For disabled/expired/suspended access:

- RFID card number
- Disable or delete action
- Reason recorded in Laravel sync log

## Device Status Laravel Must Read

Minimum status required:

- Device reachable or not reachable
- Authentication success or failure
- Last successful sync time
- Last failed sync time
- Last error message

If supported by the Dahua API/SDK, also capture:

- Door online/offline state
- Door open/closed state
- Tamper/alarm status
- Recent access event logs
- Device time

## Offline Requirements

- Door devices must be on the same LAN as the Laravel/Herd machine.
- Door IP addresses must be static or DHCP reserved.
- No cloud API is required.
- No internet connection is required for normal member access.
- Laravel should retry failed sync logs until the device is reachable.
- A manual `Sync Now` button should push all pending card changes again.

## Practical Setup Steps For Site

1. Use Dahua ConfigTool on the local network to confirm both ASI1201E-D devices are visible.
2. Assign or reserve static IP addresses:
   - `1st Floor Door`: `192.168.0.11`
   - `2nd Floor Door`: `192.168.0.12`
3. Confirm local admin credentials for both devices.
4. Confirm whether the device firmware exposes HTTP/CGI access-control endpoints.
5. If HTTP/CGI card management is not available, install a local SDK bridge service.
6. Test one card:
   - Add card from Laravel
   - Confirm card exists in Dahua device
   - Tap card at door
   - Disable membership in Laravel
   - Confirm card no longer grants access
7. Only after successful single-card testing, enable scheduled sync.

## Manual Sync

Admin should have a button:

```text
Settings > Access Controller > Sync Now
```

## Scheduled Sync

Run background job:

```text
Every 30 minutes:
- Find cards with pending sync
- Push to controller
- Record success/failure
```

## Acceptance Criteria

- Active member card can be synced to controller.
- Expired member card can be disabled.
- Manual sync logs result.
- Failed sync is recorded.
- Controller status is visible on dashboard.
