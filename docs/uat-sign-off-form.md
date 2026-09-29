# Gorilla Mutantz Gym MACS UAT Sign-Off Form

## Project

Membership and Access Control System (MACS)

## Site

Gorilla Mutantz Gym Sdn Bhd

## UAT Window

| Item | Details |
| --- | --- |
| UAT Date | |
| Front Desk Machine | |
| Local URL | |
| PostgreSQL Server | |
| 1st Floor Door IP | `192.168.100.11` |
| 2nd Floor Door IP | `192.168.100.12` |
| Tested By | |
| Witnessed By | |

## Acceptance Criteria

The system is accepted when critical daily workflows can be completed on the actual front-desk machine and door access devices without internet dependency.

## Test Checklist

| No. | Module | Test Scenario | Expected Result | Pass / Fail | Remarks |
| ---: | --- | --- | --- | --- | --- |
| 1 | Login | Administrator logs in with valid credentials | Dashboard opens successfully | | |
| 2 | Login | Invalid password is entered | Login is rejected and audit record is created | | |
| 3 | Login | User logs out | Session ends and returns to login page | | |
| 4 | Users | Administrator creates staff user | New user can login with assigned role | | |
| 5 | Members | Staff registers a new member | Member number is generated and profile is saved | | |
| 6 | Members | Staff edits member details | Updated details appear on profile and list | | |
| 7 | Members | Staff captures or uploads member photo | Photo appears on member profile | | |
| 8 | Memberships | Staff assigns membership | Membership status and expiry date are correct | | |
| 9 | Memberships | Staff renews membership before expiry | New end date follows renewal rule | | |
| 10 | Memberships | Expired membership list is checked | Expired members appear correctly | | |
| 11 | RFID | Staff assigns card number to active member | Card is linked to member profile | | |
| 12 | Access Control | Door access sync is triggered | Pending access syncs are processed | | |
| 13 | Access Control | Active member card is tested on 1st Floor Door | Door allows valid active card | | |
| 14 | Access Control | Active member card is tested on 2nd Floor Door | Door allows valid active card | | |
| 15 | Access Control | Expired or suspended member card is tested | Door rejects invalid card | | |
| 16 | Access Control | Door event history is viewed | Device events appear for selected date | | |
| 17 | POS | Product sale is completed | Receipt number, payment, and total are correct | | |
| 18 | POS | Membership sale is completed | Sales record and membership record are created | | |
| 19 | POS | PT package sale is completed for active member | PT balance is created and tracked | | |
| 20 | POS | Walk-in sale is completed | Sale is recorded under membership sales | | |
| 21 | Sales History | Staff searches sales history | Matching receipts are shown | | |
| 22 | Daily Sales Report | Report is generated for selected date | Summary totals match transactions | | |
| 23 | Daily Sales Report | XLSX export is downloaded | Excel file opens and contains daily summary | | |
| 24 | Products | Product price is updated | New sales use new price and old sales remain unchanged | | |
| 25 | Backup | Manual backup is created | Backup status is completed and downloadable | | |
| 26 | Backup | Restore confirmation guard is tested | Restore requires `RESTORE` and current password | | |
| 27 | Audit Trail | Audit log is reviewed | Login, member, sale, backup, and settings actions are visible | | |
| 28 | Settings | Door IP settings are updated | Status reflects configured device values | | |
| 29 | Scheduler | Schedule list is checked | Access sync, expiry sync, heartbeat, and backup schedules are present | | |
| 30 | Offline Use | Internet connection is unavailable | Local app, database, and door sync continue on LAN | | |

## Known Exceptions

| Item | Exception | Accepted By | Target Resolution |
| --- | --- | --- | --- |
| | | | |

## Sign-Off

| Role | Name | Signature | Date |
| --- | --- | --- | --- |
| Client Representative | | | |
| Gym Operations Representative | | | |
| System Administrator | | | |
| Developer / Implementer | | | |

## Final Decision

| Decision | Tick |
| --- | --- |
| Accepted for live use | |
| Accepted with exceptions listed above | |
| Not accepted, retest required | |
