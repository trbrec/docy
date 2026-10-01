<?php
declare(strict_types=1);
namespace TrbCrm;
require_once __DIR__.'/OnboardingContractCatalog.php';

/** Owner-selected new candidates only; leaves the legacy CRM write scope intact. */
final class OnboardingEntry
{
    private const CANDIDATE_SCOPE = "COALESCE(s.source_tab,'') NOT IN ('PORTALE_ARTISTI','PORTALE_DEMO') AND s.status IN ('new','ready_review','in_review','interested','contract_prepared')";
    public function __construct(private \PDO $db){}
    public function candidates(): array
    {
        return $this->db->query('SELECT s.id,s.contract_number,c.artist_name,c.first_name,c.last_name FROM submissions s JOIN contacts c ON c.id=s.contact_id WHERE '.self::CANDIDATE_SCOPE.' AND NOT EXISTS(SELECT 1 FROM contracts ct WHERE ct.submission_id=s.id) ORDER BY s.id DESC LIMIT 100')->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function draft(int $submissionId,string $key,int $ownerId): int
    {
        OnboardingContractCatalog::model($key);
        if($submissionId<1||$ownerId<1)throw new \RuntimeException('Candidatura non valida');
        $mysql=$this->db->getAttribute(\PDO::ATTR_DRIVER_NAME)==='mysql';$lock=$mysql?' FOR UPDATE':'';
        $this->db->beginTransaction();
        try{
            $q=$this->db->prepare('SELECT s.id,s.contract_number,s.received_at,c.email,c.first_name,c.last_name FROM submissions s JOIN contacts c ON c.id=s.contact_id WHERE s.id=? AND '.self::CANDIDATE_SCOPE.$lock);$q->execute([$submissionId]);$row=$q->fetch(\PDO::FETCH_ASSOC);
            if(!$row||!filter_var($row['email'],FILTER_VALIDATE_EMAIL)||trim((string)$row['first_name'])===''||trim((string)$row['last_name'])==='')throw new \RuntimeException('Seleziona una nuova candidatura con nome, cognome ed email completi');
            $existing=$this->db->prepare('SELECT id,template_key,status,sent_at,accepted_at FROM contracts WHERE submission_id=? ORDER BY id DESC LIMIT 1'.$lock);$existing->execute([$submissionId]);$old=$existing->fetch(\PDO::FETCH_ASSOC);
            if($old){
                if($old['template_key']!==$key||$old['status']!=='draft'||$old['sent_at']||$old['accepted_at'])throw new \RuntimeException('La candidatura ha già un contratto: verifica la bozza esistente');
                $this->db->commit();return (int)$old['id'];
            }
            $number=(string)($row['contract_number']??'');
            if($number===''){
                $year=(int)(new \DateTimeImmutable($row['received_at'],new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Rome'))->format('Y');
                if($year<2000||$year>9999)throw new \RuntimeException('Anno contratto non valido');
                $floor=$year===2026?767:1;
                $this->db->prepare(($mysql?'INSERT IGNORE':'INSERT OR IGNORE').' INTO contract_sequences(year,next_number) VALUES(?,?)')->execute([$year,$floor]);
                $seq=$this->db->prepare('SELECT next_number FROM contract_sequences WHERE year=?'.$lock);$seq->execute([$year]);$seq->fetchColumn();
                $prefix=sprintf('TRB-%04d',$year);$assigned=$this->db->prepare('SELECT contract_number FROM submissions WHERE contract_number LIKE ?');$assigned->execute([$prefix.'%']);$max=0;
                while(($value=$assigned->fetchColumn())!==false)if(preg_match('/^'.preg_quote($prefix,'/').'([0-9]{4})$/',(string)$value,$match))$max=max($max,(int)$match[1]);
                $next=max($floor,$max+1);if($next>9999)throw new \RuntimeException('Numerazione contratti esaurita');
                $this->db->prepare('UPDATE contract_sequences SET next_number=? WHERE year=?')->execute([$next+1,$year]);$number=sprintf('TRB-%04d%04d',$year,$next);
            }
            $this->db->prepare("INSERT INTO contracts(submission_id,contract_number,template_key,status,metadata) VALUES(?,?,?,'draft',?)")->execute([$submissionId,$number,$key,json_encode(['onboarding_version'=>OnboardingPolicy::VERSION,'prepared_by'=>$ownerId],JSON_THROW_ON_ERROR)]);
            $id=(int)$this->db->lastInsertId();$this->db->prepare("UPDATE submissions SET contract_number=?,contract_type=?,status='contract_prepared' WHERE id=?")->execute([$number,$key,$submissionId]);
            $this->db->commit();return $id;
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
