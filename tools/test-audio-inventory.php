<?php
define('ABSPATH',__DIR__.'/');function add_action(...$a){}function add_filter(...$a){}
require dirname(__DIR__).'/inc/trb-release-analysis.php';
function verify_inventory($ok){if(!$ok)throw new RuntimeException('Invalid audio inventory result');}
$tracks=array_fill(0,24,[]);
$files=[];for($i=0;$i<24;$i++)$files[]=['kind'=>'audio','track'=>$i];
verify_inventory([]===trb_analysis_audio_inventory_errors($tracks,$files));
verify_inventory(in_array('RELEASE_TRACKS_MISSING',trb_analysis_audio_inventory_errors([],[]),true));
verify_inventory(in_array('AUDIO_TRACK_MISSING',trb_analysis_audio_inventory_errors($tracks,array_slice($files,0,23)),true));
$duplicate=$files;$duplicate[]=$files[0];
verify_inventory(in_array('AUDIO_TRACK_DUPLICATED',trb_analysis_audio_inventory_errors($tracks,$duplicate),true));
foreach([-1,24,0.5,'1x',null] as $bad){$invalid=$files;$invalid[0]['track']=$bad;verify_inventory(in_array('AUDIO_TRACK_INDEX_INVALID',trb_analysis_audio_inventory_errors($tracks,$invalid),true));}
echo "Audio inventory completeness passed.\n";
