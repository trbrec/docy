<?php
namespace TRB\Studio;
if (!defined('ABSPATH')) exit;
function editor_permission($request){
 $p=get_post((int)$request['id']);
 return destination_site() && admin_permission() && $p && in_array($p->post_type,['page','post','wp_template_part'],true) && current_user_can('edit_post',$p->ID);
}
/** Only literal HTML blocks; dynamic forms, scripts and native blocks are untouched. */
function editable_items($raw){
 $items=[];
 preg_match_all('~<!-- wp:html -->(.*?)<!-- /wp:html -->~s',$raw,$blocks);
 foreach($blocks[1] as $block){
  $safe=preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is','',$block);
  preg_match_all('~<(h[1-6]|p|a|li|span)\b([^>]*)>(.*?)</\1>~is',$safe,$matches,PREG_SET_ORDER);
  foreach($matches as $m){
   if(preg_match('~<(?:div|section|article|ul|ol|li|p|h[1-6]|img|svg|input|button|form|iframe)\b|\[[a-z_]+(?:\s|\])~i',$m[3]))continue;
   if(!trim(wp_strip_all_tags($m[3])) || substr_count($raw,$m[0])!==1)continue;
   $key=hash('sha256',$m[0]);
   $items[$key]=['key'=>$key,'tag'=>strtolower($m[1]),'text'=>html_entity_decode(wp_strip_all_tags($m[3]),ENT_QUOTES|ENT_HTML5,'UTF-8'),'html'=>$m[3],'outer'=>$m[0],'attrs'=>$m[2]];
  }
 }
 return $items;
}
function editor_manifest($request){
 $p=get_post((int)$request['id']);$items=editable_items($p->post_content);
 foreach($items as &$item){unset($item['outer'],$item['attrs']);}unset($item);
 $response=rest_ensure_response(['id'=>$p->ID,'version'=>hash('sha256',$p->post_content),'items'=>array_values($items)]);
 $response->header('Cache-Control','private, no-store');return $response;
}
function editor_apply($raw,$changes){
 if(!class_exists('DOMDocument'))return new \WP_Error('dom_missing','Estensione PHP DOM richiesta.',['status'=>503]);
 $items=editable_items($raw);$replacements=[];$seen=[];
 if(!is_array($changes)||count($changes)>150)return new \WP_Error('invalid_changes','Modifiche non valide.',['status'=>400]);
 foreach($changes as $change){
  $key=$change['key']??'';
  if(!isset($items[$key])||isset($seen[$key]))return new \WP_Error('stale_element','Un elemento è cambiato: ricarica la pagina.',['status'=>409]);
  $seen[$key]=true;$item=$items[$key];
  $html=$change['html']??$item['html'];
  if(!is_string($html)||strlen($html)>30000)return new \WP_Error('invalid_text','Testo troppo lungo.',['status'=>400]);
  $allowed=['strong'=>[],'em'=>[],'b'=>[],'i'=>[],'br'=>[],'a'=>['href'=>true,'title'=>true,'rel'=>true,'target'=>true]];
  if($item['tag']==='a')unset($allowed['a']);
  if($html!==$item['html'])$html=wp_kses($html,$allowed,['http','https','mailto']);
  if(preg_match('~\[[a-zA-Z_][^\]]*\]~',$html))return new \WP_Error('shortcode_not_allowed','Inserisci solo testo e formattazione.',['status'=>400]);
  $doc=new \DOMDocument('1.0','UTF-8');$prev=libxml_use_internal_errors(true);
  $doc->loadHTML('<?xml encoding="utf-8"?><body>'.$item['outer'].'</body>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
  libxml_clear_errors();libxml_use_internal_errors($prev);
  $node=$doc->getElementsByTagName($item['tag'])->item(0);
  if(!$node)return new \WP_Error('invalid_element','Elemento non modificabile.',['status'=>400]);
  $styles=[];foreach(explode(';',$node->getAttribute('style')) as $part){$pair=explode(':',$part,2);if(count($pair)===2)$styles[trim($pair[0])]=trim($pair[1]);}
  $ranges=['margin-top'=>[0,160],'margin-bottom'=>[0,160],'padding-top'=>[0,160],'padding-bottom'=>[0,160],'padding-left'=>[0,96],'padding-right'=>[0,96],'max-width'=>[240,1440]];
  foreach(($change['spacing']??[]) as $prop=>$value){
   if(!isset($ranges[$prop])||!is_numeric($value)||$value<$ranges[$prop][0]||$value>$ranges[$prop][1])return new \WP_Error('invalid_spacing','Spaziatura fuori intervallo.',['status'=>400]);
   $styles[$prop]=(int)$value.'px !important';
  }
  if($styles){$s='';foreach($styles as $k=>$v)$s.=$k.':'.$v.';';$node->setAttribute('style',$s);}
  $attrs='';foreach($node->attributes as $attr)$attrs.=' '.$attr->name.'="'.esc_attr($attr->value).'"';
  $replacements[$item['outer']]='<'.$item['tag'].$attrs.'>'.$html.'</'.$item['tag'].'>';
 }
 return strtr($raw,$replacements);
}
function editor_save($request){
 if(!wp_verify_nonce($request->get_header('X-WP-Nonce'),'wp_rest'))return new \WP_Error('nonce_invalid','Sessione scaduta.',['status'=>403]);
 $id=(int)$request['id'];$p=get_post($id);$data=$request->get_json_params();
 if(!hash_equals(hash('sha256',$p->post_content),(string)($data['version']??'')))return new \WP_Error('edit_conflict','La pagina è stata modificata altrove. Ricarica prima di salvare.',['status'=>409]);
 $lock=get_post_meta($id,'_edit_lock',true);if($lock){[$time,$user]=array_pad(explode(':',$lock),2,0);if((int)$user!==get_current_user_id()&&time()-(int)$time<150)return new \WP_Error('locked','Un altro amministratore sta modificando questa pagina.',['status'=>409]);}
 $new=editor_apply($p->post_content,$data['changes']??null);if(is_wp_error($new))return $new;
 wp_save_post_revision($id);
 $result=wp_update_post(wp_slash(['ID'=>$id,'post_content'=>$new]),true);if(is_wp_error($result))return $result;
 clean_post_cache($id);if(function_exists('sg_cachepress_purge_cache'))sg_cachepress_purge_cache();
 return ['saved'=>true,'version'=>hash('sha256',$new),'edit_url'=>get_edit_post_link($id,'raw')];
}
add_action('admin_bar_menu',function($bar){if(destination_site()&&is_singular(['page','post'])&&admin_permission()&&current_user_can('edit_post',get_queried_object_id()))$bar->add_node(['id'=>'trb-studio','title'=>'Modifica visiva','href'=>add_query_arg('trb-edit','1',get_permalink())]);},90);
add_action('template_redirect',function(){if(destination_site()&&isset($_GET['trb-edit'])&&admin_permission()){if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);nocache_headers();}});
add_action('wp_enqueue_scripts',function(){
 if(!destination_site()||!isset($_GET['trb-edit'])||!admin_permission()||!is_singular(['page','post']))return;
 wp_enqueue_style('trb-studio-editor',plugins_url('editor.css',__FILE__),[],VERSION);
 wp_enqueue_script('trb-studio-editor',plugins_url('editor.js',__FILE__),[],VERSION,true);
 $sources=[['id'=>get_queried_object_id(),'label'=>'Pagina','scope'=>'main']];
 foreach(get_posts(['post_type'=>'wp_template_part','post_status'=>'publish','posts_per_page'=>50]) as $part){if(in_array($part->post_name,['header','footer'],true))$sources[]=['id'=>$part->ID,'label'=>$part->post_name==='header'?'Intestazione':'Footer','scope'=>$part->post_name==='header'?'header.site-header,header[role=banner]':'footer.site-footer,footer[role=contentinfo]'];}
 $selected=$sources[0];foreach($sources as $source)if($source['id']===(int)($_GET['trb-edit-source']??0))$selected=$source;
 wp_localize_script('trb-studio-editor','TRBStudio',['endpoint'=>rest_url('trb-studio/v1/editor/'),'postId'=>$selected['id'],'scope'=>$selected['scope'],'sources'=>$sources,'nonce'=>wp_create_nonce('wp_rest'),'exit'=>get_permalink(),'native'=>get_edit_post_link($selected['id'],'raw')]);
});
