<?php
require __DIR__.'/../inc/trb-symphonic-credits.php';
require __DIR__.'/../inc/trb-symphonic-genres.php';
require __DIR__.'/test-release-credit-roles.php';
$roles=trb_symphonic_roles();
check(array_map('count',$roles)===array('writers'=>43,'performers'=>818,'engineering'=>115),'Complete observed Symphonic catalogue');
$t=$tracks[0];unset($t['credits']['credits']);
$t['credits']['performers']=[['name'=>'Test Singer','role'=>'Vocals']];
$t['credits']['engineering']=[['name'=>'Test Producer','role'=>'Producer']];
check(!trb_symphonic_credit_errors([$t]),'Complete vocal credits pass');
$c=trb_portal_sanitize_release_tracks([$t],true);
check(count($c)===1&&count($c[0]['credits']['credits'])===2,'Separate groups survive server sanitization and legacy export');
foreach(['writers','performers','engineering'] as $group){$bad=$t;$bad['credits'][$group]=[];check((bool)trb_symphonic_credit_errors([$bad]),'Missing '.$group.' blocked');}
foreach(['Lyricist','Vocals','Producer'] as $role){$bad=$t;if($role==='Lyricist')$bad['credits']['writers'][0]['roles']=['Composer'];elseif($role==='Vocals')$bad['credits']['performers'][0]['role']='Electric Guitar';else $bad['credits']['engineering'][0]['role']='Mixing Engineer';check((bool)trb_symphonic_credit_errors([$bad]),'Missing '.$role.' blocked');}
$i=$t;$i['advisory']='no_lyrics';$i['credits']['writers'][0]['roles']=['Composer'];$i['credits']['performers'][0]['role']='Piano';check(!trb_symphonic_credit_errors([$i]),'Instrumental does not require lyricist or vocals');
$bad=$t;$bad['credits']['performers'][0]['role']='Producer';check((bool)trb_symphonic_credit_errors([$bad]),'Cross-category role blocked');
$bad=$t;$bad['credits']['engineering'][0]['name']='';check((bool)trb_symphonic_credit_errors([$bad]),'Empty contributor name blocked');
$before=serialize($tracks);trb_portal_sanitize_release_tracks($tracks);check(serialize($tracks)===$before,'Historical input never mutated');
foreach($roles as $group=>$options)foreach($options as $name=>$id){$probe=$t;if($group==='writers')$probe['credits'][$group][]=['name'=>'Test Writer','roles'=>[$name]];else $probe['credits'][$group][]=['name'=>'Test Person','role'=>$name];if(trb_symphonic_credit_errors([$probe])||count(trb_portal_sanitize_release_tracks([$probe],true))!==1)throw new Exception('Role lost: '.$group.' '.$name);}
check(true,'Every observed role accepted only in its group');

$genres=trb_symphonic_genres();
check(count($genres)===48 && array_sum(array_map('count',$genres))===498,'Complete observed genre/subgenre pairs');
$genreTrack=$t;$genreTrack['primary_genre']='Alternative';$genreTrack['secondary_genre']='Indie Rock';
check(!trb_symphonic_genre_errors([$genreTrack]) && count(trb_portal_sanitize_release_tracks([$genreTrack],true,true))===1,'Valid Symphonic genre pair accepted');
$genreTrack['secondary_genre']='Alternative Pop';
check((bool)trb_symphonic_genre_errors([$genreTrack]) && !trb_portal_sanitize_release_tracks([$genreTrack],true,true),'Cross-genre subgenre rejected');
$genreTrack['secondary_genre']='';
check((bool)trb_symphonic_genre_errors([$genreTrack]),'Missing subgenre rejected');
check(count(trb_portal_sanitize_release_tracks($tracks))===count($tracks),'Historical genre validation is unchanged');
