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
add_action('admin_bar_menu',function($bar){if(destination_site()&&admin_permission())$bar->add_node(['id'=>'trb-studio','title'=>'Editor sito','href'=>add_query_arg('trb-edit','1',home_url(wp_unslash($_SERVER['REQUEST_URI']??'/')))]);},90);
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

/** Whole-site visual editing. Managed content uses separate, persistent presentation overrides. */
function studio_mode(){return destination_site() && admin_permission() && isset($_GET['trb-edit']);}
function studio_permission(){return destination_site() && admin_permission();}
function studio_version($value){return hash('sha256',is_string($value)?$value:wp_json_encode($value));}
function studio_sources($id){
 $out=[];$p=get_post((int)$id);
 if($p&&in_array($p->post_type,['page','post'],true)&&current_user_can('edit_post',$p->ID))$out[$p->ID]=$p;
 foreach(get_posts(['post_type'=>'wp_template_part','post_status'=>'publish','posts_per_page'=>50]) as $part)
  if(in_array($part->post_name,['header','footer'],true)&&current_user_can('edit_post',$part->ID))$out[$part->ID]=$part;
 return $out;
}
function studio_dom($html,$tag){
 $doc=new \DOMDocument('1.0','UTF-8');$old=libxml_use_internal_errors(true);
 $doc->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
 libxml_clear_errors();libxml_use_internal_errors($old);
 return [$doc,$doc->getElementsByTagName($tag)->item(0)];
}
function studio_items($raw){
 // Mask executable and form markup while retaining exact byte offsets.
 $safe=preg_replace_callback('~<(script|style|form)\b[^>]*>.*?</\1>~is',fn($m)=>str_repeat(' ',strlen($m[0])),$raw);
 $items=[];$seen=[];
 preg_match_all('~<(h[1-6]|p|a|li|span|summary|button|label)\b([^>]*)>(.*?)</\1>~is',$safe,$matches,PREG_SET_ORDER|PREG_OFFSET_CAPTURE);
 foreach($matches as $m){
  if(preg_match('~<(?:div|section|article|ul|ol|li|p|h[1-6]|img|svg|input|button|form|iframe)\b|\[[a-z_]+(?:\s|\])~i',$m[3][0])||!trim(wp_strip_all_tags($m[3][0])))continue;
  $items[]=['tag'=>strtolower($m[1][0]),'kind'=>'text','outer'=>$m[0][0],'offset'=>$m[0][1],'html'=>$m[3][0],'text'=>html_entity_decode(wp_strip_all_tags($m[3][0]),ENT_QUOTES|ENT_HTML5,'UTF-8')];
 }
 preg_match_all('~<(img|section|article|div|header|footer|nav|figure|details)\b[^>]*>~is',$safe,$matches,PREG_SET_ORDER|PREG_OFFSET_CAPTURE);
 foreach($matches as $m)$items[]=['tag'=>strtolower($m[1][0]),'kind'=>strtolower($m[1][0])==='img'?'image':'layout','outer'=>$m[0][0],'offset'=>$m[0][1],'text'=>'','html'=>''];
 usort($items,fn($a,$b)=>$a['offset']<=>$b['offset']);
 foreach($items as &$i){
  $hash=hash('sha256',$i['outer']);$n=$seen[$hash]??0;$seen[$hash]=$n+1;$i['key']=$hash.'-'.$n;
  [$doc,$node]=studio_dom($i['outer'],$i['tag']);$i['attrs']=[];
  if($node)foreach(['href','src','alt','class','id','style','aria-label'] as $a)if($node->hasAttribute($a))$i['attrs'][$a]=$node->getAttribute($a);
  $i['label']=$i['kind']==='layout'?trim($i['tag'].' · '.($i['attrs']['id']??$i['attrs']['class']??'Sezione')):($i['text']?:($i['attrs']['alt']??'Immagine'));
 }unset($i);
 return $items;
}
function studio_styles($changes){
 $ranges=['margin-top'=>[0,160],'margin-bottom'=>[0,160],'padding-top'=>[0,160],'padding-bottom'=>[0,160],'padding-left'=>[0,96],'padding-right'=>[0,96],'max-width'=>[160,1920],'font-size'=>[10,96],'border-radius'=>[0,64]];
 $out=[];
 foreach($changes as $key=>$v){
  if(isset($ranges[$key])&&is_numeric($v)&&$v>=$ranges[$key][0]&&$v<=$ranges[$key][1])$out[$key]=(int)$v.'px !important';
  elseif(in_array($key,['color','background-color'],true)&&is_string($v)&&preg_match('/^#[a-f0-9]{6}$/iD',$v))$out[$key]=$v.' !important';
  elseif($key==='object-position'&&is_numeric($v)&&$v>=0&&$v<=100)$out[$key]='center '.(int)$v.'% !important';
  elseif($key==='object-fit'&&in_array($v,['cover','contain'],true))$out[$key]=$v.' !important';
  elseif($key==='brightness'&&is_numeric($v)&&$v>=90&&$v<=125)$out['filter']='brightness('.((int)$v/100).') !important';
  else return new \WP_Error('invalid_style','Valore grafico fuori intervallo.',['status'=>400]);
 }
 return $out;
}
function studio_url($url){
 if(!is_string($url)||strlen($url)>2048)return false;
 $url=trim($url);
 if(preg_match('~^(?:#[\w-]*|/(?!/)[^\x00-\x20]*)$~uD',$url))return $url;
 if(!preg_match('~^(?:https?://|mailto:|tel:)~i',$url)||wp_parse_url($url,PHP_URL_USER)||wp_parse_url($url,PHP_URL_PASS))return false;
 $safe=esc_url_raw($url,['https','http','mailto','tel']);return $safe?:false;
}
function studio_photo($id){
 if(!is_numeric($id)||$id<1||get_post_type((int)$id)!=='attachment'||!in_array(get_post_mime_type((int)$id),['image/jpeg','image/png','image/webp'],true))return false;
 return wp_get_attachment_image_src((int)$id,'large');
}
function studio_apply($raw,$changes){
 $items=array_column(studio_items($raw),null,'key');$patches=[];$seen=[];
 if(!is_array($changes)||count($changes)>300)return new \WP_Error('invalid_changes','Troppe modifiche in un salvataggio.',['status'=>400]);
 foreach($changes as $c){
  $key=$c['key']??'';if(!isset($items[$key])||isset($seen[$key]))return new \WP_Error('stale_element','Un elemento è cambiato: ricarica prima di salvare.',['status'=>409]);$seen[$key]=true;$i=$items[$key];
  [$doc,$node]=studio_dom($i['outer'],$i['tag']);if(!$node)return new \WP_Error('invalid_element','Elemento non disponibile.',['status'=>400]);
  $html=$i['html'];
  if(array_key_exists('html',$c)){
   if($i['kind']!=='text'||!is_string($c['html'])||strlen($c['html'])>30000||preg_match('~\[[a-zA-Z_][^\]]*\]~',$c['html']))return new \WP_Error('invalid_text','Testo non valido.',['status'=>400]);
   $allow=['strong'=>[],'em'=>[],'b'=>[],'i'=>[],'br'=>[]];
   $html=wp_kses($c['html'],$allow,['http','https','mailto']);
  }
  if(array_key_exists('href',$c)){
   $url=studio_url($c['href']);if($i['tag']!=='a'||$url===false)return new \WP_Error('invalid_link','Collegamento non valido.',['status'=>400]);$node->setAttribute('href',$url);
  }
  if(isset($c['image_id'])){
   $photo=studio_photo($c['image_id']);if($i['kind']!=='image'||!$photo)return new \WP_Error('invalid_photo','Scegli una fotografia JPG, PNG o WebP dalla libreria media.',['status'=>400]);
   $node->setAttribute('src',$photo[0]);$node->setAttribute('width',(string)$photo[1]);$node->setAttribute('height',(string)$photo[2]);$node->removeAttribute('srcset');$node->removeAttribute('sizes');
  }
  if(isset($c['alt'])){if($i['kind']!=='image'||!is_string($c['alt'])||strlen($c['alt'])>500)return new \WP_Error('invalid_alt','Descrizione immagine non valida.',['status'=>400]);$node->setAttribute('alt',sanitize_text_field($c['alt']));}
  $styles=studio_styles($c['spacing']??[]);if(is_wp_error($styles))return $styles;
  if($styles){$old=[];foreach(explode(';',$node->getAttribute('style')) as $part){$p=explode(':',$part,2);if(count($p)===2)$old[trim($p[0])]=trim($p[1]);}$old=array_merge($old,$styles);$style='';foreach($old as $k=>$v)$style.=$k.':'.$v.';';$node->setAttribute('style',$style);}
  $attrs='';foreach($node->attributes as $a)$attrs.=' '.$a->name.'="'.esc_attr($a->value).'"';
  $replacement='<'.$i['tag'].$attrs.'>'.($i['kind']==='text'?$html.'</'.$i['tag'].'>':'');
  $patches[]=['offset'=>$i['offset'],'length'=>strlen($i['outer']),'value'=>$replacement];
 }
 usort($patches,fn($a,$b)=>$a['offset']<=>$b['offset']);$end=-1;
 foreach($patches as $p){if($p['offset']<$end)return new \WP_Error('overlapping_edits','Salva prima il testo del contenitore, poi modifica i suoi elementi interni.',['status'=>409]);$end=$p['offset']+$p['length'];}
 foreach(array_reverse($patches) as $p)$raw=substr_replace($raw,$p['value'],$p['offset'],$p['length']);
 return $raw;
}
function studio_mark($html,$post){
 if(!studio_mode()||!$post)return $html;
 $items=studio_items($post->post_content);$seen=[];$patches=[];
 foreach($items as $i){
  $needle=$i['outer'];$start=$seen[$needle]??0;$at=strpos($html,$needle,$start);if($at===false)continue;$seen[$needle]=$at+strlen($needle);
  $opening=strpos($needle,'>');if($opening===false)continue;
  $patches[]=['offset'=>$at+$opening,'value'=>' data-trb-studio-source="'.(int)$post->ID.'" data-trb-studio-key="'.esc_attr($i['key']).'"'];
 }
 usort($patches,fn($a,$b)=>$b['offset']<=>$a['offset']);
 foreach($patches as $p)$html=substr_replace($html,$p['value'],$p['offset'],0);
 return $html;
}
add_filter('the_content',function($html){return studio_mark($html,get_post());},8);
add_filter('render_block_core/template-part',function($html,$block){
 if(!studio_mode())return $html;
 $slug=$block['attrs']['slug']??'';
 foreach(studio_sources(0) as $p)if($p->post_name===$slug)return studio_mark($html,$p);
 return $html;
},20,2);

