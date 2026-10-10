"""Verify empty PHP maps, multipart bytes and destination confinement."""
import http.cookiejar
import io
import unittest
from email.message import Message
from unittest.mock import patch

from importlib.util import spec_from_file_location, module_from_spec
from pathlib import Path
spec = spec_from_file_location('relay', Path(__file__).with_name('qa-http-relay.py'))
relay = module_from_spec(spec)
spec.loader.exec_module(relay)


class Response(io.BytesIO):
    code = 404
    headers = Message()


class RelayTests(unittest.TestCase):
    def test_empty_php_map_and_body(self):
        with patch.object(relay.urllib.request, 'build_opener') as build:
            build.return_value.open.return_value = Response(b'Fixture only')
            result = relay.exchange({'url': 'https://artist.trbrec.com/wp-login.php', 'headers': [], 'fields': None}, http.cookiejar.CookieJar())
        self.assertEqual(result['status'], 404)
        self.assertEqual(relay.base64.b64decode(result['body_base64']), b'Fixture only')

    def test_real_multipart_framing(self):
        with patch.object(relay.urllib.request, 'build_opener') as build:
            build.return_value.open.return_value = Response(b'')
            relay.exchange({'url': 'https://artist.trbrec.com/trb-audit-http-' + 'a' * 24 + '/index.php', 'headers': {'X-TRB-QA-Route': '/wp-admin/admin-post.php'}, 'fields': {'name': 'Tunisi', 'photo[0]': {'filename': 'fixture.png', 'mime': 'image/png', 'base64': 'AAECAw=='}}}, http.cookiejar.CookieJar())
            request = build.return_value.open.call_args.args[0]
        self.assertIn(b'name="photo[0]"; filename="fixture.png"', request.data)
        self.assertIn(b'\x00\x01\x02\x03', request.data)
        self.assertTrue(request.data.endswith(b'--\r\n'))

    def test_outside_destination_never_connects(self):
        with patch.object(relay.urllib.request, 'build_opener') as build:
            with self.assertRaises(ValueError):
                relay.exchange({'url': 'https://example.com/private'}, http.cookiejar.CookieJar())
        build.assert_not_called()


if __name__ == '__main__':
    unittest.main()
