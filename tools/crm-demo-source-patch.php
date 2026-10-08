<?php
/** Pure source patch; no CRM bootstrap, configuration, database or outbound service access. */
function trb_crm_demo_source_patch(string $source): string
{
    $changes=[
        [<<<'OLD_0'
$social = trim($this->payloadValue($data, ['socialnetwork_demo','social_url','social']));
OLD_0,
<<<'NEW_0'
$social = $this->fluentFormSocialInput($data);
NEW_0
        ],
        [<<<'OLD_1'
$materialChanged = array_key_exists('material_url', $input);
OLD_1,
<<<'NEW_1'
$materialChanged = array_key_exists('material_url', $input)
            && trim((string)$input['material_url']) !== trim((string)($before['material_source_url']??''));
NEW_1
        ],
        [<<<'OLD_3'
                    $existing=$this->db->prepare('SELECT id FROM assets WHERE submission_id=? AND (source_url=? OR canonical_url=?) LIMIT 1');$existing->execute([$id,$url,$url]);$assetId=(int)($existing->fetchColumn()?:0);
OLD_3,
<<<'NEW_3'
                    $existing=$this->db->prepare('SELECT id,source_url FROM assets WHERE submission_id=? AND (source_url=? OR canonical_url=?) ORDER BY (source_url=?) DESC,id LIMIT 1');$existing->execute([$id,$url,$url,$url]);$assetRow=$existing->fetch();$assetId=(int)($assetRow['id']??0);
                    $retained=$assetId>0&&(string)$assetRow['source_url']===$url;
NEW_3
        ],
        [<<<'OLD_4'
                    if($assetId>0)$this->db->prepare("UPDATE assets SET kind=?,provider=?,source_url=?,access_status=IF(access_status='secured','secured',?),last_checked_at=NULL,error_message=NULL,is_primary=? WHERE id=?")->execute([$kind,$provider,$url,$access,$index===0?1:0,$assetId]);
OLD_4,
<<<'NEW_4'
                    if($retained)$this->db->prepare('UPDATE assets SET is_primary=? WHERE id=?')->execute([$index===0?1:0,$assetId]);
                    elseif($assetId>0)$this->db->prepare("UPDATE assets SET kind=?,provider=?,source_url=?,access_status=IF(access_status='secured','secured',?),last_checked_at=NULL,error_message=NULL,is_primary=? WHERE id=?")->execute([$kind,$provider,$url,$access,$index===0?1:0,$assetId]);
NEW_4
        ],
        [<<<'OLD_5'
                    $this->queueUniqueDiscoveryJob('verify_external_asset',['asset_id'=>$assetId,'submission_id'=>$id],$assetId);
OLD_5,
<<<'NEW_5'
                    if(!$retained)$this->queueUniqueDiscoveryJob('verify_external_asset',['asset_id'=>$assetId,'submission_id'=>$id],$assetId);
NEW_5
        ],
        [<<<'OLD_6'
SELECT * FROM assets WHERE submission_id=? ORDER BY is_primary DESC,id
OLD_6,
<<<'NEW_6'
SELECT * FROM assets WHERE submission_id=? ORDER BY (TRIM(COALESCE(source_url,\'\'))<>\'\' OR TRIM(COALESCE(canonical_url,\'\'))<>\'\' OR access_status=\'secured\' OR COALESCE(pcloud_file_id,\'\')<>\'\') DESC,is_primary DESC,id
NEW_6
        ],
    ];
    foreach($changes as $index=>[$old,$new]){
        if(substr_count($source,$new)===1&&substr_count($source,$old)===0)continue;
        if(substr_count($source,$old)!==1)throw new RuntimeException('CRM_DEMO_ANCHOR_'.(int)$index);
        $source=str_replace($old,$new,$source);
    }
    $prefix="            if (\$materialChanged) {\n";
    $suffix="                \$received = new DateTimeImmutable((string)\$before['received_at'], new \\DateTimeZone('UTC'));";
    if(substr_count($source,$prefix)!==1||substr_count($source,$suffix)!==1)throw new RuntimeException('CRM_DEMO_RESET_BOUNDARY');
    $start=strpos($source,$prefix)+strlen($prefix);$end=strpos($source,$suffix,$start);
    $region=substr($source,$start,$end-$start);
    $reset=<<<'RESET'
                $removedSql="UPDATE assets SET source_url='',canonical_url=NULL,is_primary=0,access_status='unknown',last_checked_at=NULL,error_message=NULL WHERE submission_id=? AND access_status<>'secured' AND COALESCE(pcloud_file_id,'')='' AND TRIM(COALESCE(source_url,''))<>''";
                if($materialUrls)$removedSql.=' AND source_url NOT IN ('.implode(',',array_fill(0,count($materialUrls),'?')).')';
                $this->db->prepare($removedSql)->execute(array_merge([$id],$materialUrls));
                $this->db->prepare("UPDATE assets SET is_primary=0 WHERE submission_id=? AND access_status<>'secured' AND COALESCE(pcloud_file_id,'')=''")->execute([$id]);
RESET;
    $reset.="\n";
    if($region!==$reset){
        // Accept one literal asset-reset statement, preserving every other installed statement.
        $pattern='~^\\s*\\$this->db->prepare\\("UPDATE assets SET source_url=[^\\r\\n]*"\\)->execute\\(\\[\\$id\\]\\);\\s*$~s';
        if(preg_match($pattern,$region)!==1||strpos($region,'WHERE submission_id=?')===false)throw new RuntimeException('CRM_DEMO_RESET_CHANGED');
        $source=substr($source,0,$start).$reset.substr($source,$end);
    }
    $helper=<<<'HELPER'
    /** Explicit opt-out overrides an old hidden social field or a previous contact profile. */
    private function fluentFormSocialInput(array $data): string
    {
        $reference=mb_strtolower(trim($this->payloadValue($data,['social_reference'])));
        if(in_array($reference,['none','non ho profili social o un sito'],true))return 'Nessun social';
        return trim($this->payloadValue($data,['socialnetwork_demo','social_url','social']));
    }

HELPER;
    if(strpos($source,'function fluentFormSocialInput(')!==false){
        if(substr_count($source,$helper)!==1)throw new RuntimeException('CRM_DEMO_HELPER_CHANGED');
    }else{
        $anchor="    /** @return list<string> */\n    private function extractHttpUrls";
        if(substr_count($source,$anchor)!==1)throw new RuntimeException('CRM_DEMO_HELPER_ANCHOR');
        $source=str_replace($anchor,$helper.$anchor,$source);
    }
    // Earlier live builds exposed only the old primary instead of all retained source links.
    $start=strpos($source,'    public function find(int $id): ?array');
    $end=$start!==false?strpos($source,"\n    public function ",$start+30):false;
    if($start===false||$end===false)throw new RuntimeException('CRM_DEMO_FIND_ANCHOR');
    $find=substr($source,$start,$end-$start);
    $pattern='~^[ \t]*\\$submission\\[\'material_source_url\'\\][ \t]*=[^\\r\\n]+;[ \t]*$~m';
    if(preg_match_all($pattern,$find)!==1)throw new RuntimeException('CRM_DEMO_FIND_ASSIGNMENT');
    $assignment=<<<'ASSIGNMENT'
        $submission['material_source_url'] = implode("\n",array_values(array_unique(array_filter(array_map(static fn(array $row):string=>trim((string)($row['source_url']??'')),$submission['assets'])))));
ASSIGNMENT;
    $find=preg_replace_callback($pattern,static fn()=> $assignment,$find);
    return substr($source,0,$start).$find.substr($source,$end);
}

