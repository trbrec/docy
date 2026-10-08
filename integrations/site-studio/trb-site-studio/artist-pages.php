<?php
namespace TRB\Studio;
if(!defined('ABSPATH'))exit;
/** Native child pages retain their URL and immutable source artist ID across renames. */
function artist_page_id($id){
 $ids=get_posts(['post_type'=>'page','post_status'=>['publish','draft','private'],'posts_per_page'=>2,'fields'=>'ids','meta_key'=>'_trb_studio_artist_id','meta_value'=>(int)$id]);
 return count($ids)===1?(int)$ids[0]:0;
}
function artist_page_url($a){$id=artist_page_id($a['id']);return $id&&get_post_status($id)==='publish'?get_permalink($id):'';}
function artist_gallery_ids($a){
 $o=studio_override('artist',$a['id']);$ids=$o['gallery_ids']??$a['gallery_ids']??[];
 if(!array_key_exists('gallery_ids',$o)&&!empty($a['image_id']))array_unshift($ids,(int)$a['image_id']);
 return array_slice(array_values(array_unique(array_filter(array_map('intval',$ids),fn($id)=>get_post_type($id)==='attachment'&&in_array(get_post_mime_type($id),['image/jpeg','image/png','image/webp'],true)))),0,3);
}
function sync_artist_pages($d){
 if(!destination_site())return true;
 $parent=get_page_by_path('artisti');if(!$parent)return new \WP_Error('artist_parent_missing','La pagina Artisti non è disponibile.');
 $active=[];
 foreach($d['artists']??[] as $raw){
  $a=studio_effective('artist',$raw);$id=artist_page_id($a['id']);
  $content='<!-- wp:shortcode -->[trb_artist_page id="'.(int)$a['id'].'"]<!-- /wp:shortcode -->';
  $values=['post_type'=>'page','post_status'=>'publish','post_parent'=>$parent->ID,'post_title'=>$a['name'],'post_excerpt'=>artist_bio_preview($a['bio'],$a['name'])];
  if(!$id){$values['post_name']=sanitize_title($a['name'])?:'artista-'.$a['id'];$values['post_content']=$content;$id=wp_insert_post(wp_slash($values),true);if(is_wp_error($id))return $id;update_post_meta($id,'_trb_studio_artist_id',(int)$a['id']);update_post_meta($id,'_trb_studio_managed_artist','1');update_post_meta($id,'_wp_page_template','page-no-title');}
  else{if(get_post_meta($id,'_trb_studio_managed_artist',true)!=='1')return new \WP_Error('artist_page_conflict','Pagina artista non gestita: verifica richiesta.');$p=get_post($id);$changed=$p->post_title!==$values['post_title']||$p->post_excerpt!==$values['post_excerpt']||$p->post_status!=='publish';if($changed){$values['ID']=$id;$result=wp_update_post(wp_slash($values),true);if(is_wp_error($result))return $result;}}
  if(!empty($a['image_id'])&&(int)get_post_thumbnail_id($id)!==(int)$a['image_id'])set_post_thumbnail($id,$a['image_id']);
  $active[]=$id;
 }
 foreach(get_posts(['post_type'=>'page','post_status'=>'publish','posts_per_page'=>1000,'fields'=>'ids','meta_key'=>'_trb_studio_managed_artist','meta_value'=>'1']) as $id)if(!in_array((int)$id,$active,true))wp_update_post(['ID'=>$id,'post_status'=>'draft']);
 return true;
}
function artist_page($attrs){
 $id=(int)($attrs['id']??0);$d=directory_data();$a=null;foreach($d['artists']??[] as $item)if($item['id']===$id)$a=$item;
 if(!$a)return '<p>Profilo non disponibile.</p>';
 $edit=studio_mode()?' data-trb-studio-entity="artist:'.$a['id'].'"':'';
 $out='<article class="trb-artist-page"'.$edit.'><a class="trb-artist-back" href="'.esc_url(home_url('/artisti/')).'">← Tutti gli artisti</a><header class="trb-artist-page-heading"><p class="trb-directory-kicker">TRB REC · MUSIC PUBLISHING</p><h1>'.esc_html($a['name']).'</h1></header>';
 $photos=artist_gallery_ids($a);
 if($photos){$out.='<section class="trb-artist-gallery trb-gallery-count-'.count($photos).'" aria-label="Fotografie di '.esc_attr($a['name']).'">';foreach($photos as $n=>$photo){$url=wp_get_attachment_image_url($photo,'full');$out.='<a class="trb-gallery-photo" href="'.esc_url($url).'" target="_blank" rel="noopener" aria-label="'.esc_attr('Apri fotografia '.($n+1).' di '.$a['name']).'">'.($photo===(int)($a['image_id']??0)?studio_artist_image($a):wp_get_attachment_image($photo,'large',false,['alt'=>$a['name'].' · foto '.($n+1),'loading'=>'lazy','class'=>'trb-directory-image'])).'</a>';}$out.='</section>';}
 $out.='<div class="trb-artist-page-story"><section class="trb-artist-biography-full" aria-labelledby="trb-bio-title"><p class="trb-directory-kicker">IL PROFILO</p><h2 id="trb-bio-title">Biografia</h2>'.($a['bio']!==''?'<div class="trb-artist-bio-full">'.plain_paragraphs($a['bio']).'</div>':'<p class="trb-artist-pending">Biografia in aggiornamento.</p>').'</section>';
 if($a['links']){$out.='<aside class="trb-artist-page-links"><h2>Segui e ascolta</h2><nav class="trb-artist-links" aria-label="Profili ufficiali">';foreach($a['links'] as $link){$url=esc_url($link['url'],['https']);if($url)$out.='<a href="'.$url.'" target="_blank" rel="noopener noreferrer">'.esc_html($link['label']).' <span aria-hidden="true">↗</span></a>';}$out.='</nav></aside>';}$out.='</div>';
 $releases=artist_releases($a,$d);usort($releases,fn($a,$b)=>strcmp($b['date'],$a['date']));
 if($releases){$out.='<section class="trb-artist-page-discography" aria-labelledby="trb-discography-title"><header><p class="trb-directory-kicker">LA MUSICA</p><h2 id="trb-discography-title">Discografia <span>'.count($releases).'</span></h2></header><div class="trb-artist-release-grid">';foreach($releases as $r)$out.=release_card($r,[$id=>$a]);$out.='</div></section>';}
 return $out.'</article>';
}
add_shortcode('trb_artist_page',__NAMESPACE__.'\\artist_page');
add_action('wp_head',function(){
 if(!destination_site()||!is_page())return;$source=(int)get_post_meta(get_queried_object_id(),'_trb_studio_artist_id',true);if(!$source)return;
 $a=null;foreach(directory_data()['artists'] as $item)if($item['id']===$source)$a=$item;if(!$a)return;
 $url=get_permalink();$desc=artist_bio_preview($a['bio'],$a['name']);$schema=['@context'=>'https://schema.org','@type'=>'ProfilePage','@id'=>$url.'#profile','url'=>$url,'name'=>$a['name'].' · TRB rec','mainEntity'=>['@type'=>'MusicGroup','@id'=>$url.'#artist','name'=>$a['name'],'url'=>$url,'sameAs'=>array_column($a['links'],'url')]];
 if($desc)$schema['mainEntity']['description']=$desc;
 if(!empty($a['image_id']))$schema['mainEntity']['image']=wp_get_attachment_image_url($a['image_id'],'full');
 echo '<script type="application/ld+json">'.wp_json_encode($schema,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE).'</script>';
 if($desc)echo '<meta name="description" content="'.esc_attr($desc).'">';
 echo '<meta property="og:type" content="profile"><meta property="og:title" content="'.esc_attr($a['name'].' · TRB rec').'"><meta property="og:url" content="'.esc_url($url).'">';
 if(!empty($a['image_id']))echo '<meta property="og:image" content="'.esc_url(wp_get_attachment_image_url($a['image_id'],'full')).'">';
});
