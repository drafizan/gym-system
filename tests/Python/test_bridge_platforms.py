"""Platform launch and SDK selection checks without door hardware."""
import ctypes
import importlib.util
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch, Mock

ROOT = Path(__file__).resolve().parents[2]


def load_script(name):
    spec = importlib.util.spec_from_file_location(name, ROOT / 'scripts' / f'{name}.py')
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


class BridgePlatformsTest(unittest.TestCase):
    def test_launcher_options_for_each_platform(self):
        launcher = load_script('start_dahua_bridge')
        with tempfile.TemporaryDirectory(prefix='bridge test ') as directory:
            args = ['launcher', '--python', sys.executable, '--script', 'bridge script.py',
                    '--log', directory + '/bridge.log', '--working-directory', directory,
                    '--host', '127.0.0.1', '--port', '9898', '--sdk', directory + '/custom SDK']
            for platform in ('posix', 'nt'):
                with self.subTest(platform=platform), patch.object(sys, 'argv', args), \
                     patch.object(launcher.os, 'name', platform), \
                     patch.object(subprocess, 'DETACHED_PROCESS', 8, create=True), \
                     patch.object(subprocess, 'CREATE_NEW_PROCESS_GROUP', 512, create=True), \
                     patch.object(subprocess, 'CREATE_NO_WINDOW', 134217728, create=True), \
                     patch.object(subprocess, 'Popen', return_value=Mock(pid=123)) as spawn, \
                     patch('builtins.print'):
                    self.assertEqual(launcher.main(), 0)
                    call = spawn.call_args
                    self.assertEqual(call.args[0], [sys.executable, '-u', 'bridge script.py'])
                    self.assertEqual(call.kwargs['env']['DAHUA_NETSDK_PATH'], directory + '/custom SDK')
                    self.assertEqual(call.kwargs['env']['DAHUA_BRIDGE_PORT'], '9898')
                    if platform == 'nt':
                        self.assertEqual(call.kwargs['creationflags'], 8 | 512 | 134217728)
                        self.assertNotIn('start_new_session', call.kwargs)
                    else:
                        self.assertTrue(call.kwargs['start_new_session'])
                        self.assertNotIn('creationflags', call.kwargs)

    def test_real_detached_launch_with_spaces_and_sdk_environment(self):
        if os.name == 'nt':
            self.skipTest('POSIX integration check')
        with tempfile.TemporaryDirectory(prefix='bridge test ') as directory:
            script = Path(directory) / 'fake bridge.py'
            script.write_text('import os\nprint(os.environ["DAHUA_NETSDK_PATH"], flush=True)\n')
            log = Path(directory) / 'bridge.log'
            result = subprocess.run([
                sys.executable, str(ROOT / 'scripts/start_dahua_bridge.py'),
                '--python', sys.executable, '--script', str(script), '--log', str(log),
                '--working-directory', directory, '--host', '127.0.0.1', '--port', '9898',
                '--sdk', 'SDK path with spaces',
            ], capture_output=True, text=True, timeout=10, check=True)
            self.assertTrue(result.stdout.strip().isdigit())
            import time
            for _ in range(100):
                if log.exists() and 'SDK path with spaces' in log.read_text():
                    break
                time.sleep(0.02)
            self.assertIn('SDK path with spaces', log.read_text())

    def test_port_binding_failure_does_not_load_native_sdk(self):
        bridge = load_script('dahua_sdk_bridge')
        with patch.object(bridge, 'ThreadingHTTPServer', side_effect=PermissionError('bind denied')), \
             patch.object(bridge, 'DahuaSdk') as sdk:
            with self.assertRaises(PermissionError):
                bridge.main()
            sdk.assert_not_called()

    def test_server_failure_cleans_up_native_sdk(self):
        bridge = load_script('dahua_sdk_bridge')
        server = Mock()
        server.serve_forever.side_effect = RuntimeError('server stopped')
        with patch.object(bridge, 'ThreadingHTTPServer', return_value=server), \
             patch.object(bridge, 'DahuaSdk') as sdk, patch('builtins.print'):
            with self.assertRaises(RuntimeError):
                bridge.main()
            server.server_close.assert_called_once()
            sdk.return_value.lib.CLIENT_Cleanup.assert_called_once()

    def test_sdk_candidates_match_platform_and_custom_path_has_priority(self):
        for platform, filename in [('nt', 'dhnetsdk.dll'), ('posix', 'libdhnetsdk.so')]:
            with self.subTest(platform=platform), patch('os.name', platform), \
                 patch.dict(os.environ, {'DAHUA_NETSDK_PATH': '/custom/sdk'}):
                bridge = load_script('dahua_sdk_bridge')
                self.assertEqual(bridge.SDK_PATHS[0], '/custom/sdk')
                self.assertEqual(bridge.SDK_PATHS[1], os.path.join(str(ROOT), 'runtime', 'dahua', filename))
                if platform == 'posix':
                    self.assertFalse(any(path.endswith('.dll') for path in bridge.SDK_PATHS))


if __name__ == '__main__':
    unittest.main()
