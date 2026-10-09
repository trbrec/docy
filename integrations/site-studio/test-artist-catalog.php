<?php
if(PHP_SAPI!=='cli')exit;
define('ABSPATH',__DIR__.'/');
function add_action(...$a){}function add_filter(...$a){}function add_shortcode(...$a){}
function get_option($k,$default=[]){return $k==='trb_promo_takedowns'?['0000000000003']:$default;}
function get_posts($args){$GLOBALS['query']=$args;return [1,2,3,4];}
function apply_filters($n,$v,...$a){return $v;}
function get_post_meta(...$a){return [];}
function get_permalink($id){return 'https://example.test/release/'.$id.'/';}
function get_post_field($field,$id){return 'release-'.$id;}
function home_url($p){return 'https://example.test'.$p;}
function trailingslashit($s){return rtrim($s,'/').'/';}
function wp_strip_all_tags($s){return strip_tags($s);}
class TRB_Promo_Ecosystem{
 const LABELS=['TRB rec'];
 static function data($id){return ['upc'=>$id===4?'0000000000002':'000000000000'.$id,'state'=>'published','date'=>'2020-01-01','label'=>'TRB rec','title'=>'Release '.$id];}
}
require __DIR__.'/trb-site-studio/portal.php';
require __DIR__.'/trb-site-studio/directory.php';
function check($v,$label){if(!$v)throw new RuntimeException($label);echo 'PASS: '.$label."\n";}
check(TRB\Studio\catalog_name_key('Alessio De Franzoni')===TRB\Studio\catalog_name_key('Alessio de Franzoni'),'Verified names connect across capitalization');
check(TRB\Studio\catalog_name_key('  Alessio   de Franzoni ')===TRB\Studio\catalog_name_key('Alessio de Franzoni'),'Whitespace cannot hide a verified catalog identity');
check(TRB\Studio\catalog_name_key('Alessio de Fanzoni')!==TRB\Studio\catalog_name_key('Alessio de Franzoni'),'Different spellings are not merged automatically');
$artist=['id'=>128,'catalog_term_id'=>20,'catalog_term_ids'=>[20,25,25]];
$list=TRB\Studio\artist_releases($artist,['releases'=>[['artist_id'=>128,'upc'=>'0000000000001','title'=>'Portal release']]]);
check($GLOBALS['query']['tax_query'][0]['terms']===[20,25],'Both verified catalog terms are queried once');
check(count($list)===2&&$list[1]['upc']==='0000000000002','Portal/catalog duplicates and taken-down releases stay excluded');