function studio_override($kind,$id){return (array)get_option('trb_studio_override_'.$kind.'_'.(int)$id,[]);}
function studio_override_validate($kind,$patch){
 $allow=$kind==='artist'?['name','bio','links','image_id','gallery_ids','position','position_x','brightness']:['title','presentation','links','image_id','gallery_ids','position','position_x','brightness'];$out=[];
 if(!is_array($patch)||count($patch)>8)return new \WP_Error('invalid_override','Modifiche non valide.',['status'=>400]);
 foreach($patch as $k=>$v){
  if(!in_array($k,$allow,true))return new \WP_Error('invalid_override','Campo non modificabile.',['status'=>400]);
  if(in_array($k,['name','title','bio','presentation'],true)){
   if(!is_string($v)||strlen($v)>(in_array($k,['name','title'],true)?240:30000)||(in_array($k,['name','title'],true)&&!trim($v)))return new \WP_Error('invalid_text','Testo troppo lungo o titolo vuoto.',['status'=>400]);
   $out[$k]=sanitize_textarea_field($v);
  }elseif($k==='links'){
   if(!is_array($v)||count($v)>20)return new \WP_Error('invalid_links','Massimo 20 collegamenti.',['status'=>400]);$out[$k]=[];
   foreach($v as $link){$url=studio_url($link['url']??null);if(!$url||!is_string($link['label']??null)||!trim($link['label'])||strlen($link['label'])>120)return new \WP_Error('invalid_links','Indica nome e indirizzo di ogni collegamento.',['status'=>400]);$out[$k][]=['label'=>sanitize_text_field($link['label']),'url'=>$url];}
  }elseif($k==='gallery_ids'){if($kind!=='artist'||!is_array($v)||count($v)>3)return new \WP_Error('invalid_gallery','Scegli fino a tre fotografie.',['status'=>400]);$out[$k]=[];foreach($v as $photo){if(!studio_photo($photo))return new \WP_Error('invalid_photo','Fotografia non valida.',['status'=>400]);$out[$k][]=(int)$photo;}$out[$k]=array_values(array_unique($out[$k]));
  }elseif($k==='image_id'){if(!studio_photo($v))return new \WP_Error('invalid_photo','Fotografia non valida.',['status'=>400]);$out[$k]=(int)$v;
  }elseif($k==='position'||$k==='position_x'){if(!is_numeric($v)||$v<0||$v>100)return new \WP_Error('invalid_position','Inquadratura non valida.',['status'=>400]);$out[$k]=(int)$v;
  }elseif($k==='brightness'){if(!is_numeric($v)||$v<90||$v>125)return new \WP_Error('invalid_brightness','Luminosità non valida.',['status'=>400]);$out[$k]=(int)$v;}
 }
 return $out;
}
function studio_effective($kind,$entity){return array_merge($entity,studio_override($kind,$entity['id']));}
function studio_artist_image($a){
 $o=studio_override('artist',$a['id']);$style='';
 $positionX=$o['position_x']??($a['id']===67?20:50);$position=$o['position']??($a['id']===177?23:($a['id']===45?27:35));$style.='object-position:'.$positionX.'% '.$position.'% !important;';
 $brightness=$o['brightness']??($a['id']===45?112:100);$style.='filter:brightness('.($brightness/100).') !important;';
 return !empty($a['image_id'])?wp_get_attachment_image($a['image_id'],'large',false,['alt'=>$a['name'],'loading'=>'lazy','class'=>'trb-directory-image','style'=>$style]):public_image(0,$a['name']);
}
function studio_entity_list(){
 $raw=get_option('trb_studio_directory',['artists'=>[],'releases'=>[]]);$out=[];
 foreach($raw['artists']??[] as $a){$o=studio_override('artist',$a['id']);$a=studio_effective('artist',$a);$out[]=['kind'=>'artist','id'=>$a['id'],'name'=>$a['name'],'bio'=>$a['bio'],'links'=>$a['links'],'image_id'=>$a['image_id']??0,'image'=>wp_get_attachment_image_url($a['image_id']??0,'large')?:'','gallery_ids'=>artist_gallery_ids($a),'gallery'=>array_map(fn($id)=>['id'=>$id,'url'=>wp_get_attachment_image_url($id,'large')],artist_gallery_ids($a)),'url'=>artist_page_url($a),'position_x'=>$o['position_x']??($a['id']===67?20:50),'position'=>$o['position']??($a['id']===177?23:($a['id']===45?27:35)),'brightness'=>$o['brightness']??($a['id']===45?112:100),'version'=>studio_version($o),'manual'=>(bool)$o];}
 if(class_exists('TRB_Promo_Ecosystem'))foreach(get_posts(['post_type'=>'trb_release','post_status'=>['publish','future'],'posts_per_page'=>1000]) as $p){
  $d=\TRB_Promo_Ecosystem::data($p->ID);if(($d['state']??'')!=='published')continue;$o=studio_override('catalog',$p->ID);$assets=apply_filters('trb_promo_release_assets',[],$d,$p->ID);
  $image=(int)($o['image_id']??$assets['cover_id']??0);$out[]=['kind'=>'catalog','id'=>$p->ID,'name'=>$d['title']??$p->post_title,'title'=>$d['title']??$p->post_title,'presentation'=>$o['presentation']??$assets['press_text']??'','links'=>$o['links']??[],'image_id'=>$image,'image'=>wp_get_attachment_image_url($image,'large')?:($assets['cover_url']??''),'position'=>$o['position']??50,'brightness'=>$o['brightness']??100,'version'=>studio_version($o),'manual'=>(bool)$o,'url'=>get_permalink($p->ID)];
 }
 return $out;
}
function studio_form_items($data){
 $out=[];
 $walk=function($node,$path,$parent='')use(&$walk,&$out){
  if(!is_array($node))return;
  if(isset($node['element'])&&($node['settings']['visible']??true)!==false&&$node['element']!=='input_hidden'){
   $name=$node['attributes']['name']??'';$name=$parent&&$name?$parent.'['.$name.']':$name;
   foreach([['settings','label'],['settings','help_message'],['attributes','placeholder'],['settings','button_ui','text']] as $suffix){
    $v=$node;foreach($suffix as $k)$v=is_array($v)&&array_key_exists($k,$v)?$v[$k]:null;
    if(is_string($v)){$p=array_merge($path,$suffix);$key=studio_version($p);$out[$key]=['key'=>$key,'path'=>$p,'value'=>$v,'label'=>($node['settings']['label']??$name?:'Pulsante invio').' · '.end($suffix),'name'=>$name,'property'=>end($suffix)];}
   }
   if(isset($node['fields'])&&$name)$parent=$name;
  }
  foreach($node as $key=>$child)if(is_array($child)&&!in_array($key,['validation_rules','conditional_logics','editor_options','advanced_options'],true))$walk($child,array_merge($path,[$key]),$parent);
 };
 $walk($data,[]);return $out;
}
function studio_form_patch($raw,$changes){
 $data=json_decode($raw,true);if(!is_array($data))return new \WP_Error('invalid_form','Modulo non disponibile.',['status'=>400]);
 $items=studio_form_items($data);$seen=[];
 foreach($changes as $c){$key=$c['key']??'';if(!isset($items[$key])||isset($seen[$key])||!is_string($c['value']??null)||strlen($c['value'])>2000)return new \WP_Error('invalid_form_field','Testo del modulo non valido.',['status'=>400]);$seen[$key]=true;
  $p=$items[$key]['path'];$ref=&$data;foreach($p as $k)$ref=&$ref[$k];$ref=sanitize_textarea_field($c['value']);unset($ref);
 }
 return wp_json_encode($data);
}
function studio_forms($sources){
 if(!class_exists('\FluentForm\App\Models\Form'))return [];
 $ids=[];foreach($sources as $p){preg_match_all('/\[fluentform[^\]]*id=["\x27]?(\d+)/',$p->post_content,$m);$ids=array_merge($ids,$m[1]);}$out=[];
 foreach(array_unique($ids) as $id){$f=\FluentForm\App\Models\Form::find((int)$id);if(!$f)continue;$out[]=['id'=>(int)$id,'title'=>$f->title,'version'=>studio_version($f->form_fields),'items'=>array_values(studio_form_items(json_decode($f->form_fields,true)?:[])),'url'=>admin_url('admin.php?page=fluent_forms&route=editor&form_id='.(int)$id)];}
 return $out;
}
function studio_manifest($request){
 $sources=studio_sources((int)$request['id']);$out=[];
 foreach($sources as $p){$items=studio_items($p->post_content);foreach($items as &$i){unset($i['offset'],$i['outer']);}unset($i);$out[]=['id'=>$p->ID,'label'=>$p->post_type==='wp_template_part'?($p->post_name==='header'?'Intestazione e menu':'Footer'):'Pagina','version'=>studio_version($p->post_content),'items'=>$items];}
 $pages=[];foreach(get_posts(['post_type'=>['page','post'],'post_status'=>'publish','posts_per_page'=>500,'orderby'=>'title','order'=>'ASC']) as $p)if(current_user_can('edit_post',$p->ID))$pages[]=['id'=>$p->ID,'title'=>$p->post_title,'url'=>add_query_arg('trb-edit','1',get_permalink($p->ID))];
 $history=get_option('trb_studio_visual_history',[]);$recent=[];foreach(array_slice(array_reverse($history),0,10) as $h)$recent[]=['id'=>$h['id'],'at'=>$h['at'],'label'=>$h['label']];
 $r=rest_ensure_response(['sources'=>$out,'entities'=>studio_entity_list(),'forms'=>studio_forms($sources),'pages'=>$pages,'history'=>$recent]);$r->header('Cache-Control','private, no-store');return $r;
}
function studio_save($request){
 if(!wp_verify_nonce($request->get_header('X-WP-Nonce'),'wp_rest'))return new \WP_Error('nonce_invalid','Sessione scaduta. Ricarica la pagina.',['status'=>403]);
 $data=$request->get_json_params();if(!is_array($data))return new \WP_Error('invalid_request','Richiesta non valida.',['status'=>400]);
 $lock=get_option('trb_studio_visual_lock');if($lock&&time()-(int)$lock<180)return new \WP_Error('save_running','Salvataggio già in corso.',['status'=>409]);
 if($lock)delete_option('trb_studio_visual_lock');if(!add_option('trb_studio_visual_lock',time(),'','no'))return new \WP_Error('save_running','Salvataggio già in corso.',['status'=>409]);
 try{
  $writes=[];$sources=studio_sources((int)$request['id']);$entities=[];foreach(studio_entity_list() as $e)$entities[$e['kind'].':'.$e['id']]=$e;
  if(($data['action']??'')==='undo'){
   $history=get_option('trb_studio_visual_history',[]);$entry=null;foreach($history as $h)if($h['id']===($data['history_id']??''))$entry=$h;
   if(!$entry)return new \WP_Error('history_missing','Versione precedente non disponibile.',['status'=>404]);
   foreach($entry['writes'] as $w){$current=studio_read_value($w);if(studio_version($current)!==$w['after_version'])return new \WP_Error('edit_conflict','Dopo questo salvataggio il contenuto è cambiato. Ripristino sospeso.',['status'=>409]);$writes[]=array_merge($w,['before'=>$current,'after'=>$w['before']]);}
  }else{
   foreach($data['sources']??[] as $change){
    $id=(int)($change['id']??0);$p=$sources[$id]??null;if(!$p||studio_version($p->post_content)!==($change['version']??''))return new \WP_Error('edit_conflict','La pagina è stata modificata altrove. Ricarica prima di salvare.',['status'=>409]);
    $editLock=get_post_meta($id,'_edit_lock',true);if($editLock){[$t,$u]=array_pad(explode(':',$editLock),2,0);if((int)$u!==get_current_user_id()&&time()-(int)$t<150)return new \WP_Error('locked','Un altro amministratore sta modificando questa pagina.',['status'=>409]);}
    $new=studio_apply($p->post_content,$change['changes']??null);if(is_wp_error($new))return $new;$writes[]=['type'=>'post','id'=>$id,'before'=>$p->post_content,'after'=>$new];
   }
   foreach($data['entities']??[] as $change){
    $kind=$change['kind']??'';$id=(int)($change['id']??0);$key=$kind.':'.$id;if(!isset($entities[$key]))return new \WP_Error('entity_missing','Scheda non disponibile.',['status'=>404]);
    $old=studio_override($kind,$id);if(studio_version($old)!==($change['version']??''))return new \WP_Error('edit_conflict','La scheda è cambiata. Ricarica prima di salvare.',['status'=>409]);
    $patch=!empty($change['reset'])?[]:studio_override_validate($kind,$change['patch']??null);if(is_wp_error($patch))return $patch;
    $writes[]=['type'=>'option','key'=>'trb_studio_override_'.$kind.'_'.$id,'before'=>$old,'after'=>!empty($change['reset'])?[]:array_merge($old,$patch)];
   }
   $forms=array_column(studio_forms($sources),null,'id');
   foreach($data['forms']??[] as $change){$id=(int)($change['id']??0);if(!isset($forms[$id])||$forms[$id]['version']!==($change['version']??''))return new \WP_Error('edit_conflict','Il modulo è cambiato. Ricarica prima di salvare.',['status'=>409]);$f=\FluentForm\App\Models\Form::find($id);$raw=$f->form_fields;$new=studio_form_patch($raw,$change['changes']??[]);if(is_wp_error($new))return $new;$writes[]=['type'=>'form','id'=>$id,'before'=>$raw,'after'=>$new];}
  }
  if(!$writes||count($writes)>100)return new \WP_Error('nothing_to_save','Nessuna modifica da salvare.',['status'=>400]);
  $seen=[];foreach($writes as $w){$k=$w['type'].':'.($w['key']??$w['id']);if(isset($seen[$k]))return new \WP_Error('duplicate_change','Modifica duplicata.',['status'=>400]);$seen[$k]=true;}
  $applied=[];
  foreach($writes as &$w){
   $result=studio_write_value($w,$w['after']);if(is_wp_error($result)){foreach(array_reverse($applied) as $done)studio_write_value($done,$done['before']);return $result;}
   $w['after_version']=studio_version($w['after']);$applied[]=$w;
  }unset($w);
  $history=get_option('trb_studio_visual_history',[]);$history[]=['id'=>wp_generate_uuid4(),'at'=>gmdate('c'),'label'=>(($data['action']??'')==='undo'?'Ripristino':'Modifiche').' · '.count($writes).' aree','writes'=>$writes];update_option('trb_studio_visual_history',array_slice($history,-20),false);
  $synced=sync_artist_pages(directory_data());if(is_wp_error($synced))return $synced;
  if(function_exists('sg_cachepress_purge_cache'))sg_cachepress_purge_cache();
  return ['saved'=>true,'message'=>'Modifiche pubblicate.'];
 }finally{delete_option('trb_studio_visual_lock');}
}
function studio_read_value($w){
 if($w['type']==='post')return get_post($w['id'])->post_content;
 if($w['type']==='form')return \FluentForm\App\Models\Form::find($w['id'])->form_fields;
 return (array)get_option($w['key'],[]);
}
function studio_write_value($w,$value){
 if($w['type']==='post'){if(!get_post($w['id'])||!current_user_can('edit_post',$w['id']))return new \WP_Error('edit_forbidden','Pagina non modificabile.',['status'=>403]);wp_save_post_revision($w['id']);$r=wp_update_post(wp_slash(['ID'=>$w['id'],'post_content'=>$value]),true);if(is_wp_error($r))return $r;clean_post_cache($w['id']);}
 elseif($w['type']==='form'){
  $r=\FluentForm\App\Models\Form::where('id',$w['id'])->update(['form_fields'=>$value,'updated_at'=>current_time('mysql')]);
  if(!$r&&studio_read_value($w)!==$value)return new \WP_Error('form_save_failed','Il modulo non è stato salvato.',['status'=>500]);
 }else {update_option($w['key'],$value,false);if(studio_version(studio_read_value($w))!==studio_version($value))return new \WP_Error('save_failed','Salvataggio non confermato.',['status'=>500]);}
 return true;
}
add_action('rest_api_init',function(){
 if(!destination_site())return;
 register_rest_route('trb-studio/v1','/visual/(?P<id>\d+)',['methods'=>'GET','permission_callback'=>__NAMESPACE__.'\\studio_permission','callback'=>__NAMESPACE__.'\\studio_manifest']);
 register_rest_route('trb-studio/v1','/visual/(?P<id>\d+)',['methods'=>'POST','permission_callback'=>__NAMESPACE__.'\\studio_permission','callback'=>__NAMESPACE__.'\\studio_save']);
});
add_filter('trb_promo_release_data',function($data,$id){$o=studio_override('catalog',$id);if(isset($o['title']))$data['title']=$o['title'];return $data;},30,2);
add_filter('trb_promo_release_assets',function($assets,$data,$id){$o=studio_override('catalog',$id);if(isset($o['image_id'])){$assets['cover_id']=$o['image_id'];unset($assets['cover_url']);}if(isset($o['presentation']))$assets['press_text']=plain_paragraphs($o['presentation']);return $assets;},30,3);
add_filter('trb_promo_artist_profile',function($profile,$term){
 $d=get_option('trb_studio_directory',[]);foreach($d['artists']??[] as $a)if(($a['catalog_term_id']??0)===(int)$term->term_id){$o=studio_override('artist',$a['id']);if(isset($o['bio']))$profile['bio']=plain_paragraphs($o['bio']);if(isset($o['image_id']))$profile['photo_id']=$o['image_id'];if(isset($o['links']))$profile['links']=$o['links'];break;}return $profile;
},30,2);
add_action('wp_enqueue_scripts',function(){
 if(!studio_mode())return;
 wp_enqueue_media();
 wp_enqueue_style('trb-studio-editor',plugins_url('editor.css',__FILE__),[],VERSION.'.'.filemtime(__DIR__.'/editor.css'));
 wp_enqueue_script('trb-studio-editor',plugins_url('editor.js',__FILE__),[],VERSION.'.'.filemtime(__DIR__.'/editor.js'),true);
 $id=get_queried_object_id();$p=get_post($id);if(!$p||!in_array($p->post_type,['page','post','trb_release'],true))$id=(int)get_option('page_on_front');
 $exit=remove_query_arg(['trb-edit','trb-edit-source'],home_url(wp_unslash($_SERVER['REQUEST_URI']??'/')));
 wp_localize_script('trb-studio-editor','TRBStudio',['endpoint'=>rest_url('trb-studio/v1/visual/'),'postId'=>$id,'nonce'=>wp_create_nonce('wp_rest'),'exit'=>$exit,'native'=>admin_url('site-editor.php')]);
},30);
add_action('admin_menu',function(){
 if(!destination_site())return;
 add_menu_page('Editor sito TRB','Editor sito','manage_options','trb-visual-editor',function(){
  echo '<div class="wrap"><h1>Editor sito TRB</h1><p>Apri una pagina, clicca un testo, una foto o una sezione e modifica il contenuto con anteprima immediata.</p><p>Le correzioni manuali agli artisti e alle release restano protette dagli aggiornamenti automatici.</p>';
  foreach(get_posts(['post_type'=>'page','post_status'=>'publish','posts_per_page'=>100,'orderby'=>'title','order'=>'ASC']) as $p)echo '<p><a class="button button-primary" href="'.esc_url(add_query_arg('trb-edit','1',get_permalink($p->ID))).'">Modifica '.esc_html($p->post_title).'</a></p>';
  echo '</div>';
 },'dashicons-edit-page',3);
});
