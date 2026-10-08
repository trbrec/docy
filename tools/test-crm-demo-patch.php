<?php
require __DIR__.'/crm-demo-source-patch.php';
$source=file_get_contents(dirname(__DIR__).'/tests/fixtures/crm-demo-source.php');
$patched=trb_crm_demo_source_patch($source);
if(strpos($patched,'Preserve this unrelated future CRM logic.')===false)throw new RuntimeException('Unrelated source changed');
if(trb_crm_demo_source_patch($patched)!==$patched)throw new RuntimeException('Patch is not idempotent');
if(strpos($patched,'$social = $this->fluentFormSocialInput($data);')===false)throw new RuntimeException('Opt-out patch missing');
$legacy=preg_replace('/^.*\$submission\[\x27material_source_url\x27\] = .*;$/m',"        \$submission['material_source_url'] = (string)(\$primaryAsset['source_url'] ?? '');",$source);
$legacyPatched=trb_crm_demo_source_patch($legacy);
if(strpos($legacyPatched,'array_map(static fn(array $row)')===false)throw new RuntimeException('Legacy primary-only getter not repaired');
$variant=str_replace("SET source_url='',access_status","SET source_url='',is_primary=0,access_status",$source);
if(trb_crm_demo_source_patch($variant)!==$patched)throw new RuntimeException('Literal reset variant not normalized');
$extra=str_replace("            if (\$materialChanged) {\n","            if (\$materialChanged) {\n                custom_future_behavior();\n",$source);
$failed=false;try{trb_crm_demo_source_patch($extra);}catch(RuntimeException){$failed=true;}
if(!$failed)throw new RuntimeException('Unrelated installed reset behavior accepted');
foreach([$source . $source, str_replace("\$materialChanged = array_key_exists('material_url', \$input);",'changed-anchor',$source)] as $bad){
 $failed=false;try{trb_crm_demo_source_patch($bad);}catch(RuntimeException){$failed=true;}
 if(!$failed)throw new RuntimeException('Changed or duplicate source anchor accepted');
}
$temp=tempnam(sys_get_temp_dir(),'crm-demo-patch-');file_put_contents($temp,$patched);
exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temp).' 2>&1',$out,$code);unlink($temp);
if($code!==0)throw new RuntimeException('Patched source does not parse');
echo "CRM Demo patch: exact anchors, legacy getter, unrelated source, idempotence and syntax passed.\n";
