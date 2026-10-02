<?php
/** Read-only discovery of residual configuration, no writes or sends. */
if(PHP_SAPI!=='cli')exit;
ini_set('display_errors','0');ob_start();set_exception_handler(static function(){while(ob_get_level())ob_end_clean();fwrite(STDERR,"Residual discovery failed.\n");exit(1);});
require '/home/customer/www/crm.trbrec.com/public_html/app/Core.php';
\TrbCrm\Env::load('/home/customer/www/crm.trbrec.com/public_html/.env');
require '/home/customer/www/crm.trbrec.com/public_html/app/OnboardingContractCatalog.php';
$db=\TrbCrm\Database::connection();$cases=[];
foreach($db->query('SELECT p.id,p.state,p.snapshot,p.selected_plan,p.expires_at,c.status,c.sent_at,c.metadata FROM onboarding_practices p JOIN contracts c ON c.id=p.contract_id WHERE p.signed_at IS NULL AND p.cancelled_at IS NULL')->fetchAll() as $p){
$s=json_decode($p['snapshot'],true);$m=\TrbCrm\OnboardingContractCatalog::model($s['template_key']);$plan=json_decode($p['selected_plan']??'null',true);$email=strtolower($s['email']??'');$meta=json_decode($p['metadata']??'{}',true);
$label=strtoupper(implode(' ',[$s['contract_number']??'',$s['artist_name']??'',json_encode($meta)]));
$qa=str_contains($label,'QA-')||str_contains($label,'NONVALIDO')||str_contains($label,'COLLAUDO')||in_array($email,['a.tognassi@gmail.com','spotify4@trbrec.com'],true);
$counts=[];foreach(['onboarding_events','onboarding_payments','onboarding_signatures'] as $table){$q=$db->prepare("SELECT COUNT(*) FROM ".$table." WHERE practice_id=?");$q->execute([$p['id']]);$counts[$table]=(int)$q->fetchColumn();}
$cases[]=['case'=>substr(hash('sha256',$p['id']),0,8),'qa'=>$qa,'group'=>$s['group_code']??null,'state'=>$p['state'],'contract_status'=>$p['status'],'sent'=>(bool)$p['sent_at'],'expires_at'=>$p['expires_at'],'source_current'=>hash_equals($m['source_sha256'],$s['source_sha256']),'snapshot_keys'=>array_keys($s),'plan_kind'=>$plan['kind']??null,'current_plan_amounts'=>array_map(static fn($x)=>$x['amounts_cents'],$m['plans']),'selected_amounts'=>$plan['amounts_cents']??null,'counts'=>$counts];
}
$out=['cases'=>$cases,'sites'=>[]];
foreach(['artist','store','main'] as $site){
$host=$site==='main'?'trbrec.com':$site.'.trbrec.com';
$code='$_SERVER["HTTP_HOST"]='.var_export($host,true).';$_SERVER["REQUEST_URI"]="/";$_SERVER["HTTPS"]="on";define("WP_USE_THEMES",false);define("DISABLE_WP_CRON",true);ob_start();require '.var_export('/home/customer/www/'.$host.'/public_html/wp-load.php',true).';'.
'$id=(int)get_option("wp_page_for_privacy_policy");$p=$id?get_post($id):null;$r=["privacy_url"=>get_privacy_policy_url(),"configured_page"=>$p?["id"=>$p->ID,"status"=>$p->post_status,"content"=>$p->post_content]:null,"other_privacy_pages"=>[]];foreach(get_posts(["post_type"=>"page","post_status"=>["publish","draft"],"s"=>"privacy","numberposts"=>10]) as $p){$r["other_privacy_pages"][]=["id"=>$p->ID,"slug"=>$p->post_name,"status"=>$p->post_status,"content"=>$p->post_content];}if(function_exists("WC")){$r["bank_accounts"]=array_map(static function($a){$iban=preg_replace("/\\s+/","",strtoupper($a["iban"]??""));return ["holder"=>$a["account_name"]??"","iban_last4"=>substr($iban,-4),"matches_hype"=>$iban==="IT84W03268223000EM002707943","bank_name"=>$a["bank_name"]??""] ;},(array)get_option("woocommerce_bacs_accounts",[]));}while(ob_get_level())ob_end_clean();echo json_encode($r);';
$lines=[];exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code).' 2>/dev/null',$lines,$exit);$out['sites'][$site]=$exit===0?json_decode(implode("\n",$lines),true):null;
}
while(ob_get_level())ob_end_clean();echo json_encode($out,JSON_UNESCAPED_SLASHES)."\n";
