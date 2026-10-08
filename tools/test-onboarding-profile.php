<?php
/** Exercise the actual shared portal validators and candidate boundary without network. */
declare(strict_types=1);
class WP_Error{public function __construct(public string $code,public string $message,public array $data=[]){}public function get_error_message(){return $this->message;}}
function is_wp_error($value){return $value instanceof WP_Error;}
function get_template_directory(){return dirname(__DIR__);}
function remove_accents($value){return iconv('UTF-8','ASCII//TRANSLIT',$value);}
function wp_timezone(){return new DateTimeZone('Europe/Rome');}
function wp_date($format){return (new DateTimeImmutable('now',wp_timezone()))->format($format);}
function trb_onboarding_browser_key(){return 'qa-session';}
function get_transient($key){return $GLOBALS['browser']??[];}
function trb_onboarding_crm($payload){return $payload;}
function check_profile(bool $ok,string $message){if(!$ok)throw new RuntimeException($message);}
function load_function(string $source,string $name){if(!preg_match('/^function '.preg_quote($name,'/').'\s*\([^\n]*\)\s*\{.*?^\}/ms',$source,$match))throw new RuntimeException('Missing canonical function '.$name);eval($match[0]);}
$portal=file_get_contents(__DIR__.'/../inc/trb-artist-portal.php');
foreach(['trb_portal_lookup_postcode','trb_portal_territorial_archive','trb_portal_find_municipalities','trb_portal_find_municipality_exact','trb_portal_validate_mobile','trb_portal_validate_tax_code','trb_portal_validate_identity_document_number','trb_portal_validate_identity_document_expiry'] as $name)load_function($portal,$name);
$candidate=file_get_contents(__DIR__.'/../inc/trb-candidate-onboarding.php');
foreach(['trb_onboarding_schedule_identity_response','trb_onboarding_public'] as $name)load_function($candidate,$name);
class ProfileRequest{public function __construct(private array $data){}public function get_json_params(){return $this->data;}}
check_profile(is_wp_error(trb_onboarding_public(new ProfileRequest(['action'=>'postcode','postcode'=>'25038']))),'Lookups require a verified candidate session');
$GLOBALS['browser']=['session'=>str_repeat('a',64)];
$places=trb_onboarding_public(new ProfileRequest(['action'=>'postcode','postcode'=>'25038']));
check_profile(in_array(['city'=>'Rovato','province'=>'BS'],$places['places'],true)&&$places['country']==='Italia','Same portal CAP archive');
check_profile(is_wp_error(trb_onboarding_public(new ProfileRequest(['action'=>'postcode','postcode'=>'25abc038']))),'Malformed CAP rejected at server');
$municipalities=trb_onboarding_public(new ProfileRequest(['action'=>'municipalities','search'=>'Rovat']));check_profile(in_array(['city'=>'Rovato','province'=>'BS'],$municipalities['places'],true),'Same municipality archive');
$valid=['action'=>'details','billing'=>['country'=>'IT','street'=>'Via 25 Aprile','street_number'=>'11/A','address_1'=>'ignored','postcode'=>'25038','city'=>'Rovato','state'=>'XX','phone'=>'0039 333 0000000'],'tax_code'=>'rssmra90a01h501w','profile'=>['birth_date'=>'1990-01-01','birth_place'=>'Roma','birth_province'=>'RM','document_number'=>'ca 12345 ab','document_expiry'=>'2030-01-01'],'privacy_acknowledged'=>true];
$normalized=trb_onboarding_public(new ProfileRequest($valid));
check_profile(!is_wp_error($normalized)&&$normalized['billing']['state']==='BS'&&$normalized['billing']['address_1']==='Via 25 Aprile 11/A'&&$normalized['billing']['street_number']==='11/A','Server derives canonical province and preserves separate civic');
check_profile($normalized['profile']['document_number']==='CA12345AB'&&$normalized['tax_code']==='RSSMRA90A01H501W'&&$normalized['billing']['phone']==='+393330000000','Existing identity validators normalize values');
foreach([
 ['tax_code'=>'RSSMRA90A01H501A'],['tax_code'=>'RSSMRA90001H501W'],
 ['billing'=>array_replace($valid['billing'],['city'=>'Milano'])],
 ['billing'=>array_replace($valid['billing'],['street_number'=>''])],
 ['billing'=>array_replace($valid['billing'],['phone'=>'+391234'])],
 ['profile'=>array_replace($valid['profile'],['document_number'=>'123456789'])],
 ['profile'=>array_replace($valid['profile'],['document_expiry'=>'2020-01-01'])],
 ['profile'=>array_replace($valid['profile'],['birth_date'=>'1990-02-31'])],
 ['profile'=>array_replace($valid['profile'],['birth_place'=>'Comune inventato'])]
] as $change)check_profile(is_wp_error(trb_onboarding_public(new ProfileRequest(array_replace($valid,$change)))),'Invalid identity/address field rejected');
echo "Original portal validation and archives reused across the candidate boundary.\n";
