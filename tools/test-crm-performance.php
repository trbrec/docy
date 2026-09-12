<?php
require __DIR__.'/crm-performance-patch.php';
function check($condition){if(!$condition)throw new RuntimeException('Performance regression failed');}
$names=['dashboardData','systemReadiness','submissions','artists','legacyDemoArchive','activeArtists','activeArtistWorkspace','inbox','inboundCandidates','messageTemplates','contracts','accounting','accountingImports','followups','materials','releases','releaseCaseSheet','statistics'];
$source='<?php class Fixture {';
foreach($names as $name)$source.='public function '.$name.'(){ $user=Security::requireUser(); work(); }';
$source.='public function login(){ mutateSession(); } public function update(){ Security::requireUser(); mutateSession(); }}';
$patched=trb_crm_performance_patch($source,'controller');
check(substr_count($patched,'session_write_close()')===count($names));
check(strpos($patched,'public function update(){ Security::requireUser(); mutateSession(); }')!==false);
check(trb_crm_performance_patch($patched,'controller')===$patched);
$repository='<?php class Fixture {function releases(){ $portalSync=$this->reconcileArtistPortalSync(false); } function cron(){ $this->reconcileArtistPortalSync(false); }}';
$updated=trb_crm_performance_patch($repository,'repository');
check(strpos($updated,'function cron(){ $this->reconcileArtistPortalSync(false); }')!==false);
check(strpos($updated,'scheduled_worker')!==false);
check(trb_crm_performance_patch($updated,'repository')===$updated);
$failed=false;try{trb_crm_performance_patch('<?php class Changed {}','controller');}catch(RuntimeException $e){$failed=true;}check($failed);
// Execute a patched read handler with a real session and dummy slow-work callback.
class Security {static function requireUser(){return $_SESSION['user'];}}
function work(){check(session_status()!==PHP_SESSION_ACTIVE);check($_SESSION['user']['id']===1);check($_SESSION['csrf']==='fixture-token');}
function mutateSession(){}
eval(substr($patched,5));
session_start();$_SESSION=['user'=>['id'=>1],'csrf'=>'fixture-token'];(new Fixture)->releases();
echo "Read handlers release session before work; mutations and scheduled sync preserved; guards and idempotency passed.\n";
