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
    assert '>/dev/null 2>&1' in command,'Production diagnostics must not publish raw stdout or stderr'
print('Production diagnostic output isolation passed.')

helper=Path('tools/deploy-crm-inline-review.php').read_text()
for forbidden in ('Database::connection', '$db->', 'app/bootstrap.php', 'ReflectionMethod'):
    assert forbidden not in helper, 'Review deployment must not load production data'
print('Review deployment contains no production application or database access.')
