"""Prevent production diagnostic commands from publishing raw output in Actions."""
from pathlib import Path
text=Path('.github/workflows/deploy.yml').read_text()
commands=[line for line in text.splitlines() if 'ssh ' in line and 'php ' in line and '/tools/' in line]
assert commands,'Production diagnostic command inventory unexpectedly empty'
wrapper=Path('tools/onboarding-deploy-status.php').read_text()
for command in commands:
    if '>/dev/null 2>&1' in command:
        continue
    if '/tools/onboarding-readiness.php' in command:
        assert '2>/dev/null' in command
        readiness=Path('tools/onboarding-readiness.php').read_text()
        for required in ('ob_start();','register_shutdown_function','ob_end_clean();',"$clean[$key]=($checks[$key]??false)===true;",'json_encode($clean)'):
            assert required in readiness,'Fixed boolean readiness output guard missing'
        assert 'getMessage' not in readiness
        continue
    if '/tools/onboarding-deploy-status.php' in command:
        assert '2>/dev/null' in command
        for required in ('ob_start();', 'register_shutdown_function', 'ob_end_clean();', '$allowed=', "in_array($deployStage??'',", "'stage'=>$stage"):
            assert required in wrapper,'Sanitized wrapper output guard missing'
        assert "$last['message']" not in wrapper and 'getMessage' not in wrapper
        continue
    if '/tools/export-site-preview.php' in command:
        assert '2>/dev/null' in command and '> "/tmp/trb-public-preview/' in command
        export=Path('tools/export-site-preview.php').read_text()
        for required in ('ob_start();','is_user_logged_in()',"getElementsByTagName('script')","getElementsByTagName('input')","trb-artist-private","trb-release-private"):
            assert required in export,'Anonymous snapshot output guard missing'
        continue
    if '/tools/export-artist-routes.php' in command:
        assert '2>/dev/null' in command and 'artist_routes=$(' in command
        export=Path('tools/export-artist-routes.php').read_text()
        for required in ('ob_start();','ob_end_clean();',"~^/artisti/[a-z0-9-]+/$~D",'artist_page_url($a)'):
            assert required in export,'Public managed artist route guard missing'
        assert 'getMessage' not in export
        continue
    assert '>/dev/null 2>&1' in command,'Production diagnostics must not publish raw stdout or stderr'
print('Production diagnostic output isolation passed.')

helper=Path('tools/deploy-crm-inline-review.php').read_text()
for forbidden in ('Database::connection', '$db->', 'app/bootstrap.php', 'ReflectionMethod'):
    assert forbidden not in helper, 'Review deployment must not load production data'
print('Review deployment contains no production application or database access.')
