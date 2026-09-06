<?php
function add_filter(...$args){}
require __DIR__.'/../inc/trb-rights-owner-review.php';
function check_review($ok,$message){if(!$ok)throw new RuntimeException($message);}
$snapshot=['tracks'=>[
 ['index'=>0,'findings'=>[]],
 ['index'=>1,'findings'=>['Brano 2: corrispondenza riferimento A']],
 ['index'=>2,'findings'=>['Brano 3: corrispondenza riferimento B']]
]];
$r=trb_rights_selected_findings($snapshot,['1']);
check_review($r['indexes']===[1]&&$r['findings']===['Brano 2: corrispondenza riferimento A'],'Unselected track leaked into artist email');
$r=trb_rights_selected_findings($snapshot,[2,1,2]);
check_review($r['indexes']===[1,2]&&count($r['findings'])===2,'Duplicate selections not deduplicated');
foreach([[],[-1],['1.0'],['1<script>'],[0],[3]] as $invalid){
 $failed=false;try{trb_rights_selected_findings($snapshot,$invalid);}catch(RuntimeException $e){$failed=true;}
 check_review($failed,'Invalid or evidence-free selection admitted');
}
echo "PASS per-track isolation, duplicate selection and invalid index rejection\n";
