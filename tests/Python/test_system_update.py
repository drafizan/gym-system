import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]


class SystemUpdateTest(unittest.TestCase):
    def test_update_commands_and_failure_stop(self):
        php = shutil.which('php')
        if not php:
            self.skipTest('PHP CLI unavailable')
        for fail in ('', 'build'):
            with self.subTest(fail=fail), tempfile.TemporaryDirectory() as directory:
                root = Path(directory)
                (root / 'scripts').mkdir()
                (root / 'storage/app').mkdir(parents=True)
                (root / 'vendor').symlink_to(ROOT / 'vendor', target_is_directory=True)
                shutil.copy(ROOT / 'scripts/deploy.php', root / 'scripts/deploy.php')
                (root / 'artisan').write_text('<?php file_put_contents(getenv("UPDATE_TEST_LOG"), "artisan ".implode(" ", array_slice($argv, 1))."\\n", FILE_APPEND);')
                bin_dir = root / 'bin'
                bin_dir.mkdir()
                for command in ('git', 'composer', 'npm'):
                    script = bin_dir / command
                    script.write_text('#!/bin/sh\n'
                                      'echo "' + command + ' $*" >> "$UPDATE_TEST_LOG"\n'
                                      + ('if [ "$1" = "branch" ]; then echo main; fi\n' if command == 'git' else '')
                                      + ('if [ "$UPDATE_TEST_FAIL" = "build" ] && [ "$1" = "run" ]; then exit 1; fi\n' if command == 'npm' else ''))
                    script.chmod(0o755)
                log = root / 'commands.log'
                env = dict(os.environ, PATH=str(bin_dir) + os.pathsep + os.environ['PATH'],
                           UPDATE_TEST_LOG=str(log), UPDATE_TEST_FAIL=fail)
                result = subprocess.run([php, str(root / 'scripts/deploy.php')], env=env,
                                        capture_output=True, text=True, timeout=30)
                commands = log.read_text()
                self.assertIn('git pull --ff-only origin main', commands)
                self.assertIn('composer install --no-dev', commands)
                if fail:
                    self.assertNotEqual(result.returncode, 0)
                    self.assertNotIn('artisan migrate', commands)
                else:
                    self.assertEqual(result.returncode, 0, result.stderr)
                    self.assertIn('artisan migrate --force', commands)
                    self.assertIn('artisan config:cache', commands)
                    self.assertIn('artisan queue:restart', commands)
                    self.assertIn('completed successfully', result.stdout)


if __name__ == '__main__':
    unittest.main()
