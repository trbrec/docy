<?php
declare(strict_types=1);
require_once __DIR__.'/../integrations/giulia/GiuliaReport.php';
use TrbCrm\GiuliaReport;
function reportCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$before=['at'=>1791733438,'used'=>24106,'total'=>55006,'remaining'=>30900,'reset'=>1794330000];
$after=$before;$after['at']+=86400;$after['used']+=420;$after['remaining']-=420;
reportCheck(GiuliaReport::consumption($before,$after)===420,'Daily usage comes from comparable used counters');
$after['total']+=25000;$after['remaining']+=25000;
reportCheck(GiuliaReport::consumption($before,$after)===420,'Top-ups never hide daily consumption');
$after['reset']+=2592000;
reportCheck(GiuliaReport::consumption($before,$after)===null,'A billing reset does not produce fabricated usage');
$after=$before;$after['used']-=1;
reportCheck(GiuliaReport::consumption($before,$after)===null,'A decreasing counter is not a negative consumption');
reportCheck(GiuliaReport::consumption(null,$before)===null,'First observation does not claim a full day');
foreach([
 ['2026-10-11 17:00:00 UTC',true],['2026-10-11 18:00:00 UTC',false],
 ['2026-12-11 17:00:00 UTC',false],['2026-12-11 18:00:00 UTC',true],
 ['2026-10-25 18:00:00 UTC',true],['2026-03-29 17:00:00 UTC',true],
] as [$when,$due])reportCheck(GiuliaReport::due(strtotime($when))===$due,'Italian daylight saving time is respected');
$mock=static fn($key)=>['character_count'=>24106,'character_limit'=>55006,'next_character_count_reset_unix'=>1794330000,'tier'=>'starter'];
$snapshot=GiuliaReport::snapshot(str_repeat('a',40),$mock);
reportCheck($snapshot['remaining']===30900,'Subscription response yields the verified remaining balance');
$requests=0;
try{GiuliaReport::snapshot("key\r\ninjection",function()use(&$requests){$requests++;return [];});throw new LogicException('Invalid key accepted');}
catch(RuntimeException $e){reportCheck($requests===0,'Invalid credentials never reach the transport');}
try{GiuliaReport::snapshot(str_repeat('a',40),static fn($key)=>['character_count'=>9,'character_limit'=>3,'next_character_count_reset_unix'=>1]);throw new LogicException('Inconsistent balance accepted');}
catch(RuntimeException $e){reportCheck(str_contains($e->getMessage(),'riconciliare'),'Inconsistent counters require reconciliation');}
$message=GiuliaReport::message($before,$snapshot,['items'=>[['question'=>'<script>alert(1)</script>','reason'=>'technical_problem']]],1791647038,1791733438);
reportCheck(!str_contains($message['html'],'<script>'),'Question text is escaped in owner emails');
reportCheck(str_contains($message['text'],'non le fatture'),'Credits are not misrepresented as paid invoices');
reportCheck(str_contains($message['text'],'non analizza nuovamente tutte le chat'),'Report coverage is explicit');
reportCheck(GiuliaReport::RECIPIENT==='info@trbrec.com','Delivery is restricted to the authorized recipient');
echo "GIULIA_REPORT_TESTS_OK\n";
