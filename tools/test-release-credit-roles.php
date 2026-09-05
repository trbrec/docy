<?php
/** Exercise the real server sanitizer with instrument/vocal credits from the incident. */
$source = file_get_contents(__DIR__ . '/../inc/trb-artist-portal.php');
function load_function($name) {
 global $source;
 $start = strpos($source, 'function ' . $name . '(');
 if ($start === false) throw new RuntimeException('Missing function ' . $name);
 $end = strpos($source, "\nfunction ", $start + 1);
 eval(substr($source, $start, $end - $start));
}
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_\\-]/', '', strtolower((string) $v)); }
function absint($v) { return abs((int) $v); }
function get_template_directory() { return dirname(__DIR__); }
foreach (array('trb_portal_genres','trb_portal_contributor_roles','trb_portal_sanitize_contributors','trb_portal_sanitize_writers','trb_portal_sanitize_release_tracks') as $fn) load_function($fn);
function check($ok,$message) { if (!$ok) throw new RuntimeException($message); echo "PASS: $message\n"; }
$tracks = array();
foreach (array(array('Guitar','Vocals'),array('Lead Guitar','Vocals'),array('Drums')) as $i=>$roles) {
 $tracks[] = array('title'=>'Regression track ' . $i,'duration_minutes'=>3,'duration_seconds'=>0,
 'primary_genre'=>'Alternative/Grunge','secondary_genre'=>'','advisory'=>'non_explicit','content_nature'=>'original','rights_basis'=>'owned','audio_status'=>'mastered',
 'credits'=>array('writers'=>array(array('name'=>'Test Writer','roles'=>array('Lyricist','Composer'))),
 'credits'=>array(array('name'=>'Test Performer','role'=>$roles[0],'roles_json'=>json_encode($roles)))));
}
$clean=trb_portal_sanitize_release_tracks($tracks);
check(count($clean)===3,'All three tracks with instrument and vocal credits are accepted');
check(count($clean[0]['credits']['credits'])===2,'Multiple contributor roles are preserved');
check($clean[2]['credits']['credits'][0]['role']==='Drums','Instrument role is preserved');
$all=trb_portal_contributor_roles()['credits'];
foreach(array_keys($all) as $role) check(count(trb_portal_sanitize_contributors(array(array('name'=>'Test','role'=>$role)), $all))===1, 'Accept canonical role ' . $role);
$invalid=$tracks[0]; $invalid['credits']['credits'][0]['role']='NOT_A_ROLE'; $invalid['credits']['credits'][0]['roles_json']='["NOT_A_ROLE"]';
check(trb_portal_sanitize_release_tracks(array($invalid))===array(),'Unknown roles still rejected');
$invalid=$tracks[0];$invalid['credits']['writers']=array();
check(trb_portal_sanitize_release_tracks(array($invalid))===array(),'Missing writers still rejected');
$invalid=$tracks[0];$invalid['primary_genre']='NOT_A_GENRE';
check(trb_portal_sanitize_release_tracks(array($invalid))===array(),'Unknown genres still rejected');
