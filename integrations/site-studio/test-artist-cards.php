<?php
if (PHP_SAPI !== 'cli') exit;
define('ABSPATH',__DIR__.'/');
function add_action(...$args){} function add_shortcode(...$args){} function add_filter(...$args){}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
function esc_attr($s){return esc_html($s);}
function esc_url($s,$protocols=[]){return str_starts_with($s,'https://')?$s:'';}
function wp_strip_all_tags($s){return strip_tags($s);}
function wpautop($s){return '<p>'.str_replace("\n\n",'</p><p>',$s).'</p>';}
function get_option($key,$fallback=[]){return $GLOBALS['options'][$key]??$fallback;}
function wp_get_attachment_image($id,$size,$icon,$attrs){return '<img class="trb-directory-image" width="600" height="400" alt="'.esc_attr($attrs['alt']).'" src="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27600%27 height=%27400%27%3E%3Crect width=%27600%27 height=%27400%27 fill=%27%23a4c7b1%27/%3E%3C/svg%3E">';}
require __DIR__.'/trb-site-studio/directory.php';
function check($condition,$label){if(!$condition)throw new RuntimeException($label);fwrite(STDERR,"PASS: $label\n");}
$short="Una voce tra musica e teatro.";
check(TRB\Studio\artist_bio_preview($short)===$short,'Short biographies remain intact');
$long=str_repeat("Musica, città e libertà. ",50)."\n\nLa seconda parte resta disponibile, con è, à e 😊.";
$preview=TRB\Studio\artist_bio_preview($long);
preg_match_all('/./us',$preview,$characters);
check(count($characters[0])<=240&&str_ends_with($preview,'…'),'Long preview is at most 240 Unicode characters');
check(preg_match('//u',$preview)===1,'Accents and emoji remain valid UTF-8');
check(TRB\Studio\artist_bio_preview("ARTISTA\n\n".$short,'Artista')===$short,'Repeated artist headings are removed from the preview only');
$full=TRB\Studio\artist_biography(['bio'=>$long,'name'=>'Duo']);
check(str_contains($full,'<details class="trb-artist-biography">')&&str_contains($full,'La seconda parte resta disponibile'),'Full biography is accessible behind a native disclosure');
$unsafe=TRB\Studio\artist_biography(['bio'=>'<script>alert(1)</script> testo','name'=>'<img onerror=alert(1)>']);
check(!str_contains($unsafe,'<script>')&&!str_contains($unsafe,'<img onerror='),'Both the full biography and artist labels are escaped');
check(!str_contains(TRB\Studio\artist_biography(['bio'=>'','name'=>'Artista']),'<details'),'Missing biography does not create a dead disclosure');
$artists=[['id'=>1,'name'=>'Alberto Puviani','bio'=>$long,'links'=>[['label'=>'Spotify','url'=>'https://open.spotify.com/artist/example']],'image_id'=>1],['id'=>2,'name'=>'Edmondo Romano e Simona Fasano','bio'=>$short,'links'=>[],'image_id'=>1],['id'=>3,'name'=>'Artista in aggiornamento','bio'=>'','links'=>[],'image_id'=>0],['id'=>4,'name'=>'Alessio de Franzoni','bio'=>$long,'links'=>[],'image_id'=>1]];
$options=['trb_studio_directory'=>['generated_at'=>gmdate('c'),'artists'=>$artists,'releases'=>[]]];
$html=TRB\Studio\roster();
check(substr_count($html,'class="trb-artist-profile"')===4,'Every eligible artist renders one consistent card');
check(substr_count($html,'class="trb-artist-bio-preview')===4,'Short, long and missing biographies share the same preview slot');
if(in_array('--fixture',$argv,true))echo '<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>*,*:before,*:after{box-sizing:border-box}body{margin:0;background:#f2f4ef;font:16px Arial,sans-serif}summary{display:list-item}.screen-reader-text{position:absolute;clip:rect(1px,1px,1px,1px);width:1px;height:1px;overflow:hidden}body.page-id-936 .trb-directory{width:100%;padding-inline:48px;grid-template-columns:repeat(2,minmax(0,1fr))}body.page-id-936 .trb-artist-profile>div:last-child{padding:32px}body.page-id-936 .trb-artist-profile h3{font-size:32px}body.page-id-936 .trb-artist-profile p{font-size:17px}body.page-id-936 .trb-directory>.trb-artist-profile{align-self:start}@media(max-width:680px){body.page-id-936 .trb-directory{grid-template-columns:1fr;padding-inline:20px}}</style><style>'.file_get_contents(__DIR__.'/trb-site-studio/directory.css').'</style><body class="page-id-936"><main>'.$html.'</main></body></html>';
