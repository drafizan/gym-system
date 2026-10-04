#!/usr/bin/env python3
"""Keep the local Dahua bridge running on Windows."""

from __future__ import annotations

import ctypes
import datetime as dt
import os
import subprocess
import sys
import time
import urllib.request


APP_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PYTHON = os.path.join(APP_ROOT, "runtime", "python-3.13.15", "python.exe")
LAUNCHER = os.path.join(APP_ROOT, "scripts", "start_dahua_bridge.py")
BRIDGE = os.path.join(APP_ROOT, "scripts", "dahua_sdk_bridge.py")
SDK = os.path.join(APP_ROOT, "runtime", "dahua", "dhnetsdk.dll")
LOG = os.path.join(APP_ROOT, "storage", "logs", "dahua-bridge.log")
WATCHDOG_LOG = os.path.join(APP_ROOT, "storage", "logs", "dahua-bridge-watchdog.log")
PID_FILE = os.path.join(APP_ROOT, "storage", "app", "dahua-bridge-watchdog.pid")
HEALTH_URL = "http://127.0.0.1:8787/health"


def write_log(message: str) -> None:
    os.makedirs(os.path.dirname(WATCHDOG_LOG), exist_ok=True)
    timestamp = dt.datetime.now().astimezone().isoformat(timespec="seconds")
    with open(WATCHDOG_LOG, "a", encoding="utf-8") as stream:
        stream.write(f"[{timestamp}] {message}\n")


def bridge_is_healthy() -> bool:
    try:
        with urllib.request.urlopen(HEALTH_URL, timeout=2) as response:
            return response.status == 200
    except Exception:
        return False


def start_bridge() -> None:
    command = [
        PYTHON,
        LAUNCHER,
        "--python", PYTHON,
        "--script", BRIDGE,
        "--log", LOG,
        "--working-directory", APP_ROOT,
        "--host", "127.0.0.1",
        "--port", "8787",
        "--sdk", SDK,
    ]
    result = subprocess.run(
        command,
        cwd=APP_ROOT,
        capture_output=True,
        text=True,
        timeout=15,
        creationflags=subprocess.CREATE_NO_WINDOW,
    )
    detail = result.stdout.strip() or result.stderr.strip() or f"exit {result.returncode}"
    write_log(f"Bridge launch requested: {detail}")


def claim_single_instance() -> bool:
    mutex = ctypes.windll.kernel32.CreateMutexW(None, False, "Local\\GymDahuaBridgeWatchdog")
    if not mutex:
        return False
    return ctypes.windll.kernel32.GetLastError() != 183


def main() -> int:
    if os.name != "nt" or not claim_single_instance():
        return 0

    os.makedirs(os.path.dirname(PID_FILE), exist_ok=True)
    with open(PID_FILE, "w", encoding="ascii") as stream:
        stream.write(str(os.getpid()))

    write_log("Watchdog started.")
    while True:
        if not bridge_is_healthy():
            try:
                start_bridge()
            except Exception as exception:
                write_log(f"Bridge launch failed: {exception}")
            time.sleep(5)
        else:
            time.sleep(30)


if __name__ == "__main__":
    sys.exit(main())
