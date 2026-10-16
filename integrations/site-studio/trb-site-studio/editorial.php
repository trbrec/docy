<?php
namespace TRB\Studio;
if (!defined('ABSPATH')) exit;

/** Public editorial profiles are independent from portal login/contract entitlements. */
function editorial_entry_allowed($entry){return ($entry['basis']??'')==='trb';}
function editorial_artists($artists){
 $extra=(array)get_option('trb_studio_editorial_artists',[]);
 foreach($extra as $entry){
  if(!editorial_entry_allowed($entry))continue;
  $found=false;
  foreach($artists as &$artist){
   if((int)$artist['id']===(int)$entry['id']||catalog_name_key($artist['name'])===catalog_name_key($entry['name'])){
    // A later portal registration must not duplicate an existing public profile.
    // Keep the editorial public ID, while retaining the portal identity separately.
    if($artist['id']!==$entry['id'])$artist['portal_id']=$artist['id'];$artist['id']=$entry['id'];
    foreach(['bio','links','image_id','gallery_ids'] as $field)if(empty($artist[$field])&&!empty($entry[$field]))$artist[$field]=$entry[$field];
    $artist['catalog_term_ids']=array_values(array_unique(array_filter(array_merge((array)($artist['catalog_term_ids']??[]),(array)($entry['catalog_term_ids']??[])))));
    if(empty($artist['catalog_term_id']))$artist['catalog_term_id']=$entry['catalog_term_id']??0;
    $found=true;break;
   }
  }unset($artist);
  if(!$found)$artists[]=$entry;
 }
 return $artists;
}
function editorial_directory($data){
 $data['artists']=editorial_artists($data['artists']??[]);
 $aliases=[];foreach($data['artists'] as $artist)if(!empty($artist['portal_id']))$aliases[$artist['portal_id']]=$artist['id'];
 foreach($data['releases']??[] as $i=>$release)if(isset($aliases[$release['artist_id']]))$data['releases'][$i]['artist_id']=$aliases[$release['artist_id']];
 return $data;
}
function editorial_validate($data,$existing=[]){
 if(!is_array($data)||count($data)>100)return new \WP_Error('editorial_invalid','Importa al massimo 100 schede alla volta.');
 $out=array_filter($existing,__NAMESPACE__.'\\editorial_entry_allowed');
 foreach($data as $entry){
  if(!is_array($entry))return new \WP_Error('editorial_invalid','Scheda non valida.');
  $name=trim((string)($entry['name']??''));$key=catalog_name_key($name);
  if(!$name||strlen($name)>240||str_contains($name,'@')||preg_match('/^(?:trb_audit_|utente_|test\b|collaudo\b)/i',$name))return new \WP_Error('editorial_name','Nome pubblico non valido.');
  $patch=studio_override_validate('artist',array_intersect_key($entry,array_flip(['name','bio','links','image_id','gallery_ids'])));
  if(is_wp_error($patch))return $patch;
  if(empty($patch['bio'])||empty($patch['image_id']))return new \WP_Error('editorial_materials','Ogni scheda deve avere biografia e fotografia verificate: '.$name.'.');
  $basis=$entry['basis']??'';$worked=$entry['last_work']??'';$term_id=(int)($entry['catalog_term_id']??0);
  if($basis!=='trb')return new \WP_Error('editorial_basis','Il roster comprende esclusivamente artisti ufficiali TRB rec. DDB e collaborazioni recenti non sono criteri di inclusione.');
  if($term_id){$term=get_term($term_id,'trb_catalog_artist');if(!$term||is_wp_error($term)||catalog_name_key($term->name)!==$key)return new \WP_Error('editorial_identity','Il riferimento di catalogo non corrisponde all’artista: '.$name.'.');}
  if($basis==='trb'){
   $verified=false;
   if($term_id&&class_exists('TRB_Promo_Ecosystem'))foreach(get_posts(['post_type'=>'trb_release','post_status'=>'publish','posts_per_page'=>500,'fields'=>'ids','tax_query'=>[['taxonomy'=>'trb_catalog_artist','field'=>'term_id','terms'=>[$term_id]]]]) as $post_id){$release=\TRB_Promo_Ecosystem::data($post_id);if(($release['state']??'')==='published'&&in_array($release['label']??'',\TRB_Promo_Ecosystem::LABELS,true)&&!in_array($release['upc']??'',(array)get_option('trb_promo_takedowns',[]),true)){$verified=true;break;}}
   if(!$verified)return new \WP_Error('editorial_label','Non è disponibile una pubblicazione TRB attiva: '.$name.'.');
  }
  $source=sanitize_textarea_field((string)($entry['source']??''));if(!$source||strlen($source)>2000)return new \WP_Error('editorial_source','Indica la fonte dei materiali: '.$name.'.');
  $id=0;foreach($out as $saved)if(catalog_name_key($saved['name'])===$key)$id=(int)$saved['id'];
  if(!$id){$id=max(array_merge([1000000000],array_map(fn($saved)=>(int)$saved['id'],$out)))+1;}
  $out[$id]=array_merge(['name'=>$name,'bio'=>'','links'=>[],'image_id'=>0,'gallery_ids'=>[]],$patch,['id'=>$id,'name_source'=>'editorial','catalog_term_id'=>$term_id,'catalog_term_ids'=>$term_id?[$term_id]:[],'basis'=>$basis,'last_work'=>$worked,'source'=>$source]);
 }
 return $out;
}
add_action('admin_menu',function(){if(destination_site())add_submenu_page('trb-visual-editor','Roster pubblico','Roster pubblico','manage_options','trb-public-roster',__NAMESPACE__.'\\editorial_admin');});
// Retire profiles imported under superseded criteria, preserving a private backup.
add_action('admin_init',function(){
 if(!destination_site()||!admin_permission())return;
 $previous=(array)get_option('trb_studio_editorial_artists',[]);
 $restricted=array_filter($previous,__NAMESPACE__.'\\editorial_entry_allowed');
 if($restricted===$previous)return;
 update_option('trb_studio_editorial_excluded_20261011',$previous,false);
 update_option('trb_studio_editorial_artists',$restricted,false);
 $synced=sync_artist_pages(directory_data());
 if(is_wp_error($synced)){update_option('trb_studio_editorial_artists',$previous,false);return;}
 if(function_exists('sg_cachepress_purge_cache'))sg_cachepress_purge_cache();
});
function editorial_admin(){
 if(!admin_permission()||!destination_site())return;
 $notice='';$error=false;
 if(isset($_POST['trb_editorial_save'])){
  check_admin_referer('trb_editorial_roster');
  $data=json_decode(wp_unslash($_POST['profiles']??''),true);
  $validated=editorial_validate($data,(array)get_option('trb_studio_editorial_artists',[]));
  if(is_wp_error($validated)){$notice=$validated->get_error_message();$error=true;}
  else{
   $previous=get_option('trb_studio_editorial_artists',[]);update_option('trb_studio_editorial_previous',$previous,false);update_option('trb_studio_editorial_artists',$validated,false);
   $synced=sync_artist_pages(directory_data());
   if(is_wp_error($synced)){update_option('trb_studio_editorial_artists',$previous,false);sync_artist_pages(directory_data());$notice=$synced->get_error_message();$error=true;}
   else{if(function_exists('sg_cachepress_purge_cache'))sg_cachepress_purge_cache();$notice='Schede pubblicate. Foto e biografie sono modificabili nell’Editor sito e restano protette dalla sincronizzazione.';}
  }
 }
 echo '<div class="wrap"><h1>Roster pubblico</h1><p>Il roster comprende esclusivamente artisti ufficiali TRB rec. DDB, collaborazioni recenti e precedenti eccezioni sono esclusi. Questa selezione non modifica account, contratti o gruppi del portale.</p>';
 if($notice)echo '<div class="notice '.($error?'notice-error':'notice-success').'"><p>'.esc_html($notice).'</p></div>';
 echo '<table class="widefat striped"><thead><tr><th>Artista</th><th>Origine</th><th>Scheda</th></tr></thead><tbody>';
 foreach(directory_data()['artists'] as $artist)echo '<tr><td>'.esc_html($artist['name']).'</td><td>'.esc_html(($artist['name_source']??'')==='editorial'?'Selezione editoriale':'Portale artisti').'</td><td><a href="'.esc_url(add_query_arg('trb-edit','1',artist_page_url($artist))).'">Modifica foto e biografia</a></td></tr>';
 echo '</tbody></table><h2>Importa schede verificate</h2><p>Importazione amministrativa di materiali già verificati. Le schede senza biografia, fotografia o fonte non vengono pubblicate.</p><form method="post">';wp_nonce_field('trb_editorial_roster');
 echo '<label for="trb-editorial-profiles">Schede da importare</label><textarea class="large-text code" rows="12" id="trb-editorial-profiles" name="profiles" required></textarea>';
 submit_button('Pubblica schede','primary','trb_editorial_save');echo '</form></div>';
}
