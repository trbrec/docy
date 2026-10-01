<?php
namespace TrbCrm;
require_once __DIR__.'/fixtures-onboarding-entry-mail.php';
final class Env { public static function get($key,$default=''){return $default;} }
final class Security { public static function requireUser():array{throw new \LogicException('Background sends must not require a browser session');} }
require_once __DIR__.'/../integrations/onboarding/crm/OnboardingContractWorkflow.php';
require_once __DIR__.'/../integrations/onboarding/crm/OnboardingWorkflowInstaller.php';
function queue_check($ok,$message){if(!$ok)throw new \RuntimeException($message);}
$original="enum('pending','blocked','sent','failed','skipped')";
$column=['Type'=>$original,'Null'=>'NO','Default'=>'pending'];
$definition=OnboardingWorkflowInstaller::queueStatusDefinition($column);
queue_check($definition==="enum('pending','blocked','sent','failed','skipped','sending') NOT NULL DEFAULT 'pending'",'Existing states and ordinal positions are retained');
$column['Type']="enum('pending','blocked','sent','failed','skipped','sending')";
queue_check(OnboardingWorkflowInstaller::queueStatusDefinition($column)===null,'Status migration is idempotent');
foreach([
    ['Type'=>"enum('queued','sent','failed')",'Null'=>'NO','Default'=>'queued'],
    ['Type'=>"enum('pending','sent','failed')",'Null'=>'YES','Default'=>'pending'],
    ['Type'=>'varchar(4)','Null'=>'NO','Default'=>'pending'],
] as $unsupported){
    try{OnboardingWorkflowInstaller::queueStatusDefinition($unsupported);throw new \LogicException('Unexpected schema was modified');}catch(\RuntimeException $expected){}
}
$db=new \PDO('sqlite::memory:');$db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE,\PDO::FETCH_ASSOC);
$db->exec('CREATE TABLE users(id INTEGER,role TEXT,email TEXT,is_active INTEGER); CREATE TABLE outbound_batches(id INTEGER,created_by INTEGER,action_type TEXT,status TEXT,confirmed_at TEXT); CREATE TABLE outbound_batch_items(id INTEGER,batch_id INTEGER,submission_id INTEGER,recipient TEXT,status TEXT)');
$db->exec("INSERT INTO users VALUES(1,'admin','andrea.tognassi@trbrec.com',1); INSERT INTO outbound_batches VALUES(1,1,'contract_send','processing','2026-10-01'); INSERT INTO outbound_batch_items VALUES(1,1,123,'qa@example.invalid','sending')");
$s=['id'=>123,'email'=>'qa@example.invalid'];
queue_check(OnboardingContractWorkflow::authorizedSender($db,$s,1)['id']===1,'Confirmed background send resolves the real owner without a browser session');
$cases=[
    ["UPDATE outbound_batch_items SET status=''", "UPDATE outbound_batch_items SET status='sending'"],
    ["UPDATE outbound_batch_items SET recipient='other@example.invalid'", "UPDATE outbound_batch_items SET recipient='qa@example.invalid'"],
    ["UPDATE outbound_batches SET confirmed_at=NULL", "UPDATE outbound_batches SET confirmed_at='2026-10-01'"],
    ["UPDATE outbound_batches SET status='completed'", "UPDATE outbound_batches SET status='processing'"],
    ["UPDATE outbound_batches SET action_type='other'", "UPDATE outbound_batches SET action_type='contract_send'"],
    ["UPDATE users SET is_active=0", "UPDATE users SET is_active=1"],
    ["UPDATE users SET role='editor'", "UPDATE users SET role='admin'"],
    ["UPDATE users SET email='other@example.invalid'", "UPDATE users SET email='andrea.tognassi@trbrec.com'"],
];
foreach($cases as [$change,$restore]){
    $db->exec($change);
    try{OnboardingContractWorkflow::authorizedSender($db,$s,1);throw new \LogicException('Unconfirmed or unauthorized send accepted');}catch(\RuntimeException $expected){}
    $db->exec($restore);
}
try{OnboardingContractWorkflow::authorizedSender($db,$s,2);throw new \LogicException('A different operator inherited the owner approval');}catch(\RuntimeException $expected){}
echo "Queue status compatibility and confirmed owner authorization verified.\n";
$dsn=getenv('TRB_QUEUE_TEST_DSN');
if($dsn){
    $mysql=new \PDO($dsn,'root','queue-fixture',[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);
    $mysql->exec("CREATE TABLE outbound_batch_items (id INTEGER PRIMARY KEY, status ".$original." NOT NULL DEFAULT 'pending')");
    $mysql->exec("INSERT INTO outbound_batch_items VALUES(1,'pending'),(2,'blocked'),(3,'sent'),(4,'failed'),(5,'skipped')");
    OnboardingWorkflowInstaller::ensureQueueStatus($mysql);
    $mysql->exec("UPDATE outbound_batch_items SET status='sending' WHERE id=1");
    queue_check($mysql->query('SELECT status FROM outbound_batch_items WHERE id=1')->fetchColumn()==='sending','MySQL retains the sending state instead of the empty enum value');
    queue_check($mysql->query('SELECT GROUP_CONCAT(status ORDER BY id) FROM outbound_batch_items WHERE id>1')->fetchColumn()==='blocked,sent,failed,skipped','Historical statuses survive the migration');
    OnboardingWorkflowInstaller::ensureQueueStatus($mysql);
    $mysql->exec('DROP TABLE outbound_batch_items');
    echo "Real MySQL queue migration and sending-state persistence verified.\n";
}
