"""Qualify theme overrides using pinned official plugins, a private fixture and a disposable DB."""
import io
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import time
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('TRB_QA_PHP', 'php')
TMP = Path(tempfile.gettempdir()).resolve()
STAMP = str(time.time_ns() // 1_000_000)
FIXTURE = TMP / ('trb-wp-mysql-qa-' + STAMP)
DATABASE = 'trb_wp_qa_' + STAMP


def query(sql):
    # This is the dedicated local/CI MySQL service, never a production connection.
    code = '$db=new mysqli("127.0.0.1","root","qa_ci","",3307);$db->query(' + json.dumps(sql) + ');'
    subprocess.run([PHP, '-r', code], check=True, capture_output=True, text=True, timeout=30)


def extract(archive_env, url, target):
    archive = os.environ.get(archive_env)
    if archive:
        source = Path(archive).read_bytes()
    else:
        with urllib.request.urlopen(url, timeout=60) as response:
            if not response.geturl().startswith('https://') or response.geturl().split('/')[2] not in ('wordpress.org', 'downloads.wordpress.org'):
                raise RuntimeError('Unexpected official archive redirect')
            source = response.read(100_000_001)
    if len(source) > 100_000_000:
        raise RuntimeError('Official archive exceeds the fixture size limit')
    with zipfile.ZipFile(io.BytesIO(source)) as package:
        for entry in package.infolist():
            destination = (target / entry.filename).resolve()
            if not destination.is_relative_to(target) or (entry.external_attr >> 16) & 0o170000 == 0o120000:
                raise RuntimeError('Unsafe official archive entry')
        package.extractall(target)


def main():
    database_created = False
    if FIXTURE.parent != TMP or FIXTURE.exists():
        raise RuntimeError('Unsafe or existing fixture directory')
    FIXTURE.mkdir(mode=0o700)
    try:
        extract('TRB_QA_WORDPRESS_ZIP', 'https://wordpress.org/wordpress-7.1.3.zip', FIXTURE)
        extract('TRB_QA_WOOCOMMERCE_ZIP', 'https://downloads.wordpress.org/plugin/woocommerce.11.2.1.zip', FIXTURE / 'wordpress/wp-content/plugins')
        query('CREATE DATABASE ' + DATABASE + ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
        database_created = True
        environment = dict(os.environ, TRB_WP_MYSQL_ROOT=str(FIXTURE / 'wordpress'), TRB_QA_MYSQL_DATABASE=DATABASE)
        for mode in ('install', 'verify'):
            subprocess.run([PHP, str(ROOT / 'tests/php/woocommerce-native.php'), mode], cwd=ROOT, env=environment, check=True, timeout=180)
            if mode == 'install':
                environment['TRB_QA_CONFIG_SHA256'] = hashlib.sha256((FIXTURE / 'wordpress/wp-config.php').read_bytes()).hexdigest()
    finally:
        try:
            if database_created:
                query('DROP DATABASE ' + DATABASE)
        finally:
            if FIXTURE.parent != TMP or FIXTURE.is_symlink() or not FIXTURE.name.removeprefix('trb-wp-mysql-qa-').isdigit():
                raise RuntimeError('Unsafe fixture cleanup path')
            shutil.rmtree(FIXTURE)
            print('Owned WooCommerce fixture directory removed.', flush=True)


if __name__ == '__main__':
    main()
