<?php
declare(strict_types=1);
require_once __DIR__.'/../integrations/onboarding/crm/OnboardingMail.php';
use TrbCrm\OnboardingMail;
function mail_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$message=OnboardingMail::accessCode('Mario <script>alert(1)</script>','012345');
mail_check(str_contains($message['html'],'font-size:36px')&&str_contains($message['html'],'012345'),'Prominent continuous code, including leading zero');
mail_check(str_contains($message['html'],'&lt;script&gt;')&&!str_contains($message['html'],'<script>'),'Recipient name escaped');
mail_check(str_contains($message['text'],"\n\n012345\n\n")&&!str_contains($message['subject'],'012345'),'Plain-text code isolated and never in subject');
mail_check(str_contains($message['html'],'10 minuti')&&str_contains($message['text'],'10 minuti'),'Same expiry in both alternatives');
$boundary='=_trbonboarding_'.str_repeat('b',24);$id='<trbcrm.onboarding.'.str_repeat('a',32).'@crm.trbrec.com>';
$raw=OnboardingMail::mime('artist@example.invalid',$message['subject'],$message['text'],$message['html'],$id,$boundary);
mail_check(str_contains($raw,'From: TRB rec - Portale Artisti <andrea.tognassi@trbrec.com>')&&str_contains($raw,'Reply-To: andrea.tognassi@trbrec.com'),'Service identity and existing reply mailbox');
$parts=explode('--'.$boundary,$raw);
foreach([1=>'text',2=>'html'] as $index=>$key){[$headers,$body]=explode("\r\n\r\n",$parts[$index],2);mail_check(str_contains($headers,'Content-Transfer-Encoding: base64'),'MIME transfer encoding');mail_check(base64_decode(preg_replace('/\s/','',$body),true)===$message[$key],'Exact Unicode MIME round trip');}
mail_check(!str_contains($message['html'],'A&amp;R')&&!str_contains($message['html'],'confidential')&&!str_contains($message['html'],'P. IVA'),'Transactional message has no personal A&R signature');
foreach(["artist@example.invalid\r\nBcc: stolen@example.invalid",'bad-address'] as $email){try{OnboardingMail::mime($email,'test','test',null,$id,$boundary);throw new LogicException('Expected rejection');}catch(InvalidArgumentException $e){}}
try{OnboardingMail::accessCode('Mario','12345');throw new LogicException('Expected invalid code rejection');}catch(InvalidArgumentException $e){}
$notice=OnboardingMail::mime('artist@example.invalid','Quota scaduta',"Quota <49>\nSeconda riga",null,$id,$boundary);
mail_check(str_contains(base64_decode(preg_replace('/\s/','',explode("\r\n\r\n",explode('--'.$boundary,$notice)[2],2)[1])),'Quota &lt;49&gt;<br>'),'Generic notices safely use the service layout');
$welcome=OnboardingMail::welcome('Andrea <img>', 'https://artist.trbrec.com/adesione/#invite='.str_repeat('a',64));
mail_check(str_contains($welcome['html'],'&lt;img&gt;')&&!str_contains($welcome['html'],'<img>'),'Welcome recipient escaped');
mail_check(substr_count($welcome['html'],'<a href=')===1&&str_contains($welcome['html'],'ACCEDI AL PORTALE ARTISTI'),'One clear primary welcome action');
mail_check(str_contains($welcome['html'],'scegli la tua password')&&str_contains($welcome['text'],'codice'),'First access explains verification and password setup');
mail_check(str_contains($welcome['html'],'Portale Artisti')&&str_contains($welcome['html'],'max-width:560px'),'Welcome uses the same branded responsive layout');
foreach(['http://artist.trbrec.com/adesione/#invite='.str_repeat('a',64),'https://evil.invalid/'] as $url){try{OnboardingMail::welcome('Mario',$url);throw new LogicException('Expected invalid welcome URL');}catch(InvalidArgumentException $e){}}
if(in_array('--preview',$argv,true)){file_put_contents(__DIR__.'/../mail-preview.html',OnboardingMail::accessCode('Andrea','012345')['html']);file_put_contents(__DIR__.'/../welcome-preview.html',$welcome['html']);}
echo "Service email branding, escaping, visible code and multipart MIME verified.\n";
