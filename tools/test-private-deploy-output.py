"""Prevent production diagnostic commands from publishing raw output in Actions."""
from pathlib import Path
text=Path('.github/workflows/deploy.yml').read_text()
commands=[line for line in text.splitlines() if 'ssh ' in line and 'php ' in line and '/tools/' in line]
assert commands,'Production diagnostic command inventory unexpectedly empty'
for command in commands:
    assert '>/dev/null 2>&1' in command,'Production diagnostics must not publish raw stdout or stderr'
print('Production diagnostic output isolation passed.')
