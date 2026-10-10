<?php
declare(strict_types=1);
namespace TrbCrm;

/** Scoped identification and channel admission. Never returns financial or document data. */
final class GiuliaSupport
{
    public const AGENT = 'agent_7601m3ydrnzyf5jsk6yrct49b0az';
    public const VERSION = '2026-10-10.1';
    public function __construct(private \PDO $db, private string $key) {}

    public static function normalizeName(string $value): string
    {
        $value = strtr(mb_strtolower(trim($value), 'UTF-8'), ['’'=>"'", '‘'=>"'", 'à'=>'a', 'á'=>'a', 'è'=>'e', 'é'=>'e', 'ì'=>'i', 'í'=>'i', 'ò'=>'o', 'ó'=>'o', 'ù'=>'u', 'ú'=>'u']);
        return preg_replace('/\s+/u', ' ', $value) ?? '';
    }
    public static function normalizeContract(string $value): string
    {
        return preg_replace('/\s+/u', '', mb_strtoupper(trim($value), 'UTF-8')) ?? '';
    }
    public static function matches(array $row, string $first, string $last, string $number): bool
    {
        $first = explode(' ', self::normalizeName($first))[0];
        $savedFirst = explode(' ', self::normalizeName((string)($row['first_name'] ?? '')))[0];
        $last = self::normalizeName($last);
        return $first !== '' && $last !== '' && $savedFirst === $first
            && self::normalizeName((string)($row['last_name'] ?? '')) === $last
            && self::normalizeContract((string)($row['contract_number'] ?? '')) === self::normalizeContract($number);
    }
    public static function role(array $row): string
    {
        $state = (string)($row['contract_status'] ?? '');
        if (in_array($state, ['rifiutato','annullato','terminato'], true) || in_array((string)($row['submission_status'] ?? ''), ['rejected','archived','merged_duplicate'], true)) return 'inactive';
        if ($state === 'accettato' || in_array((string)($row['record_status'] ?? ''), ['accepted','signed'], true)
            || (string)($row['submission_status'] ?? '') === 'accepted' || !empty($row['activation_date'])
            || in_array((string)($row['portal_status'] ?? ''), ['attivo','sospeso'], true)) return 'artist';
        return 'candidate';
    }
    public static function model(string $key): ?string
    {
        return ['dds_pimd_49'=>'DDS49','ddb_csae_600'=>'DDB12_600','ddb_ccad_600'=>'DDB600','ddb_ccad_800'=>'DDB800','ddb_ccad_1200'=>'DDB1200',
            'ddb_trb_ccad_2000'=>'DDBTRB2000','ddb_trb_ccad_3000'=>'DDBTRB3000','ddb_trb_ccad_4000'=>'DDBTRB4000','ddb_trb_ccad_6000'=>'DDBTRB6000','trb_ccde'=>'TRB'][$key] ?? null;
    }
    public static function voiceDecision(string $role, ?array $reservation, string $conversation, int $now): array
    {
        if ($role !== 'candidate') return ['call_allowed'=>false,'call_reason'=>'chat_and_email_only','max_call_seconds'=>0];
        if ($reservation === null) return ['call_allowed'=>true,'call_reason'=>'first_candidate_call','max_call_seconds'=>1200];
        if (!hash_equals((string)$reservation['conversation_hash'], $conversation)) return ['call_allowed'=>false,'call_reason'=>'first_call_already_used','max_call_seconds'=>0];
        $left = max(0, 1200 - max(0, $now - (int)$reservation['started_at']));
        return ['call_allowed'=>$left > 0,'call_reason'=>$left > 0 ? 'same_first_call' : 'first_call_expired','max_call_seconds'=>$left];
    }
    public static function redact(string $text, array $names = []): string
    {
        $text = mb_substr(trim(strip_tags($text)), 0, 1600);
        $text = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $text) ?? '';
        $text = preg_replace('/https?:\/\/\S+/i', '[link]', $text) ?? '';
        foreach ($names as $name) foreach (preg_split('/\s+/u',trim((string)$name)) ?: [] as $part) if (mb_strlen($part) > 2) $text = preg_replace('/(?<!\p{L})'.preg_quote($part, '/').'(?!\p{L})/iu', '[nome]', $text) ?? $text;
        $text = preg_replace('/\b[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]\b/i', '[codice fiscale]', $text) ?? '';
        $text = preg_replace('/\b(?:TRB|DDB|DDS|TEST)[\s-]*[0-9]+[A-Z]?\b/i', '[contratto]', $text) ?? '';
        return preg_replace('/(?<!\w)\+?\d[\d .()\/-]{5,}\d(?!\w)/', '[numero]', $text) ?? '';
    }
    private function hash(string $purpose, string $value): string { return hash_hmac('sha256', $purpose.'|'.$value, $this->key); }
    private static function configPath(): string { return dirname(__DIR__, 2).'/private/giulia-support.json'; }
    public static function config(): array
    {
        $value = @file_get_contents(self::configPath());
        $config = is_string($value) ? json_decode($value, true) : null;
        if (!is_array($config) || strlen((string)($config['key'] ?? '')) !== 64) throw new \RuntimeException('Integration unavailable');
        return $config;
    }
    public static function install(\PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS giulia_contact_bindings (actor_hash CHAR(64) PRIMARY KEY, submission_id BIGINT UNSIGNED NOT NULL, verified_at DATETIME NOT NULL, INDEX giulia_binding_submission(submission_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS giulia_first_calls (submission_id BIGINT UNSIGNED PRIMARY KEY, conversation_hash CHAR(64) NOT NULL, started_at BIGINT UNSIGNED NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS giulia_rate_limits (actor_hash CHAR(64) NOT NULL, bucket BIGINT UNSIGNED NOT NULL, attempts INT UNSIGNED NOT NULL DEFAULT 1, PRIMARY KEY(actor_hash,bucket)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS giulia_unanswered (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, report_day DATE NOT NULL, conversation_hash CHAR(64) NOT NULL, question_hash CHAR(64) NOT NULL, question TEXT NOT NULL, reason VARCHAR(80) NOT NULL, model_code VARCHAR(30) NULL, role_code VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, UNIQUE KEY giulia_question_once(conversation_hash,question_hash), INDEX giulia_report_day(report_day)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    private function rows(string $number = '', int $id = 0): array
    {
        $sql = "SELECT s.id submission_id,s.contact_id,s.contract_number,s.status submission_status,
            COALESCE(si.first_name,c.first_name) first_name,COALESCE(si.last_name,c.last_name) last_name,
            ct.template_key,ct.status record_status,cl.contract_status,cl.activation_date,pe.portal_status
            FROM submissions s JOIN contacts c ON c.id=s.contact_id
            LEFT JOIN submission_identity_snapshots si ON si.submission_id=s.id
            LEFT JOIN contracts ct ON ct.id=(SELECT c2.id FROM contracts c2 WHERE c2.submission_id=s.id ORDER BY c2.id DESC LIMIT 1)
            LEFT JOIN contract_lifecycle cl ON cl.contract_id=ct.id LEFT JOIN portal_entitlements pe ON pe.contract_id=ct.id
            WHERE ".($id > 0 ? 's.id=?' : "UPPER(REPLACE(TRIM(s.contract_number),' ',''))=?")." LIMIT 10";
        $q=$this->db->prepare($sql);$q->execute([$id > 0 ? $id : self::normalizeContract($number)]);return $q->fetchAll(\PDO::FETCH_ASSOC);
    }
    private function bound(string $actor): ?array
    {
        $q=$this->db->prepare('SELECT submission_id FROM giulia_contact_bindings WHERE actor_hash=?');$q->execute([$actor]);
        $id=(int)$q->fetchColumn();return $id > 0 ? ($this->rows('', $id)[0] ?? null) : null;
    }
    private function admission(array $row, bool $textOnly, string $conversation): array
    {
        $role=self::role($row);$voice=['call_allowed'=>false,'call_reason'=>'text_session','max_call_seconds'=>0];
        if (!$textOnly) {
            if ($role === 'candidate') {
                $this->db->prepare('INSERT IGNORE INTO giulia_first_calls(submission_id,conversation_hash,started_at) VALUES(?,?,?)')->execute([(int)$row['submission_id'],$conversation,time()]);
                $q=$this->db->prepare('SELECT conversation_hash,started_at FROM giulia_first_calls WHERE submission_id=?');$q->execute([(int)$row['submission_id']]);$reservation=$q->fetch(\PDO::FETCH_ASSOC) ?: null;
            } else $reservation=null;
            $voice=self::voiceDecision($role,$reservation,$conversation,time());
        }
        return ['verified'=>true,'role'=>$role,'model_code'=>self::model((string)($row['template_key'] ?? '')),
            'support_scope'=>'informational_only','support_email'=>'info@trbrec.com','portal_url'=>'https://artist.trbrec.com/']+$voice;
    }
    public function handle(array $input): array
    {
        foreach (['caller_id','conversation_id'] as $key) if (!is_string($input[$key] ?? null) || !preg_match('/^[A-Za-z0-9_+:.\-]{1,180}$/D', $input[$key])) throw new \InvalidArgumentException('Invalid context');
        if (($input['agent_id'] ?? '') !== self::AGENT) throw new \InvalidArgumentException('Invalid agent');
        $textOnly=filter_var($input['text_only'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($textOnly === null || !array_key_exists('text_only',$input)) throw new \InvalidArgumentException('Invalid channel');
        $actor=$this->hash('actor', $input['caller_id']);$conversation=$this->hash('conversation', $input['conversation_id']);$bucket=intdiv(time(),60);
        if (random_int(1,100) === 1) $this->db->exec('DELETE FROM giulia_rate_limits WHERE bucket < '.($bucket-1440).' LIMIT 10000');
        $this->db->prepare('INSERT INTO giulia_rate_limits(actor_hash,bucket) VALUES(?,?) ON DUPLICATE KEY UPDATE attempts=attempts+1')->execute([$actor,$bucket]);
        $q=$this->db->prepare('SELECT attempts FROM giulia_rate_limits WHERE actor_hash=? AND bucket=?');$q->execute([$actor,$bucket]);
        if ((int)$q->fetchColumn() > 12) return ['verified'=>false,'reason'=>'retry_limit','support_email'=>'info@trbrec.com'];
        $action=(string)($input['action'] ?? 'recognize');
        if ($action === 'unanswered') {
            $row=$this->bound($actor);$question=self::redact((string)($input['question'] ?? ''),$row ? [$row['first_name'],$row['last_name']] : []);
            if (mb_strlen($question)<8) throw new \InvalidArgumentException('Question missing');
            $reason=(string)($input['reason'] ?? 'missing_information');
            if (!in_array($reason,['missing_information','conflicting_sources','personal_verification','technical_problem','out_of_scope','recognition_failed'],true)) $reason='missing_information';
            $day=(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Rome')))->format('Y-m-d');
            $this->db->prepare('INSERT IGNORE INTO giulia_unanswered(report_day,conversation_hash,question_hash,question,reason,model_code,role_code,created_at) VALUES(?,?,?,?,?,?,?,UTC_TIMESTAMP())')
                ->execute([$day,$conversation,hash('sha256',$question),$question,$reason,$row ? self::model((string)($row['template_key'] ?? '')) : null,$row ? self::role($row) : 'unknown']);
            return ['recorded'=>true,'support_email'=>'info@trbrec.com'];
        }
        if ($action !== 'recognize') throw new \InvalidArgumentException('Invalid action');
        $first=(string)($input['first_name'] ?? '');$last=(string)($input['last_name'] ?? '');$number=(string)($input['contract_number'] ?? '');
        if (mb_strlen($first)>120 || mb_strlen($last)>120 || mb_strlen($number)>100) throw new \InvalidArgumentException('Invalid identity fields');
        if ($first === '' && $last === '' && $number === '') {
            $row=$this->bound($actor);return $row ? $this->admission($row,$textOnly,$conversation) : ['verified'=>false,'reason'=>'identity_required','required_fields'=>['first_name','last_name','contract_number'],'support_email'=>'info@trbrec.com'];
        }
        if ($first === '' || $last === '' || $number === '') return ['verified'=>false,'reason'=>'identity_required','support_email'=>'info@trbrec.com'];
        $matches=array_values(array_filter($this->rows($number),static fn($row)=>self::matches($row,$first,$last,$number)));
        if (count($matches)!==1) return ['verified'=>false,'reason'=>'not_verified','support_email'=>'info@trbrec.com'];
        $row=$matches[0];
        $this->db->prepare('INSERT INTO giulia_contact_bindings(actor_hash,submission_id,verified_at) VALUES(?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE submission_id=VALUES(submission_id),verified_at=UTC_TIMESTAMP()')->execute([$actor,(int)$row['submission_id']]);
        return $this->admission($row,$textOnly,$conversation);
    }
    public static function dispatch(string $method, string $path): bool
    {
        if (!in_array($path,['/api/giulia/support','/giulia-integration'],true)) return false;
        header('Cache-Control: no-store, private');header('X-Robots-Tag: noindex, nofollow');header('Referrer-Policy: no-referrer');
        try {
            $config=self::config();
            if ($path === '/api/giulia/support') {
                if ($method !== 'POST') { Response::json(['error'=>'method_not_allowed'],405);return true; }
                if (!hash_equals($config['key'],(string)($_SERVER['HTTP_X_TRB_GIULIA_KEY'] ?? ''))) { Response::json(['error'=>'unauthorized'],403);return true; }
                if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>8192) {Response::json(['error'=>'invalid_request'],400);return true;}
                $raw=(string)file_get_contents('php://input',false,null,0,8193);
                if(strlen($raw)>8192)throw new \InvalidArgumentException('Invalid request');
                $input=json_decode($raw,true,16,JSON_THROW_ON_ERROR);if(!is_array($input))throw new \InvalidArgumentException('Invalid request');
                $db=Database::connection();if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
                Response::json((new self($db,$config['key']))->handle($input));return true;
            }
            $owner=Security::requireUser();if(($owner['role']??'')!=='admin') {Response::json(['error'=>'forbidden'],403);return true;}
            if($method!=='GET') {Response::json(['error'=>'method_not_allowed'],405);return true;}
            $db=Database::connection();$day=(string)($_GET['day'] ?? (new \DateTimeImmutable('now',new \DateTimeZone('Europe/Rome')))->format('Y-m-d'));
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$day);if(!$date || $date->format('Y-m-d')!==$day)throw new \InvalidArgumentException('Invalid date');
            $rolling=($_GET['window'] ?? '')==='24h';
            $q=$db->prepare('SELECT question,reason,model_code,role_code,created_at FROM giulia_unanswered WHERE '.($rolling ? 'created_at >= UTC_TIMESTAMP() - INTERVAL 1 DAY' : 'report_day=?').' ORDER BY id');$q->execute($rolling ? [] : [$day]);$questions=$q->fetchAll(\PDO::FETCH_ASSOC);
            $bindings=(int)$db->query('SELECT COUNT(*) FROM giulia_contact_bindings')->fetchColumn();$calls=(int)$db->query('SELECT COUNT(*) FROM giulia_first_calls')->fetchColumn();
            $e=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html lang="it"><meta charset="utf-8"><title>Giulia — Riconoscimento e formazione</title><style>body{font:16px system-ui;max-width:1000px;margin:40px auto;padding:0 20px;color:#17202b}input{padding:8px;max-width:100%;box-sizing:border-box}table{border-collapse:collapse;width:100%}td,th{padding:12px;border-bottom:1px solid #ddd;text-align:left;vertical-align:top}small{color:#52606c}</style><h1>Giulia — Riconoscimento e formazione</h1><p>Integrazione attiva · versione '.$e(self::VERSION).'</p><p>Contatti riconosciuti: '.$bindings.' · prime call registrate: '.$calls.'</p><p>Nome principale, cognome completo e numero contratto devono coincidere. Il secondo nome può essere omesso. Artisti: assistenza scritta WhatsApp e info@trbrec.com; candidati: una prima call di massimo 20 minuti.</p><details><summary>Collegamento riservato a ElevenLabs</summary><p>Endpoint: https://crm.trbrec.com/api/giulia/support</p><label>Chiave integrazione <input id="giulia-key" type="text" value="'.$e($config['key']).'" readonly size="68"></label></details><h2>Domande senza risposta — '.($rolling ? 'ultime 24 ore' : $e($day)).'</h2><form><label>Giorno <input type="date" name="day" value="'.$e($day).'"></label> <button>Mostra report</button></form><p>Totale: '.count($questions).'</p>';
            if(!$questions)echo '<p>Nessuna domanda irrisolta registrata per questo giorno.</p>';
            else {echo '<table><tr><th>Domanda</th><th>Motivo</th><th>Modello / interlocutore</th></tr>';foreach($questions as $row)echo '<tr><td>'.$e($row['question']).'</td><td>'.$e($row['reason']).'</td><td>'.$e($row['model_code']?:'Da identificare').' · '.$e($row['role_code']).'</td></tr>';echo '</table>';}
            echo '<p><small>Il report registra domande anonimizzate. Giulia indirizza sempre a info@trbrec.com quando serve una risposta o una verifica dell’ufficio. Il riconoscimento abilita supporto informativo e non rende accessibili saldi, documenti o credenziali.</small></p><p><a href="/">Torna al CRM</a></p></html>';return true;
        } catch (\Throwable $error) {
            Response::json(['verified'=>false,'reason'=>'service_unavailable','support_email'=>'info@trbrec.com'],503);return true;
        }
    }
}
