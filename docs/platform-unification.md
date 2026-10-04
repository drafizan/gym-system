# Shared Windows and Linux version

Both platforms use the same Laravel application and source branch. Machine-specific
settings belong in `.env`; there is no need for a separate Windows application branch.

## Branch comparison

Compared `main` at `ba474826a55705a5cd6cfb1ac8d0ecd3e2ea0d59` with `windows` at
`b09e0b50cbc1f27a2f07186ac8b4a445416521f0`. The Windows branch descends directly
from main. Its 21 changed/added files contain:

- A Windows bridge launcher with PID recording.
- A bundled Windows Python 3.13.15 runtime and Dahua `dhnetsdk.dll`.
- Windows SDK loading and an optional Windows watchdog.
- Python/SDK configuration and a version change to `v1.01`.

There are no separate controller, view, database, or business-feature changes.
All Windows additions are retained in the shared version. The launcher now also
supports Linux/macOS, resolves Python on PATH, handles paths containing spaces,
and passes the configured SDK path on every platform. SDK discovery selects
platform-compatible libraries and retains the existing macOS locations.

## Platform configuration

Follow [deployment-notes.md](deployment-notes.md) for Laravel, PostgreSQL, assets,
permissions, backups, and scheduler installation.

| Setting | Windows default | Linux default |
| --- | --- | --- |
| Python | `runtime/python-3.13.15/python.exe` | `python3` on PATH |
| Bundled SDK location | `runtime/dahua/dhnetsdk.dll` | `runtime/dahua/libdhnetsdk.so` |
| SDK loader | Windows DLL loader | POSIX shared library loader |
| Background process | Detached, hidden process | New process session |

Windows native binaries are retained from the Windows branch. Linux requires a
Dahua Linux NetSDK matching the machine architecture; a Windows DLL cannot be
used on Linux. Install the SDK dependencies alongside the library and configure
the service's library search path (`LD_LIBRARY_PATH` on Linux) if required by the
SDK distribution. Existing macOS SmartPSS Lite/ConfigTool discovery is retained.

Optional Linux overrides:

```dotenv
GYM_DAHUA_BRIDGE_PYTHON="/usr/bin/python3"
GYM_DAHUA_NETSDK_PATH="/opt/dahua/lib/libdhnetsdk.so"
```

Optional Windows overrides (use forward slashes inside quoted dotenv paths):

```dotenv
GYM_DAHUA_BRIDGE_PYTHON="C:/Python313/python.exe"
GYM_DAHUA_NETSDK_PATH="C:/Dahua/NetSDK/dhnetsdk.dll"
GYM_BACKUP_PATH="C:/GymBackups"
GYM_PG_DUMP_BINARY="C:/Program Files/PostgreSQL/17/bin/pg_dump.exe"
GYM_PSQL_BINARY="C:/Program Files/PostgreSQL/17/bin/psql.exe"
```

Set these on both platforms:

```dotenv
GYM_DAHUA_BRIDGE_URL=http://127.0.0.1:8787/dahua
GYM_DAHUA_BRIDGE_HEALTH_URL=http://127.0.0.1:8787/health
```

After changing `.env`, refresh cached configuration:

```text
php artisan config:clear
php artisan config:cache
php artisan access:bridge-heartbeat
php artisan access:bridge-heartbeat --status
```

The minute-by-minute Laravel scheduler starts the bridge when needed. The
retained Windows watchdog is optional and uses its original fixed defaults
(bundled runtime/SDK, `127.0.0.1:8787`); use the Laravel scheduler when overriding
bridge paths or ports. Configure cron on Linux or Task Scheduler on Windows.

The optional dashboard deployment script remains Bash-based (`scripts/deploy.sh`)
and disabled by default. Windows deployments enabling it require Bash and the
same command-line tools available to the web service account.

## Validation

```text
python3 -m unittest discover -s tests/Python -v
php artisan test
npm run build
```

On Windows, the first command can use `runtime/python-3.13.15/python.exe` instead
of `python3`. Launcher tests cover both platform process options and SDK candidate
selection; POSIX also performs a real detached launch with paths containing spaces.
Native SDK loading and door-controller commands require verification on each target
machine with the appropriate SDK and reachable controllers.
