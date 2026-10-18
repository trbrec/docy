'use strict';
const fs=require('node:fs'),assert=require('node:assert/strict'),{spawnSync}=require('node:child_process');
const workflow=fs.readFileSync('.github/workflows/deploy.yml','utf8');
const block=workflow.match(/              if \[ "\$\{snapshot_ready\}"[^\n]+; then\n[\s\S]*?              fi/)[0];
assert(block.includes('"${theme_install_started}" -eq 1'));
const check=workflow.indexOf("symphonic-metadata-install-guard.php' check");
const started=workflow.indexOf('          theme_install_started=1');
const install=workflow.indexOf('"rsync -a --checksum');
assert(check>0&&started>check&&install>started,'Guard must succeed before marking the theme installation started.');
if(process.platform==='win32'){
 console.log('PASS rollback guard ordering; actual Bash rollback branches require Linux CI');
}else{
 // Execute the exact existing theme rollback block with an in-process mock of ssh.
 // No full trap, SSH process, home-directory operation or real installer is invoked.
 const script=`set -euo pipefail\nssh(){ calls=$((calls+1)); }\ncalls=0\nssh_opts=()\nremote=synthetic\ncandidate=/synthetic\nGITHUB_SHA=${'a'.repeat(40)}\nsnapshot_ready=1\ntheme_install_started=0\n${block}\n[ "$calls" -eq 0 ]\ntheme_install_started=1\n${block}\n[ "$calls" -eq 1 ]\nsnapshot_ready=0\n${block}\n[ "$calls" -eq 1 ]\n`;
 const result=spawnSync('bash',['-c',script],{encoding:'utf8'});
 assert.equal(result.status,0,result.stdout+result.stderr);
 console.log('PASS guard failure leaves theme untouched; partial installation retains existing rollback');
}
