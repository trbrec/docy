<?php
declare(strict_types=1);
namespace TrbCrm;

/** Deterministic owner report: no model calls, purchases, or changes to ElevenLabs. */
final class GiuliaReport
{
    public const RECIPIENT = 'info@trbrec.com';
    public const TZ = 'Europe/Rome';
    public const TEST_CONVERSATIONS = [
        'conv_9901m4nqkhxnf41s1twj36fthk8g','conv_1701m4nr1q8mfe2rq9f0w4t3gbp6',
        'conv_8601m4nr5e7gf6wvckw356nhb2b1','conv_7601m4nr9dtkfa1brgme9qskhzb8',
        'conv_3401m4nrh7ypf8ds52ztrcg7t06g','conv_7701m4nrnpd9fmk9m1x3rttpjq1r',
        'conv_9201m4nrs2xzft2t940k193dpkpc'
    ];
    public static function directory(): string { return dirname(__DIR__,2).'/private'; }
    private static function configPath(): string { return self::directory().'/giulia-report-config.json'; }
    private static function statePath(): string { return self::directory().'/giulia-report-state.json'; }
    public static function read(string $path): array
    {
        if(!is_file($path))return [];
        $v=json_decode((string)file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
        if(!is_array($v))throw new \RuntimeException('Stato del report non valido');
        return $v;
    }
    public static function save(string $path,array $value): void
    {
        $json=json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $temp=$path.'.'.bin2hex(random_bytes(8)).'.tmp';
        $old=umask(0077);
        try{
            if(file_put_contents($temp,$json,LOCK_EX)!==strlen($json)||!chmod($temp,0600)||!rename($temp,$path))
                throw new \RuntimeException('Salvataggio del report non riuscito');
        }finally{umask($old);if(is_file($temp))unlink($temp);}
    }
    public static function due(int $time): bool
    {
        return (new \DateTimeImmutable('@'.$time))->setTimezone(new \DateTimeZone(self::TZ))->format('H')==='19';
    }
    public static function consumption(?array $before,array $after): ?int
    {
        if(!$before||!isset($before['used'],$before['reset'],$after['used'],$after['reset'])
            ||(int)$before['reset']!==(int)$after['reset']||(int)$after['used']<(int)$before['used'])return null;
        return (int)$after['used']-(int)$before['used'];
    }
    public static function snapshot(string $key,?callable $request=null): array
    {
        if(!preg_match('/^[A-Za-z0-9_-]{20,200}$/D',$key))throw new \RuntimeException('Chiave di lettura non configurata');
        $request??=static function(string $apiKey):array{
            $context=stream_context_create(['http'=>[
                'method'=>'GET','header'=>"xi-api-key: ".$apiKey."\r\nAccept: application/json\r\n",
                'timeout'=>25,'ignore_errors'=>true,'follow_location'=>0
            ]]);
            $body=@file_get_contents('https://api.elevenlabs.io/v1/user/subscription',false,$context,0,2097153);
            $status=$http_response_header[0]??'';
            if(!preg_match('~^HTTP/\S+ 200\b~',$status)||!is_string($body)||strlen($body)>2097152)
                throw new \RuntimeException('Saldo ElevenLabs non disponibile: verificare il collegamento');
            $v=json_decode($body,true,32,JSON_THROW_ON_ERROR);
            if(!is_array($v))throw new \RuntimeException('Risposta ElevenLabs non valida');
            return $v;
        };
        $v=$request($key);
        foreach(['character_count','character_limit','next_character_count_reset_unix'] as $field)
            if(!isset($v[$field])||filter_var($v[$field],FILTER_VALIDATE_INT)===false||(int)$v[$field]<0)
                throw new \RuntimeException('Contatori ElevenLabs non confrontabili');
        $used=(int)$v['character_count'];$limit=(int)$v['character_limit'];
        if($used>$limit)throw new \RuntimeException('Saldo ElevenLabs da riconciliare');
        return ['at'=>time(),'used'=>$used,'total'=>$limit,'remaining'=>$limit-$used,
            'reset'=>(int)$v['next_character_count_reset_unix'],'tier'=>(string)($v['tier']??'')];
    }
    public static function questions(\PDO $db,int $from,int $to): array
    {
        $q=$db->prepare('SELECT conversation_hash,question,reason,model_code,role_code FROM giulia_unanswered WHERE created_at >= ? AND created_at < ? ORDER BY id');
        $q->execute([gmdate('Y-m-d H:i:s',$from),gmdate('Y-m-d H:i:s',$to)]);
        $key=GiuliaSupport::config()['key'];
        $testHashes=array_map(static fn($id)=>hash_hmac('sha256','conversation|'.$id,$key),self::TEST_CONVERSATIONS);
        $items=[];$technical=0;
        foreach($q->fetchAll(\PDO::FETCH_ASSOC) as $row){
            if(in_array($row['conversation_hash'],$testHashes,true)||str_starts_with($row['question'],'[COLLAUDO TECNICO GIULIA 4.2]')){$technical++;continue;}
            $items[]=['question'=>GiuliaSupport::redact($row['question']),'reason'=>(string)$row['reason']];
        }
        return ['items'=>$items,'technical_excluded'=>$technical];
    }
    public static function message(?array $before,?array $after,array $questions,int $from,int $to,?string $problem=null,bool $activation=false): array
    {
        $zone=new \DateTimeZone(self::TZ);
        $format=static fn($n)=>(new \DateTimeImmutable('@'.$n))->setTimezone($zone)->format('d/m/Y H:i');
        $date=(new \DateTimeImmutable('@'.$to))->setTimezone($zone)->format('d/m/Y');
        $number=static fn($n)=>number_format($n,0,',','.');
        $used=$after?self::consumption($before,$after):null;
        $text=($activation?'Report di attivazione':'Report serale')." Giulia – TRB rec\n\n";
        $text.='Periodo osservato: '.$format($from).' → '.$format($to).".\n";
        $text.='Saldo crediti: '.($after?$number($after['remaining']):'non disponibile').".\n";
        $text.='Crediti consumati nel periodo: '.($used!==null?$number($used):'non determinabili con certezza: manca un riferimento confrontabile').".\n";
        if($after)$text.='Contatore utilizzati: '.$number($after['used']).' su '.$number($after['total']).".\n";
        if($problem!==null)$text.='Da verificare: '.$problem.".\n";
        $text.="\nRicariche automatiche: configurazione autorizzata di 5 USD per ricarica sotto 1 USD di saldo, tetto 10 USD al mese oltre al piano Starter da 6 USD. Eventuali imposte escluse.\n";
        $text.="Gli aumenti della dotazione sono crediti aggiunti o rettifiche e non riducono il consumo. Questo collegamento legge i contatori, non le fatture: nuovi addebiti e ricariche non sono certificati da una variazione del saldo. I pagamenti restano consultabili nella fatturazione ElevenLabs.\n";
        if($before&&$after&&$after['total']!==$before['total'])
            $text.='Variazione della dotazione nel periodo: '.sprintf('%+d',(int)$after['total']-(int)$before['total'])." crediti, da riconciliare con ricariche, rinnovi o rettifiche.\n";
        $items=$questions['items']??[];
        $text.="\nDomande irrisolte registrate nel CRM: ".count($items).".\n";
        if(!$items)$text.="Nessuna domanda irrisolta registrata nel periodo osservato.\n";
        foreach($items as $i=>$item)$text.=($i+1).'. '.$item['question'].' ['.($item['reason']??'da verificare')."]\n";
        if(!empty($questions['technical_excluded']))$text.='Record di collaudo esclusi: '.$questions['technical_excluded'].".\n";
        $text.="\nCopertura: saldo e contatori del workspace ElevenLabs; domande registrate tramite l'integrazione CRM. Il report non analizza nuovamente tutte le chat né certifica l'assenza di domande che non sono state registrate.\n";
        $text.="Generato automaticamente dal CRM. Nessuna chiamata a modelli AI o simulazione viene eseguita per preparare questo report.\n";
        $title=($activation?'Attivazione report email Giulia':'Giulia – report serale').' – '.$date;
        $html='<!doctype html><html lang="it"><meta charset="utf-8"><body style="font:16px Arial;color:#18252e;line-height:1.6"><h1 style="font-size:22px">'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').'</h1><div>'.nl2br(htmlspecialchars($text,ENT_QUOTES,'UTF-8'),false).'</div></body></html>';
        return ['subject'=>$title,'text'=>$text,'html'=>$html];
    }
    private static function send(array $message,string $deliveryKey): array
    {
        foreach(['OutboundMail','OnboardingMail'] as $class)
            if(!class_exists(__NAMESPACE__.'\\'.$class)&&is_file(__DIR__.'/'.$class.'.php'))require_once __DIR__.'/'.$class.'.php';
        if(!class_exists(OutboundMail::class)||!class_exists(OnboardingMail::class))
            throw new \RuntimeException('Invio email del CRM non disponibile');
        $id='<trbcrm.onboarding.'.substr(hash('sha256',$deliveryKey),0,32).'@crm.trbrec.com>';
        $boundary='=_trbonboarding_'.substr(hash('sha256','mime|'.$deliveryKey),0,24);
        $raw=OnboardingMail::mime(self::RECIPIENT,$message['subject'],$message['text'],$message['html'],$id,$boundary);
        $result=OutboundMail::candidateBridge(['action'=>'crm_candidate_mail_send','confirm'=>true,'operator_confirmed'=>true,
            'recipient'=>self::RECIPIENT,'message_id'=>$id,'raw_base64'=>base64_encode($raw),'mime_sha256'=>hash('sha256',$raw)]);
        if(($result['sent']??false)!==true||empty($result['gmail_message_id'])||empty($result['sent_copy']))
            throw new \RuntimeException('Invio email non confermato: verificare prima di ripetere');
        return ['sent'=>true,'gmail_message_id'=>(string)$result['gmail_message_id']];
    }
    public static function run(bool $activation=false,?string $newKey=null): array
    {
        $lock=fopen(self::directory().'/giulia-report.lock','c');
        if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))return ['status'=>'busy'];
        try{
            $now=time();$config=self::read(self::configPath());$state=self::read(self::statePath());
            if(!$activation&&(empty($config['enabled'])||!self::due($now)))return ['status'=>'not_due'];
            $key=$newKey??(string)($config['api_key']??'');$snapshot=null;$problem=null;
            try{$snapshot=self::snapshot($key);}catch(\Throwable $e){$problem=$e instanceof \RuntimeException?$e->getMessage():'Collegamento saldo non disponibile';if($activation)throw $e;}
            $day=(new \DateTimeImmutable('@'.$now))->setTimezone(new \DateTimeZone(self::TZ))->format('Y-m-d');
            $deliveryKey=($activation?'activation':'daily').'|'.$day;
            if(isset($state['deliveries'][$deliveryKey]))return ['status'=>$state['deliveries'][$deliveryKey]['status']];
            $before=$state['snapshot']??null;
            $from=$before?(int)$before['at']:$now-86400;
            if($activation)$from=$now;
            $questions=self::questions(Database::connection(),$from,$now);
            $message=self::message($before,$snapshot,$questions,$from,$now,$problem,$activation);
            $state['deliveries'][$deliveryKey]=['status'=>'sending','at'=>$now];
            self::save(self::statePath(),$state);
            $receipt=self::send($message,$deliveryKey);
            $state['deliveries'][$deliveryKey]=['status'=>'sent','at'=>$now]+$receipt;
            if($snapshot)$state['snapshot']=$snapshot;
            $state['last_delivery_at']=$now;$state['last_problem']=$problem;
            self::save(self::statePath(),$state);
            if($activation)self::save(self::configPath(),['enabled'=>true,'recipient'=>self::RECIPIENT,'api_key'=>$key,'configured_at'=>$now]);
            return ['status'=>'sent','remaining'=>$snapshot['remaining']??null,'used'=>$snapshot['used']??null,'total'=>$snapshot['total']??null];
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public static function dispatch(string $method): void
    {
        header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex, nofollow');header('Referrer-Policy: no-referrer');
        $owner=Security::requireUser();
        if(($owner['role']??'')!=='admin'){Response::json(['error'=>'forbidden'],403);return;}
        $notice='';
        try{
            if($method==='POST'){
                Security::verifyCsrf();
                if(($_POST['action']??'')!=='activate')throw new \RuntimeException('Operazione non disponibile');
                $result=self::run(true,trim((string)($_POST['api_key']??'')));
                $notice=$result['status']==='sent'?'Collegamento verificato. Email di attivazione inviata e report serale abilitato.':'Invio già riservato: verifica la consegna prima di ripetere.';
            }elseif($method!=='GET'){Response::json(['error'=>'method_not_allowed'],405);return;}
        }catch(\Throwable $e){$notice=$e instanceof \RuntimeException?$e->getMessage():'Collegamento non completato: verifica la configurazione';}
        $config=self::read(self::configPath());$state=self::read(self::statePath());$scheduler=self::read(self::directory().'/giulia-report-scheduler.json');
        $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Report serale Giulia</title><style>body{font:16px system-ui;line-height:1.6;max-width:820px;margin:40px auto;padding:0 20px;color:#18252e}section{border:1px solid #ddd;border-radius:12px;padding:20px;margin:20px 0}input{display:block;box-sizing:border-box;width:100%;padding:12px;margin:8px 0 20px}button{padding:12px 18px}small{color:#52616b}</style><h1>Report serale Giulia</h1><p>Ogni giorno alle <strong>19:00, ora italiana</strong>, direttamente a <strong>'.self::RECIPIENT.'</strong>.</p>';
        if($notice!=='')echo '<p role="status">'.$e($notice).'</p>';
        echo '<section><p>Invio: <strong>'.(!empty($config['enabled'])?'attivo':'da collegare').'</strong></p><p>Programmazione sul server: <strong>'.(!empty($scheduler['installed'])?'verificata':'da verificare').'</strong></p>';
        if(isset($state['snapshot']))echo '<p>Ultimo saldo letto: <strong>'.$e(number_format($state['snapshot']['remaining'],0,',','.')).' crediti</strong> su '.$e(number_format($state['snapshot']['total'],0,',','.')).'.</p>';
        if(isset($state['last_delivery_at']))echo '<p>Ultima email confermata: '.$e((new \DateTimeImmutable('@'.$state['last_delivery_at']))->setTimezone(new \DateTimeZone(self::TZ))->format('d/m/Y H:i')).'.</p>';
        echo '</section><section><h2>Collega il saldo</h2><p>Il collegamento legge saldo e contatori di utilizzo di ElevenLabs. Il report non esegue conversazioni, acquisti o simulazioni.</p><form method="post"><input type="hidden" name="_csrf" value="'.$e(Security::csrf()).'"><input type="hidden" name="action" value="activate"><label>Chiave ElevenLabs con permesso di lettura dell’account<input type="password" name="api_key" autocomplete="off" required maxlength="200"></label><button>Verifica, invia email e attiva il report</button></form><small>La chiave viene conservata nell’area privata del server e non viene mostrata nella pagina o nelle email. L’attivazione esegue una lettura di prova e invia un primo riepilogo a '.self::RECIPIENT.'.</small></section><p><a href="/giulia-integration">Domande registrate e formazione</a> · <a href="/">Torna al CRM</a></p></html>';
    }
}
