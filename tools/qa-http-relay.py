"""Run isolated HTTP checks from the runner; never persist request/session data."""
import base64
import http.cookiejar
import io
import json
import re
import secrets
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def exchange(packet, jar):
    url = packet['url']
    if not re.fullmatch(r'https://artist\.trbrec\.com/(?:trb-audit-http-[a-f0-9]{24}/index\.php|wp-login\.php)', url):
        raise ValueError('Unexpected isolated HTTP destination')
    headers = {'User-Agent': 'Mozilla/5.0 (compatible; TRB-Audit/1.0)'}
    for name, value in packet.get('headers', {}).items():
        if name not in ('X-TRB-QA-Token', 'X-TRB-QA-Route') or '\r' in value or '\n' in value:
            raise ValueError('Unexpected fixture header')
        headers[name] = value
    fields = packet.get('fields')
    data = None
    if fields is not None:
        boundary = 'trbqa' + secrets.token_hex(16)
        body = io.BytesIO()
        for name, value in fields.items():
            if not re.fullmatch(r'[a-zA-Z0-9_\[\]-]+', name):
                raise ValueError('Invalid form field')
            body.write(('--' + boundary + '\r\n').encode())
            if isinstance(value, dict):
                filename = value['filename']
                if not re.fullmatch(r'[a-zA-Z0-9_.-]+', filename) or value['mime'] not in ('image/png', 'text/plain'):
                    raise ValueError('Unexpected synthetic upload')
                raw = base64.b64decode(value['base64'], validate=True)
                if len(raw) > 65536:
                    raise ValueError('Synthetic upload exceeds fixture limit')
                body.write((f'Content-Disposition: form-data; name="{name}"; filename="{filename}"\r\nContent-Type: {value["mime"]}\r\n\r\n').encode())
                body.write(raw)
            else:
                body.write((f'Content-Disposition: form-data; name="{name}"\r\n\r\n' + str(value)).encode())
            body.write(b'\r\n')
        body.write(('--' + boundary + '--\r\n').encode())
        data = body.getvalue()
        headers['Content-Type'] = 'multipart/form-data; boundary=' + boundary
    active_jar = jar if packet.get('authenticated') else http.cookiejar.CookieJar()
    opener = urllib.request.build_opener(NoRedirect(), urllib.request.HTTPCookieProcessor(active_jar))
    request = urllib.request.Request(url, data=data, headers=headers)
    try:
        response = opener.open(request, timeout=35)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        body = response.read(4 * 1024 * 1024 + 1)
        if len(body) > 4 * 1024 * 1024:
            raise ValueError('Unexpected response size')
        cookie_lines = ['# Netscape HTTP Cookie File']
        for cookie in active_jar:
            cookie_lines.append('\t'.join((cookie.domain, 'TRUE' if cookie.domain_initial_dot else 'FALSE', cookie.path, 'TRUE' if cookie.secure else 'FALSE', str(cookie.expires or 0), cookie.name, cookie.value)))
        return {'status': response.code, 'headers': str(response.headers), 'body_base64': base64.b64encode(body).decode(), 'cookies': '\n'.join(cookie_lines) + '\n'}


def main():
    if len(sys.argv) < 3 or sys.argv[1] != '--':
        raise SystemExit('Usage: qa-http-relay.py -- ssh ...')
    process = subprocess.Popen(sys.argv[2:], stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, text=True)
    jar = http.cookiejar.CookieJar()
    final = None
    try:
        for line in process.stdout:
            packet = json.loads(line)
            if packet.get('relay') == 'isolated-http-v1':
                answer = exchange(packet, jar)
                process.stdin.write(json.dumps(answer) + '\n')
                process.stdin.flush()
            else:
                final = packet
        status = process.wait(timeout=45)
        if not isinstance(final, dict) or 'completed' not in final:
            raise RuntimeError('Isolated HTTP result unavailable')
        print(json.dumps(final))
        return status
    except Exception:
        process.kill()
        process.wait()
        # Never include request payloads, cookies or response bodies in diagnostics.
        print(json.dumps({'completed': False, 'fatal': {'stage': 'external_http_relay'}}))
        return 1


if __name__ == '__main__':
    sys.exit(main())
