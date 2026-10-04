#!/usr/bin/env python3
"""Start the Dahua bridge as a detached process on Windows, Linux, or macOS."""

from __future__ import annotations

import argparse
import os
import subprocess
import sys


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--python", required=True)
    parser.add_argument("--script", required=True)
    parser.add_argument("--log", required=True)
    parser.add_argument("--working-directory", required=True)
    parser.add_argument("--host", required=True)
    parser.add_argument("--port", required=True)
    parser.add_argument("--sdk", default="")
    args = parser.parse_args()

    env = os.environ.copy()
    env["DAHUA_BRIDGE_HOST"] = args.host
    env["DAHUA_BRIDGE_PORT"] = args.port
    if args.sdk:
        env["DAHUA_NETSDK_PATH"] = args.sdk

    os.makedirs(os.path.dirname(args.log), exist_ok=True)
    process_options = {"start_new_session": True} if os.name != "nt" else {
        "creationflags": (
            subprocess.DETACHED_PROCESS
            | subprocess.CREATE_NEW_PROCESS_GROUP
            | subprocess.CREATE_NO_WINDOW
        )
    }

    with open(args.log, "ab", buffering=0) as log:
        process = subprocess.Popen(
            [args.python, "-u", args.script],
            cwd=args.working_directory,
            env=env,
            stdin=subprocess.DEVNULL,
            stdout=log,
            stderr=subprocess.STDOUT,
            **process_options,
            close_fds=True,
        )

    print(process.pid)
    return 0


if __name__ == "__main__":
    sys.exit(main())
