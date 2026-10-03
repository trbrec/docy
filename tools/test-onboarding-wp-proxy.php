<?php
declare(strict_types=1);

/** Exercise the native WP boundary with synthetic transport only; never a live request. */
define('ABSPATH', __DIR__.'/');
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);

final class WP_Error
{
    public function __construct(private string $code, private string $message, private mixed $data = null) {}
    public function get_error_code(): string { return $this->code; }
    public function get_error_message(): string { return $this->message; }
    public function get_error_data(): mixed { return $this->data; }
}

$GLOBALS['native_test'] = ['hooks'=>[], 'filters'=>[], 'scheduled'=>[], 'scheduled_calls'=>[], 'spawned'=>0, 'transients'=>[], 'calls'=>[], 'remote'=>null];
function add_action($hook, $callback, $priority=10, $accepted=1) { $GLOBALS['native_test']['hooks'][$hook][]=$callback; }
function add_filter($hook, $callback, $priority=10, $accepted=1) { $GLOBALS['native_test']['filters'][$hook][]=$callback; }
function get_option($key, $default=false) { return $key==='trb_candidate_onboarding_enabled' ? true : $default; }
function home_url($path='') { return 'https://artist.trbrec.com'.$path; }
function wp_salt($scope) { return 'synthetic-unit-test-browser-salt'; }
function wp_json_encode($value, $flags=0) { return json_encode($value, $flags|JSON_THROW_ON_ERROR); }
function trb_crm_connector_settings() { return ['secret'=>str_repeat('s', 40)]; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_post($url, $args) {
    $GLOBALS['native_test']['calls'][]=[$url,$args];
    $response=$GLOBALS['native_test']['remote'];
    return is_callable($response) ? $response($url,$args) : $response;
}
function wp_remote_retrieve_body($response) { return $response['body'] ?? ''; }
function wp_remote_retrieve_response_code($response) { return $response['response']['code'] ?? 0; }
function native_event_key($hook, $args=[]) { return $hook.':'.json_encode($args,JSON_THROW_ON_ERROR); }
function wp_next_scheduled($hook, $args=[]) { return $GLOBALS['native_test']['scheduled'][native_event_key($hook,$args)]['time'] ?? false; }
function wp_schedule_single_event($time, $hook, $args=[]) {
    $event=['hook'=>$hook,'time'=>$time,'args'=>$args,'recurring'=>false];
    $GLOBALS['native_test']['scheduled'][native_event_key($hook,$args)]=$event;
    $GLOBALS['native_test']['scheduled_calls'][]=$event;
    return true;
}
function wp_schedule_event($time, $recurrence, $hook, $args=[]) {
    $event=['hook'=>$hook,'time'=>$time,'args'=>$args,'recurring'=>$recurrence];
    $GLOBALS['native_test']['scheduled'][native_event_key($hook,$args)]=$event;
    $GLOBALS['native_test']['scheduled_calls'][]=$event;
    return true;
}
function wp_unschedule_event($time, $hook, $args=[]) {
    $key=native_event_key($hook,$args);
    if(($GLOBALS['native_test']['scheduled'][$key]['time']??null)===$time)unset($GLOBALS['native_test']['scheduled'][$key]);
    return true;
}
function spawn_cron($time=0) { $GLOBALS['native_test']['spawned']++; return true; }
function get_transient($key) { return $GLOBALS['native_test']['transients'][$key] ?? false; }
function set_transient($key, $value, $expiry=0) { $GLOBALS['native_test']['transients'][$key]=$value; return true; }
function delete_transient($key) { unset($GLOBALS['native_test']['transients'][$key]); return true; }
function add_option($key, $value, $deprecated='', $autoload=null) { return true; }
function is_user_logged_in() { return false; }

final class NativeTestRequest
{
    public function __construct(private array $input, private array $headers=[]) {}
    public function get_json_params(): array { return $this->input; }
    public function get_header(string $name): string { return $this->headers[strtolower($name)] ?? ''; }
    public function get_body(): string { return json_encode($this->input, JSON_THROW_ON_ERROR); }
}
function native_check(bool $condition, string $message): void { if(!$condition) throw new RuntimeException($message); }
function native_response(array|string $body, int $status=200): array { return ['response'=>['code'=>$status],'body'=>is_array($body)?json_encode($body,JSON_THROW_ON_ERROR):$body]; }
function native_fire(string $hook, array $args=[]): void {
    native_check(isset($GLOBALS['native_test']['hooks'][$hook]),$hook.' is registered');
    unset($GLOBALS['native_test']['scheduled'][native_event_key($hook,$args)]);
    foreach($GLOBALS['native_test']['hooks'][$hook] as $callback) $callback(...$args);
}
function native_recoverable($error, string $context): void {
    native_check($error instanceof WP_Error, $context.' is an error');
    native_check(($error->get_error_data()['status'] ?? 0)>=500, $context.' is a temporary server failure');
    native_check(($error->get_error_data()['recoverable'] ?? false)===true, $context.' retains a recovery path');
    native_check(!preg_match('/Failed to fetch|cURL|api\.openai|stack|trace|upstream-secret/i',$error->get_error_message()), $context.' never exposes raw provider or technical messages');
}

require __DIR__.'/../inc/trb-candidate-onboarding.php';

// A syntactically correct HTTP failure must not become an invalid-document decision.
$GLOBALS['native_test']['remote']=new WP_Error('http_request_failed','cURL timeout at upstream-secret');
$error=trb_onboarding_crm(['action'=>'identity','session'=>str_repeat('a',64)]);
native_recoverable($error,'lost CRM reply');
$sent=$GLOBALS['native_test']['calls'][0];$args=$sent[1];$headers=$args['headers'];
native_check($sent[0]==='https://crm.trbrec.com/webhooks/artist-portal/onboarding'&&$args['redirection']===0,'candidate JSON stays on the configured authenticated endpoint');
native_check(hash_equals('sha256='.hash_hmac('sha256','onboarding-crm-v1|'.$headers['X-TRB-Timestamp'].'|'.$headers['X-TRB-Nonce'].'|'.$args['body'],str_repeat('s',40)),$headers['X-TRB-Signature']),'recovery requests keep purpose-bound HMAC');

foreach([native_response('<html>upstream-secret stack trace</html>',502),native_response('not-json'),native_response(['error'=>'upstream-secret stack trace api.openai.com'],500),native_response(['error'=>['malformed'=>'upstream-secret']],409)] as $remote){
    $GLOBALS['native_test']['remote']=$remote;
    native_recoverable(trb_onboarding_crm(['action'=>'view','session'=>str_repeat('a',64)]),'unconfirmed remote response');
}

// Permission failure cannot accidentally issue OCR or any provider request.
$_COOKIE['__Host-trb_onboarding']=str_repeat('b',64);
$csrf=trb_onboarding_csrf($_COOKIE['__Host-trb_onboarding']);
$headers=['content-type'=>'application/json','origin'=>'https://artist.trbrec.com','x-trb-onboarding-csrf'=>$csrf];
native_check(trb_onboarding_public_permission(new NativeTestRequest(['action'=>'identity'],$headers))===true,'valid same-origin CSRF enters candidate boundary');
$crossOrigin=$headers;$crossOrigin['origin']='https://untrusted.example.invalid';
native_check(is_wp_error(trb_onboarding_public_permission(new NativeTestRequest(['action'=>'identity'],$crossOrigin))),'cross-origin document verification remains blocked');
$before=count($GLOBALS['native_test']['calls']);
$GLOBALS['native_test']['transients']=[];
$error=trb_onboarding_public(new NativeTestRequest(['action'=>'identity']));
native_check($error instanceof WP_Error&&($error->get_error_data()['status']??0)===401,'expired browser session requires email confirmation');
native_check(count($GLOBALS['native_test']['calls'])===$before,'missing authenticated session never starts a job');

// A CRM session may expire before the browser cookie. The response must direct re-authentication.
$GLOBALS['native_test']['transients'][trb_onboarding_browser_key()]=['session'=>str_repeat('a',64),'invite'=>str_repeat('c',64)];
$GLOBALS['native_test']['remote']=native_response(['error'=>'Conferma nuovamente il tuo indirizzo email. I dati e i documenti salvati restano disponibili.','code'=>'session_expired'],401);
$error=trb_onboarding_public(new NativeTestRequest(['action'=>'view']));
native_check($error instanceof WP_Error&&($error->get_error_data()['status']??0)===401,'expired CRM session preserves HTTP authentication semantics');
native_check($error->get_error_code()==='onboarding_session','expired CRM session is distinguishable from a document rejection');
native_check(!isset($GLOBALS['native_test']['transients'][trb_onboarding_browser_key()]['session']),'expired CRM authentication is removed instead of retried forever');
native_check(($GLOBALS['native_test']['transients'][trb_onboarding_browser_key()]['invite']??'')===str_repeat('c',64),'personal invitation survives re-authentication');
$GLOBALS['native_test']['transients'][trb_onboarding_browser_key()]=['session'=>str_repeat('a',64),'invite'=>str_repeat('c',64)];

// Invalid documents are a completed business decision with correction instructions,
// not an infrastructure failure and not an unbounded pending cron retry.
$GLOBALS['native_test']['remote']=native_response(['identity'=>['status'=>'rejected','reason'=>'document_unreadable'],'practice'=>['id'=>str_repeat('e',32),'state'=>'identity_review','identity_verification'=>['status'=>'rejected','reason'=>'document_unreadable','result_status'=>'review']]]);
$scheduledCount=count($GLOBALS['native_test']['scheduled_calls']);
$result=trb_onboarding_public(new NativeTestRequest(['action'=>'identity']));
native_check(!is_wp_error($result)&&($result['practice']['state']??'')==='identity_review','invalid screenshots retain the durable document correction result');
native_check(count($GLOBALS['native_test']['scheduled_calls'])===$scheduledCount,'completed document rejection does not start infrastructure retry');

// The public submit only queues work. A duplicate click queues no second cron job.
$id=str_repeat('d',32);
$GLOBALS['native_test']['remote']=native_response(['identity'=>['status'=>'queued'],'practice'=>['id'=>$id,'state'=>'invited','identity_verification'=>['status'=>'queued','retry_after_seconds'=>1]]]);
$before=count($GLOBALS['native_test']['calls']);
$result=trb_onboarding_public(new NativeTestRequest(['action'=>'identity']));
native_check(($result['identity']['status']??'')==='queued','queued verification is returned immediately without pretending documents were approved');
$sent=$GLOBALS['native_test']['calls'][$before];
native_check(json_decode($sent[1]['body'],true)['action']==='identity','public candidate submits a job without calling the private worker');
native_check($sent[1]['timeout']<=55,'public submit is bounded independently of reader runtime');
$eventKey=native_event_key('trb_onboarding_identity_worker',[$id]);
native_check(isset($GLOBALS['native_test']['scheduled'][$eventKey]),'queued document work has a durable native cron wakeup');
$event=$GLOBALS['native_test']['scheduled'][$eventKey];
native_check($event['time']>time()-1&&$event['time']<=time()+65&&$event['recurring']===false,'first queued check is scheduled promptly as a single event');
$scheduledCount=count($GLOBALS['native_test']['scheduled_calls']);
trb_onboarding_public(new NativeTestRequest(['action'=>'identity']));
native_check(count($GLOBALS['native_test']['scheduled_calls'])===$scheduledCount,'repeat submit cannot create parallel wakeups for the same practice');
native_check($GLOBALS['native_test']['spawned']>0,'queued submit starts cron nonblocking rather than waiting for page traffic');

// Even requesters with a valid browser session cannot operate trusted worker routes.
$before=count($GLOBALS['native_test']['calls']);
$error=trb_onboarding_public(new NativeTestRequest(['action'=>'identity_worker','practice_id'=>$id]));
native_check(is_wp_error($error)&&count($GLOBALS['native_test']['calls'])===$before,'private identity worker stays outside the public action allowlist');

// A transient outage retries the same durable job; a completed check does not retry.
$GLOBALS['native_test']['remote']=native_response(['pending'=>true,'practice_id'=>$id,'retry_after_seconds'=>30,'identity_verification'=>['status'=>'retry_wait']]);
native_fire('trb_onboarding_identity_worker',[$id]);
$call=$GLOBALS['native_test']['calls'][array_key_last($GLOBALS['native_test']['calls'])];$payload=json_decode($call[1]['body'],true);
native_check($payload['action']==='identity_worker'&&($payload['practice_id']??'')===$id,'native worker resumes only the bound durable practice');
native_check($call[1]['timeout']>55,'only the trusted cron callback may outlive the short public request');
$event=$GLOBALS['native_test']['scheduled'][$eventKey]??null;
native_check($event&&$event['time']>=time()+25&&$event['time']<=time()+35,'provider retry uses its durable backoff instead of immediate loops');
$GLOBALS['native_test']['remote']=native_response(['pending'=>false,'practice_id'=>$id,'identity_verification'=>['status'=>'complete']]);
native_fire('trb_onboarding_identity_worker',[$id]);
native_check(!isset($GLOBALS['native_test']['scheduled'][$eventKey]),'completed or rejected verification removes the retry wakeup');

// A lost worker reply must leave a recovery wakeup; it never cancels the job.
$GLOBALS['native_test']['remote']=new WP_Error('http_request_failed','cURL transport timeout');
native_fire('trb_onboarding_identity_worker',[$id]);
$event=$GLOBALS['native_test']['scheduled'][$eventKey]??null;
native_check($event&&$event['time']>=time()+55&&$event['time']<=time()+65,'lost worker reply retries later without an immediate loop');
// Corrected documents replace an old retry revision. Their new queue must not
// inherit a minute of stale backoff from the previous worker result.
$GLOBALS['native_test']['remote']=native_response(['identity'=>['status'=>'queued'],'practice'=>['id'=>$id,'state'=>'invited','identity_verification'=>['status'=>'queued','retry_after_seconds'=>1]]]);
trb_onboarding_public(new NativeTestRequest(['action'=>'identity']));
$event=$GLOBALS['native_test']['scheduled'][$eventKey]??null;
native_check($event&&$event['time']>=time()-1&&$event['time']<=time()+1,'new queued input moves an older retry wakeup forward to due-now');
$samePractice=array_filter($GLOBALS['native_test']['scheduled'],static fn($e)=>$e['hook']==='trb_onboarding_identity_worker'&&$e['args']===[$id]);
native_check(count($samePractice)===1,'earlier recovery wakeup replaces rather than duplicates the existing cron event');
$GLOBALS['native_test']['remote']=native_response(['pending'=>false,'practice_id'=>$id,'identity_verification'=>['status'=>'rejected']]);
native_fire('trb_onboarding_identity_worker',[$id]);
native_check(!isset($GLOBALS['native_test']['scheduled'][$eventKey]),'document rejection terminates infrastructure wakeups');
$before=count($GLOBALS['native_test']['calls']);
native_fire('trb_onboarding_identity_worker',['invalid-worker-id']);
native_check(count($GLOBALS['native_test']['calls'])===$before,'malformed scheduled IDs cannot trigger a broad worker operation');

// Invalid remote IDs must never become a broad or foreign cron operation.
$GLOBALS['native_test']['remote']=native_response(['id'=>'not-a-valid-practice','identity_verification'=>['status'=>'queued','retry_after_seconds'=>1]]);
$scheduledCount=count($GLOBALS['native_test']['scheduled_calls']);
trb_onboarding_public(new NativeTestRequest(['action'=>'view']));
native_check(count($GLOBALS['native_test']['scheduled_calls'])===$scheduledCount,'invalid remote practice IDs are not scheduled');

// Recovery after tab closure must also have a periodic sweep without identity approval shortcuts.
$_SERVER['REQUEST_URI']='/unit-fixture';
foreach($GLOBALS['native_test']['hooks']['init'] as $callback) $callback();
$sweepKey=native_event_key('trb_onboarding_identity_sweep');
native_check(($GLOBALS['native_test']['scheduled'][$sweepKey]['recurring']??'')==='trb_onboarding_one_minute','one-minute native sweep recovers queued work after the browser is closed');
$schedules=[];foreach($GLOBALS['native_test']['filters']['cron_schedules'] as $callback)$schedules=$callback($schedules);
native_check(($schedules['trb_onboarding_one_minute']['interval']??0)===60,'native recovery recurrence is actually registered at one minute');
$GLOBALS['native_test']['remote']=native_response(['pending'=>false]);
native_fire('trb_onboarding_identity_sweep');
$call=$GLOBALS['native_test']['calls'][array_key_last($GLOBALS['native_test']['calls'])];$payload=json_decode($call[1]['body'],true);
native_check($payload['action']==='identity_worker'&&!isset($payload['practice_id']),'periodic recovery takes one native durable job without browser claims');

echo "Native candidate proxy: recoverable transport, expired sessions, scoped cron wakeups, duplicate prevention and closed-tab recovery verified.\n";
