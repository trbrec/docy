<?php
namespace TRB\Studio;
if(!defined('ABSPATH'))exit;
/** Read only a CLI-published, immutable bundle outside the document root. */
function bundle_root(){return dirname(rtrim(ABSPATH,'/')).'/private/trb-site-studio';}
function bundle_read($path){
 $root=bundle_root();$index=$root.'/current.json';
 if(!is_file($index)||is_link($index))return new \WP_Error('bundle_missing','L’esportazione del portale non è ancora disponibile.');
 $pointer=json_decode((string)file_get_contents($index),true);
 if(!preg_match('/^generation-[a-f0-9]{32}$/D',$pointer['generation']??''))return new \WP_Error('bundle_invalid','Esportazione non valida.');
 $dir=$root.'/'.$pointer['generation'];
 if(is_link($dir)||!is_dir($dir))return new \WP_Error('bundle_invalid','Esportazione non valida.');
 $manifest=$dir.'/snapshot.json';
 if(!is_file($manifest)||is_link($manifest)||filesize($manifest)>20*1024*1024||!hash_equals((string)($pointer['sha256']??''),hash_file('sha256',$manifest)))return new \WP_Error('bundle_integrity','Esportazione incompleta.');
 $snapshot=json_decode((string)file_get_contents($manifest),true);
 $at=strtotime($snapshot['generated_at']??'');
 if(!$at||$at>time()+300||time()-$at>10800)return new \WP_Error('bundle_stale','Esportazione del portale da aggiornare.');
 if($path==='snapshot')return $snapshot;
 if(!preg_match('~^asset/(artist|release)/([1-9][0-9]*)$~D',$path,$match))return new \WP_Error('bundle_path','Materiale non disponibile.');
 $ref=null;$list=$match[1]==='artist'?'artists':'releases';$field=$match[1]==='artist'?'photo':'cover';
 foreach($snapshot[$list]??[] as $item)if(($item['id']??0)===(int)$match[2]){$ref=$item[$field]??null;break;}
 if(!$ref||!preg_match('/^[a-f0-9]{64}$/D',$ref['hash']??''))return new \WP_Error('bundle_asset_missing','Materiale non disponibile.');
 $file=$dir.'/'.$match[1].'-'.$match[2].'-'.$ref['hash'].'.json';
 if(!is_file($file)||is_link($file)||filesize($file)>8*1024*1024)return new \WP_Error('bundle_asset_missing','Materiale non disponibile.');
 $asset=json_decode((string)file_get_contents($file),true);
 return is_array($asset)?$asset:new \WP_Error('bundle_asset_invalid','Materiale non valido.');
}
