<?php
namespace TrbCrm;
use DateTimeImmutable;use PDO;use RuntimeException;
final class SubmissionRepository {
// Preserve this unrelated future CRM logic.
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT s.*,c.email,c.first_name,c.last_name,c.phone,c.artist_name,c.artist_type,c.artist_type_source,c.artist_type_confidence,c.artist_type_reason,c.social_url,c.social_raw,c.social_status,c.social_checked_at,c.social_resolution FROM submissions s JOIN contacts c ON c.id=s.contact_id WHERE s.id=?');
        $stmt->execute([$id]); $submission = $stmt->fetch(); if (!$submission) return null;
        $this->normalizeIdentityRow($submission);
        $asset = $this->db->prepare('SELECT * FROM assets WHERE submission_id=? ORDER BY is_primary DESC,id'); $asset->execute([$id]);
        $history = $this->db->prepare('SELECT i.*,u.display_name FROM interactions i LEFT JOIN users u ON u.id=i.user_id WHERE i.submission_id=? ORDER BY occurred_at DESC,id DESC LIMIT 100'); $history->execute([$id]);
        $duplicates = $this->db->prepare("SELECT s.id,s.source_row,s.received_at,s.status,s.contract_number,c.artist_name
          FROM submissions s JOIN contacts c ON c.id=s.contact_id
          WHERE s.contact_id=? AND s.id<>? AND COALESCE(s.source_tab,'') <> 'CRM_TEST_PERMANENT' AND ABS(TIMESTAMPDIFF(DAY,s.received_at,?))<=120
          ORDER BY s.received_at DESC,s.id DESC");
        $duplicates->execute([(int)$submission['contact_id'], $id, $submission['received_at']]);
        $submission['assets'] = $asset->fetchAll();
        $fieldNotes=$this->db->prepare('SELECT id,field_key,raw_value,interpretation_status,updated_at FROM submission_field_notes WHERE submission_id=? ORDER BY id');$fieldNotes->execute([$id]);$submission['field_notes']=$fieldNotes->fetchAll();
        $primaryAsset = $submission['assets'][0] ?? null;
        $submission['material_source_url'] = implode("\n",array_values(array_unique(array_filter(array_map(static fn(array $row):string=>trim((string)($row['source_url']??'')),$submission['assets'])))));
        $submission['listen_url'] = is_array($primaryAsset) ? (string)($primaryAsset['canonical_url'] ?: ($primaryAsset['source_url'] ?? '')) : '';
        $submission['history'] = $history->fetchAll();
        $submission['duplicates'] = $duplicates->fetchAll();
        $contract = $this->db->prepare('SELECT * FROM contracts WHERE submission_id=? ORDER BY id DESC LIMIT 1');
        $contract->execute([$id]);
        $submission['contract'] = $contract->fetch() ?: null;
        if ($submission['contract'] && !empty($submission['contract']['metadata'])) {
            $metadata = json_decode((string)$submission['contract']['metadata'], true);
            $submission['contract']['metadata'] = is_array($metadata) ? $metadata : [];
        }
        $followups = $this->db->prepare('SELECT * FROM followups WHERE submission_id=? ORDER BY sequence_no,id');
        $followups->execute([$id]);
        $submission['followups'] = $followups->fetchAll();
        $ar=$this->db->prepare('SELECT feedback_type,feedback_at,first_call_status,first_call_at,first_call_outcome,second_call_status,second_call_at,second_call_outcome,next_action_type,next_action_at,summary_note,updated_at FROM submission_ar_workflow WHERE submission_id=? LIMIT 1');
        $ar->execute([$id]);
        $submission['ar_workflow']=$ar->fetch()?:['feedback_type'=>'nessuno','first_call_status'=>'non_prevista','second_call_status'=>'non_prevista','next_action_type'=>'ascolto'];
        $submission['writable']=$this->isWritableTestPractice($submission);
        $submission['ar_writable']=true;
        $submission['contract_draft_writable']=true;
        return $submission;
    }
    public function updateMetadata(int $id, array $input, int $userId): array
    {
        $before = $this->find($id); if (!$before) throw new RuntimeException('Candidatura non trovata');
        $priority = isset($input['priority']) ? (int)$input['priority'] : (int)$before['priority'];
        if ($priority < 0 || $priority > 100) throw new RuntimeException('La priorità deve essere compresa tra 0 e 100');
        $artistType = (string)($input['artist_type'] ?? $before['artist_type']);
        $allowedTypes = ['unknown','solo','band','duo','group','orchestra','producer','dj','singer','author','other'];
        if (!in_array($artistType, $allowedTypes, true)) throw new RuntimeException('Tipologia artista non valida');
        $contractType = trim((string)($input['contract_type'] ?? $before['contract_type'] ?? ''));
        if (mb_strlen($contractType) > 80) throw new RuntimeException('Tipologia contratto troppo lunga');
        $artistName = preg_replace('/\s+/u', ' ', trim((string)($input['artist_name'] ?? $before['artist_name'] ?? ''))) ?? '';
        if ($artistName === '' || mb_strlen($artistName) > 180) throw new RuntimeException('Il nome d’arte deve contenere da 1 a 180 caratteri');
        $normalizePersonName = static function ($value, string $label): string {
            $name = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
            if (mb_strlen($name) > 120) throw new RuntimeException($label.' troppo lungo');
            if ($name !== '' && preg_match('/[\x00-\x1F\x7F]/u', $name)) throw new RuntimeException($label.' contiene caratteri non validi');
            return $name;
        };
        $firstName = $normalizePersonName($input['first_name'] ?? $before['first_name'] ?? '', 'Nome');
        $lastName = $normalizePersonName($input['last_name'] ?? $before['last_name'] ?? '', 'Cognome');
        $email = mb_strtolower(trim((string)($input['email'] ?? $before['email'] ?? '')));
        if (mb_strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Indirizzo email non valido');
        $identityChanged = $artistName !== (string)$before['artist_name'] || $firstName !== (string)($before['first_name'] ?? '') || $lastName !== (string)($before['last_name'] ?? '');
        $emailChanged = $email !== mb_strtolower((string)$before['email']);
        if ($emailChanged) {
            $duplicateEmail=$this->db->prepare('SELECT id FROM contacts WHERE email_normalized=? AND id<>? LIMIT 1');
            $duplicateEmail->execute([$email,(int)$before['contact_id']]);
            if($duplicateEmail->fetchColumn())throw new RuntimeException('Questa email appartiene già a un’altra anagrafica: usa l’aggregazione candidature per evitare duplicati');
        }
        $validateUrl = static function (mixed $value, string $label, int $maxLength): string {
            $url = trim((string)$value);
            if (mb_strlen($url) > $maxLength) throw new RuntimeException($label.' troppo lungo');
            if ($url !== '') {
                $scheme = mb_strtolower((string)parse_url($url, PHP_URL_SCHEME));
                if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http','https'], true)) {
                    throw new RuntimeException($label.' non valido: usa un indirizzo completo http o https');
                }
            }
            return $url;
        };
        $socialChanged = array_key_exists('social_url', $input);
        $materialChanged = array_key_exists('material_url', $input);
        $socialRaw=$socialChanged?trim((string)$input['social_url']):(string)($before['social_raw']??$before['social_url']??'');
        if(mb_strlen($socialRaw)>4000)throw new RuntimeException('Indicazione social troppo lunga');
        $socialNormalized=$this->normalizeSocialInput($socialRaw);
        $socialUrl=$socialNormalized['urls']?implode("\n",$socialNormalized['urls']):'';
        $materialRaw=$materialChanged?trim((string)$input['material_url']):(string)($before['material_source_url']??'');
        if(mb_strlen($materialRaw)>12000)throw new RuntimeException('Link demo o ascolto troppo lunghi');
        $materialUrls=$this->extractHttpUrls($materialRaw);
        $materialUrl=$materialUrls?implode("\n",$materialUrls):'';

        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE submissions SET priority=?,contract_type=? WHERE id=?')->execute([$priority,$contractType!==''?$contractType:null,$id]);
            $artistTypeChanged=$artistType!==(string)$before['artist_type'];
            $this->db->prepare('UPDATE contacts SET email=?,email_normalized=?,artist_name=?,first_name=?,last_name=?,artist_type=?,artist_type_source=IF(?,\'manual\',artist_type_source),artist_type_confidence=IF(?,1.0000,artist_type_confidence),artist_type_reason=IF(?,\'Corretto manualmente nel CRM\',artist_type_reason) WHERE id=?')
                ->execute([$email,$email,$artistName,$firstName!==''?$firstName:null,$lastName!==''?$lastName:null,$artistType,$artistTypeChanged?1:0,$artistTypeChanged?1:0,$artistTypeChanged?1:0,(int)$before['contact_id']]);
            if ($socialChanged) {
                $this->db->prepare('UPDATE contacts SET social_url=?,social_raw=?,social_status=?,social_checked_at=NULL,social_resolution=? WHERE id=?')->execute([$socialUrl!==''?$socialUrl:null,$socialRaw!==''?$socialRaw:null,$socialNormalized['status'],json_encode(['source'=>'manual','platform'=>$socialNormalized['platform'],'candidates'=>$socialNormalized['candidates']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$before['contact_id']]);
                if($socialNormalized['status']==='pending_search'&&$socialRaw!=='')$this->queueUniqueDiscoveryJob('discover_social_profile',['contact_id'=>(int)$before['contact_id'],'submission_id'=>$id,'raw'=>$socialRaw],(int)$before['contact_id']);
                if($socialNormalized['urls'])$this->queueUniqueDiscoveryJob('discover_social_demo',['contact_id'=>(int)$before['contact_id'],'submission_id'=>$id],(int)$before['contact_id']);
                if($socialNormalized['status']==='uninterpretable')$this->storeFieldNote($id,'social',$socialRaw);
            }
            if ($materialChanged) {
                $this->db->prepare("UPDATE assets SET source_url='',access_status='unknown',last_checked_at=NULL,error_message=NULL WHERE submission_id=? AND access_status<>'secured' AND COALESCE(pcloud_file_id,'')='' AND TRIM(COALESCE(source_url,''))<>''")->execute([$id]);
                $received = new DateTimeImmutable((string)$before['received_at'], new \DateTimeZone('UTC'));
                $pcloudPath = $this->pcloudPath($received, $artistName);
                foreach($materialUrls as $index=>$url){
                    $existing=$this->db->prepare('SELECT id FROM assets WHERE submission_id=? AND (source_url=? OR canonical_url=?) LIMIT 1');$existing->execute([$id,$url,$url]);$assetId=(int)($existing->fetchColumn()?:0);
                    [$kind,$provider,$access]=$this->classifyAsset($url,'demo_link');
                    if($assetId>0)$this->db->prepare("UPDATE assets SET kind=?,provider=?,source_url=?,access_status=IF(access_status='secured','secured',?),last_checked_at=NULL,error_message=NULL,is_primary=? WHERE id=?")->execute([$kind,$provider,$url,$access,$index===0?1:0,$assetId]);
                    else{$this->db->prepare('INSERT INTO assets(submission_id,kind,provider,source_url,access_status,pcloud_path,is_primary) VALUES(?,?,?,?,?,?,?)')->execute([$id,$kind,$provider,$url,$access,$pcloudPath,$index===0?1:0]);$assetId=(int)$this->db->lastInsertId();}
                    $this->queueUniqueDiscoveryJob('verify_external_asset',['asset_id'=>$assetId,'submission_id'=>$id],$assetId);
                }
                if($materialRaw!==''&&!$materialUrls)$this->storeFieldNote($id,'demo',$materialRaw);
            }
            if($artistTypeChanged||$identityChanged)$this->db->prepare("UPDATE submission_identity_snapshots SET artist_name=?,first_name=?,last_name=?,artist_type=?,snapshot_source='manual' WHERE submission_id=?")->execute([$artistName,$firstName!==''?$firstName:null,$lastName!==''?$lastName:null,$artistType,$id]);
            $this->audit($userId, 'submission', (string)$id, 'metadata_updated', [
                'artist_name'=>$before['artist_name'],'first_name'=>$before['first_name']??null,'last_name'=>$before['last_name']??null,'email'=>$before['email'],'priority'=>(int)$before['priority'],'artist_type'=>$before['artist_type'],'contract_type'=>$before['contract_type'],
                'social_url'=>$before['social_url']??null,'material_url'=>$before['material_source_url']??null
            ], [
                'artist_name'=>$artistName,'first_name'=>$firstName!==''?$firstName:null,'last_name'=>$lastName!==''?$lastName:null,'email'=>$email,'priority'=>$priority,'artist_type'=>$artistType,'contract_type'=>$contractType!==''?$contractType:null,
                'social_url'=>$socialUrl!==''?$socialUrl:null,'social_status'=>$socialNormalized['status'],'material_url'=>$materialUrl!==''?$materialUrl:null
            ]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        return $this->find($id) ?? [];
    }
    public function ingestFluentForm(array $payload, string $rawBody = ''): array
    {
        $this->ensureInboundEventsTable();
        $data = isset($payload['data']) && is_array($payload['data']) ? array_merge($payload, $payload['data']) : $payload;
        $email = mb_strtolower(trim($this->payloadValue($data, ['email_demo','email'])));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Indirizzo email non valido');

        $artistName = $this->limit($this->payloadValue($data, ['nomedarte_demo.first_name','nomedarte_demo[first_name]','nomedarte_demo','artist_name','pseudonimo']), 180);
        $firstName = $this->limit($this->payloadValue($data, ['names_demo.first_name','names_demo[first_name]','first_name','nome']), 120);
        $lastName = $this->limit($this->payloadValue($data, ['names_demo.last_name','names_demo[last_name]','last_name','cognome']), 120);
        $identity = $this->normalizedArtistIdentity($artistName,$firstName,$lastName);
        $artistName = $identity['artist_name'] ?: 'Senza nome d’arte';
        $firstName = $identity['first_name'];
        $lastName = $identity['last_name'];
        $phone = $this->limit($this->payloadValue($data, ['phone_demo','phone','telefono']), 50);
        $social = trim($this->payloadValue($data, ['socialnetwork_demo','social_url','social']));
        $socialNormalized=$this->normalizeSocialInput($social);
        $demoLink = trim($this->payloadValue($data, ['linkdemo_demo','demo_link','link']));
        $demoUpload = trim($this->payloadValue($data, ['file_upload_demo','demo_upload','upload_url','file_url']));
        $artistSuggestion=$this->inferArtistType($artistName,array_merge($data,['first_name'=>$firstName,'last_name'=>$lastName]));

        $received = $this->parseReceivedAt($this->payloadValue($data, ['created_at','date_created','submitted_at','received_at']));
        $externalId = $this->payloadValue($data, ['submission_id','entry_id','id','serial_number']);
        $canonicalPayload = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: $rawBody;
        $payloadHash = hash('sha256', $rawBody !== '' ? $rawBody : $canonicalPayload);
        $eventKey = hash('sha256', 'fluent_form_7|'.($externalId !== '' ? $externalId : $payloadHash));

        $this->db->beginTransaction();
        try {
            $event = $this->db->prepare("INSERT IGNORE INTO inbound_events(provider,event_key,payload_hash,status) VALUES('fluent_form_7',?,?,'processing')");
            $event->execute([$eventKey,$payloadHash]);
            if ($event->rowCount() === 0) {
                $existing = $this->db->prepare("SELECT submission_id,status FROM inbound_events WHERE provider='fluent_form_7' AND event_key=? FOR UPDATE");
                $existing->execute([$eventKey]); $record = $existing->fetch();
                $this->db->commit();
                return ['submission_id'=>(int)($record['submission_id']??0),'duplicate'=>true];
            }
            $eventId = (int)$this->db->lastInsertId();

            $contact = $this->db->prepare('SELECT id FROM contacts WHERE email_normalized=? ORDER BY id LIMIT 1 FOR UPDATE');
            $contact->execute([$email]); $contactId = $contact->fetchColumn();
            if (!$contactId) {
                $this->db->prepare('INSERT INTO contacts(email,email_normalized,first_name,last_name,phone,artist_name,artist_type,artist_type_source,artist_type_confidence,artist_type_reason,social_url,social_raw,social_status,social_resolution,consent_source) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$email,$email,$firstName?:null,$lastName?:null,$phone?:null,$artistName,$artistSuggestion['type'],$artistSuggestion['source'],$artistSuggestion['confidence'],$artistSuggestion['reason'],$socialNormalized['urls']?implode("\n",$socialNormalized['urls']):null,$social?:null,$socialNormalized['status'],json_encode(['source'=>'form','platform'=>$socialNormalized['platform'],'candidates'=>$socialNormalized['candidates']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'fluent_form_7']);
                $contactId = (int)$this->db->lastInsertId();
            } else {
                $this->db->prepare("UPDATE contacts SET email=?,first_name=COALESCE(NULLIF(?,''),first_name),last_name=COALESCE(NULLIF(?,''),last_name),phone=COALESCE(NULLIF(?,''),phone),artist_name=COALESCE(NULLIF(?,''),artist_name),artist_type_source=IF(artist_type='unknown',?,artist_type_source),artist_type_confidence=IF(artist_type='unknown',?,artist_type_confidence),artist_type_reason=IF(artist_type='unknown',?,artist_type_reason),artist_type=IF(artist_type='unknown',?,artist_type),social_url=IF(?<>'',?,social_url),social_raw=IF(?<>'',?,social_raw),social_status=IF(?<>'',?,social_status),social_checked_at=NULL,social_resolution=IF(?<>'',?,social_resolution),consent_source='fluent_form_7' WHERE id=?")
                    ->execute([$email,$firstName,$lastName,$phone,$artistName,$artistSuggestion['source'],$artistSuggestion['confidence'],$artistSuggestion['reason'],$artistSuggestion['type'],$social,$socialNormalized['urls']?implode("\n",$socialNormalized['urls']):null,$social,$social?:null,$social,$socialNormalized['status'],$social,json_encode(['source'=>'form','platform'=>$socialNormalized['platform'],'candidates'=>$socialNormalized['candidates']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$contactId]);
            }

            $assetInputs=[];
            foreach([['field'=>'demo_link','raw'=>$demoLink],['field'=>'demo_upload','raw'=>$demoUpload]] as $source){
                foreach($this->extractHttpUrls($source['raw']) as $url)$assetInputs[]=['field'=>$source['field'],'url'=>$url];
            }
            $deduped=[];foreach($assetInputs as $assetInput)$deduped[$assetInput['url']]=$assetInput;$assetInputs=array_values($deduped);
            $status = 'new';
            foreach ($assetInputs as $assetInput) {
                [$kind,,$access] = $this->classifyAsset($assetInput['url'],$assetInput['field']);
                if ($access === 'permission_required') $status = 'needs_access';
                elseif ($kind === 'temporary_transfer' && $status === 'new') $status = 'files_expiring';
            }
            $contractNumber = $this->nextContractNumber((int)$received->setTimezone(new \DateTimeZone('Europe/Rome'))->format('Y'));
            $publicId = strtoupper(substr(bin2hex(random_bytes(16)),0,26));
            $rowFingerprint = hash('sha256',implode('|',[$received->format(DATE_ATOM),$email,$artistName,$demoLink,$demoUpload]));
            $rawPayload = json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $this->db->prepare("INSERT INTO submissions(public_id,contact_id,source,source_tab,received_at,status,contract_number,row_fingerprint,raw_payload) VALUES(?,?,'website_form','fluent_form_7',?,?,?,?,?)")
                ->execute([$publicId,$contactId,$received->format('Y-m-d H:i:s'),$status,$contractNumber,$rowFingerprint,$rawPayload]);
            $submissionId = (int)$this->db->lastInsertId();
            $this->saveSubmissionIdentitySnapshot($submissionId,(int)$contactId,$artistName,$firstName,$lastName,(string)$artistSuggestion['type'],'fluent_form_7');
            $pcloudPath = $this->pcloudPath($received,$artistName);

            foreach ($assetInputs as $index => $assetInput) {
                [$kind,$provider,$access] = $this->classifyAsset($assetInput['url'],$assetInput['field']);
                $this->db->prepare('INSERT INTO assets(submission_id,kind,provider,source_url,access_status,pcloud_path,is_primary) VALUES(?,?,?,?,?,?,?)')
                    ->execute([$submissionId,$kind,$provider,$assetInput['url'],$access,$pcloudPath,$index===0?1:0]);
                $assetId = (int)$this->db->lastInsertId();
                $jobType = match ($kind) {
                    'google_drive' => 'request_drive_access',
                    'temporary_transfer' => 'secure_temporary_transfer',
                    'direct_upload' => 'mirror_to_pcloud',
                    default => null,
                };
                if ($jobType) {
                    $this->db->prepare('INSERT INTO jobs(type,payload) VALUES(?,?)')->execute([$jobType,json_encode(['asset_id'=>$assetId,'submission_id'=>$submissionId,'pcloud_path'=>$pcloudPath],JSON_UNESCAPED_SLASHES)]);
                }
                $this->queueUniqueDiscoveryJob('verify_external_asset',['asset_id'=>$assetId,'submission_id'=>$submissionId],$assetId);
            }
            if($socialNormalized['status']==='pending_search'&&$social!=='')$this->queueUniqueDiscoveryJob('discover_social_profile',['contact_id'=>(int)$contactId,'submission_id'=>$submissionId,'raw'=>$social],(int)$contactId);
            $demoAsSocial=$this->normalizeSocialInput($demoLink);
            if($socialNormalized['status']==='uninterpretable'&&$demoAsSocial['platform']!==''){
                $this->db->prepare("UPDATE contacts SET social_status='pending_search',social_resolution=? WHERE id=?")->execute([json_encode(['source'=>'demo_field_hint','platform'=>$demoAsSocial['platform'],'candidates'=>$demoAsSocial['candidates']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$contactId]);
                $this->queueUniqueDiscoveryJob('discover_social_profile',['contact_id'=>(int)$contactId,'submission_id'=>$submissionId,'raw'=>$demoLink],(int)$contactId);
            }
            if($socialNormalized['status']==='uninterpretable')$this->storeFieldNote($submissionId,'social',$social);
            $unparsedDemo=[];if($demoLink!==''&&!$this->extractHttpUrls($demoLink))$unparsedDemo[]=$demoLink;if($demoUpload!==''&&!$this->extractHttpUrls($demoUpload))$unparsedDemo[]=$demoUpload;
            if($unparsedDemo)$this->storeFieldNote($submissionId,'demo',implode("\n\n",array_values(array_unique($unparsedDemo))));

            $dupes = $this->db->prepare("SELECT sx.id,sx.contract_number,sx.status FROM submissions sx JOIN contacts cx ON cx.id=sx.contact_id WHERE cx.email_normalized=? AND sx.id<>? AND sx.status<>'merged_duplicate' AND sx.received_at BETWEEN DATE_SUB(?,INTERVAL 120 DAY) AND ? ORDER BY sx.received_at ASC,sx.id ASC FOR UPDATE");
            $receivedSql = $received->format('Y-m-d H:i:s');
            $dupes->execute([$email,$submissionId,$receivedSql,$receivedSql]);
            $duplicateRows=$dupes->fetchAll();
            foreach ($duplicateRows as $old) {
                $oldId=(int)$old['id'];
                $this->db->prepare('UPDATE assets SET submission_id=? WHERE submission_id=?')->execute([$submissionId,$oldId]);
                $this->db->prepare("UPDATE submissions SET status='merged_duplicate',primary_submission_id=? WHERE id=?")->execute([$submissionId,$oldId]);
                $this->db->prepare("INSERT INTO interactions(submission_id,type,subject,metadata) VALUES(?,'system','Aggregazione automatica entro 120 giorni',?)")
                    ->execute([$submissionId,json_encode(['absorbed_submission_id'=>$oldId,'previous_contract_number'=>$old['contract_number'],'previous_status'=>$old['status']],JSON_UNESCAPED_UNICODE)]);
            }

            $this->db->prepare("UPDATE inbound_events SET submission_id=?,status='completed',completed_at=UTC_TIMESTAMP() WHERE id=?")->execute([$submissionId,$eventId]);
            $this->db->prepare("INSERT INTO audit_log(entity_type,entity_id,action,after_json,ip_hash) VALUES('submission',?,'webhook_ingested',?,?)")
                ->execute([(string)$submissionId,json_encode(['provider'=>'fluent_form_7','contract_number'=>$contractNumber,'merged_count'=>count($duplicateRows)]),Security::ipHash()]);
            $this->db->commit();
            return ['submission_id'=>$submissionId,'duplicate'=>false];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
    /** @return list<string> */
    private function extractHttpUrls(string $value): array
    {
        preg_match_all('~https?://[^\s<>"\']+~iu',$value,$matches);
        $urls=[];
        foreach($matches[0]??[] as $url){
            $url=rtrim((string)$url,")],.;:!?'\"");
            if(!filter_var($url,FILTER_VALIDATE_URL))continue;
            $scheme=mb_strtolower((string)parse_url($url,PHP_URL_SCHEME));
            if(!in_array($scheme,['http','https'],true))continue;
            $urls[$url]=true;
        }
        return array_keys($urls);
    }

    /** @return array{status:string,platform:string,urls:list<string>,candidates:list<string>} */
}

