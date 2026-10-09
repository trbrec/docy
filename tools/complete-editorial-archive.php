<?php
/** Preserve published historical articles and add reviewed editorial context through native WordPress revisions. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}ini_set('display_errors','0');
$revision=$argv[1]??'';
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents(dirname(__DIR__).'/.trb-deployed-sha'))!==$revision)exit(2);
define('WP_USE_THEMES',false);require '/home/customer/www/new1.trbrec.com/public_html/wp-load.php';
if(!\TRB\Studio\destination_site())exit(3);
// Use the existing authorized site editor so native KSES preserves approved HTML styles.
$trbEditorialEditors=get_users(['role'=>'administrator','number'=>1,'orderby'=>'ID','order'=>'ASC']);
if(!$trbEditorialEditors||!user_can($trbEditorialEditors[0],'unfiltered_html'))exit(8);
wp_set_current_user($trbEditorialEditors[0]->ID);if(!current_user_can('unfiltered_html'))exit(8);
$items=json_decode(<<<'TRB_EDITORIAL_JSON'
[{"id":922,"slug":"greta-meghan-visione-2026","excerpt":"Visione con Giacomo Voli, la formazione pianistica e il dialogo con poesia e canzone: il percorso di Greta Meghan entra nel catalogo TRB rec.","html":"<section data-trb-editorial-review=\"20261009-greta\"><h2>Il percorso che precede Visione</h2><p>Greta Meghan affianca alla composizione e al pianoforte la scrittura poetica e la canzone. La sua formazione parte dal Conservatorio dell’Aquila e prosegue all’Accademia di Santa Cecilia con Sergio Perticaroli. Questo percorso classico si apre poi a esperienze in cui musica, parola e performance convivono.</p><p>La biografia ufficiale ricorda <em>Piano Universo</em> e le trasmissioni delle sue composizioni nel programma <em>Battiti</em> di Rai Radio Tre. Tra i lavori letterari figurano <em>Grido di Terra</em>, dedicato alla memoria dell’Aquila, e <em>Nuda Poesia – Poèsie Dénudée</em>, raccolta bilingue pubblicata nel 2019 e portata anche in scena.</p><h2>Un dialogo artistico con Giacomo Voli</h2><p>Il coinvolgimento di Giacomo Voli in <em>Visione</em> si inserisce in una collaborazione già documentata dall’artista: Greta Meghan ha scritto canzoni per il cantante, frontman dei Rhapsody of Fire. Il singolo pubblicato il 29 maggio 2026 li riunisce nei crediti di una nuova registrazione, con Greta Meghan come artista principale e Voli come ospite.</p><p>La scheda della release consente di ascoltare il brano e di ritrovare l’edizione TRB rec. Per conoscere l’autrice oltre questo singolo, il profilo artista e il suo sito ufficiale offrono due percorsi complementari: la produzione discografica e il lavoro tra pianoforte, poesia e palco.</p><p class=\"archive-note\">Fonti: <a href=\"https://gretameghan.com/bio-2/\" target=\"_blank\" rel=\"noopener\">biografia ufficiale di Greta Meghan</a>; <a href=\"https://music.apple.com/it/album/visione-feat-giacomo-voli-single/1896354606\" target=\"_blank\" rel=\"noopener\">edizione di Visione su Apple Music</a>; <a href=\"https://www.facebook.com/giacomovoli.it/videos/oggi-esce-visione-in-collaborazione-con-greta-meghan-buon-ascolto/1335713155179996/\" target=\"_blank\" rel=\"noopener\">annuncio della collaborazione di Giacomo Voli</a>.</p></section>"},{"id":915,"slug":"best-seller-2008-primo-brown-ghemon","excerpt":"Una raccolta del 2008 mette in relazione Ghemon, Primo Brown, Mecna, Clementino e altre voci: Best Seller nel primo catalogo TRB rec.","html":"<section data-trb-editorial-review=\"20261009-bestseller\"><h2>Una rete di brani e collaborazioni</h2><p>La raccolta documenta incontri diversi all’interno della scena rap. Nel repertorio figurano <em>Va così</em> di Ghemon, <em>Non mi batti</em> di Amir Issaa, <em>Smokit’up d club</em> di Tormento e <em>Milano street</em> di DJ Enzo con VMD 70’s. I crediti riuniscono inoltre Primo Brown, Mecna, Clementino, Babaman e numerosi altri interpreti.</p><p>La forza documentale di <em>Best Seller</em> sta anche in questa pluralità: singoli artisti, gruppi e collaborazioni trovano posto nella stessa pubblicazione. È una testimonianza del lavoro di un’etichetta indipendente alle proprie origini, costruita intorno a una rete di persone e registrazioni, prima dell’ampliamento del repertorio ad altri generi.</p><h2>Ritrovare l’edizione</h2><p>L’edizione digitale reca il numero di catalogo TRB20080001. L’anno 2008 è confermato dai metadati delle piattaforme e dai brani pubblicati sul canale TRB rec di Audiomack. I nomi riportati sono i crediti della compilation: ogni partecipazione riguarda la registrazione indicata, senza trasformare l’elenco degli ospiti in un elenco di artisti sotto contratto esclusivo.</p><p class=\"archive-note\">Fonti: <a href=\"https://www.beatport.com/release/best-seller-compilation-vol-1-2/6048066\" target=\"_blank\" rel=\"noopener\">edizione e crediti su Beatport</a>; canale TRB rec su Audiomack: <a href=\"https://audiomack.com/trb-rec-di-andrea-tognassi/song/va-cosi\" target=\"_blank\" rel=\"noopener\">Va così</a>, <a href=\"https://audiomack.com/trb-rec-di-andrea-tognassi/song/milano-street\" target=\"_blank\" rel=\"noopener\">Milano street</a>.</p></section>"},{"id":919,"slug":"malacarne-merce-trita-ossa-2015","excerpt":"Merce trita ossa nel percorso di Malacarne: le origini tra punk ed elettronica, il rapporto con TRB rec e le collaborazioni del disco del 2015.","html":"<section data-trb-editorial-review=\"20261009-malacarne\"><h2>Le esperienze prima del rap</h2><p>Il percorso di Malacarne, Emilio Finizio, parte dalla provincia di Reggio Emilia e attraversa linguaggi diversi. Prima dell’attività rap suona in gruppi punk e hardcore, occupandosi di basso e testi, e lavora nell’ambiente elettronico con giradischi e drum machine. Sono esperienze che precedono l’incontro con il rap e con il collettivo Truceklan.</p><p>La ricostruzione pubblicata dalla stampa musicale colloca nel 2010 l’avvio del rapporto con TRB rec e il mixtape <em>Mine antiuomo vol. 1</em>, con la partecipazione di Emis Killa. <em>Merce trita ossa</em> arriva nel 2015, dentro un percorso già segnato dal lavoro dal vivo e dalle collaborazioni con altri rapper e produttori.</p><h2>Una tappa nel catalogo</h2><p>Dopo il disco, il percorso prosegue con il singolo <em>Anti</em> e con <em>Trap ’N’ Metal</em>, pubblicato nel 2018 con TRB rec. Il singolo <em>Santana</em>, prodotto da Emmex, ne anticipa l’uscita; il videoclip è diretto da Jacopo Rossini.</p><p>Leggere <em>Merce trita ossa</em> insieme a queste tappe permette di collocare il disco nella carriera dell’autore, oltre l’elenco dei featuring. Il catalogo conserva l’edizione, mentre la documentazione promozionale ricostruisce il percorso che la precede e quello che segue.</p><p class=\"archive-note\">Fonte del percorso biografico e delle uscite successive: <a href=\"https://www.systemfailurewebzine.com/malacarne-trap-n-metal/\" target=\"_blank\" rel=\"noopener\">presentazione di Malacarne e Trap ’N’ Metal su System Failure</a>. Dati e collaborazioni del disco del 2015: edizione TRB rec collegata in questa pagina.</p></section>"}]
TRB_EDITORIAL_JSON,true);
foreach($items as $item){
 $p=get_post($item['id']);
 if(!$p||$p->post_type!=='post'||$p->post_status!=='publish'||$p->post_name!==$item['slug'])exit(4);
 $marker='data-trb-editorial-review="20261009-';
 if(str_contains($p->post_content,$marker)&&!str_contains($p->post_content,'<style>')){
  // Recover only the previous approved article body after a filtered CLI save.
  $trbOriginalBody='';
  foreach(wp_get_post_revisions($p->ID) as $trbRevision){
   if(str_contains($trbRevision->post_content,'<style>')&&!str_contains($trbRevision->post_content,$marker)&&str_contains($trbRevision->post_content,'class="trb-archive-story"')){$trbOriginalBody=$trbRevision->post_content;break;}
  }
  if($trbOriginalBody===''){
   // The filtered save retained the known CSS text; restore its missing wrapper exactly.
   $trbPrefix='<!-- wp:html -->';$trbArticle='<article class="trb-archive-story">';
   $trbArticlePos=strpos($p->post_content,$trbArticle);
   if(!str_starts_with($p->post_content,$trbPrefix.'.trb-archive-story{')||$trbArticlePos===false||substr_count($p->post_content,$trbArticle)!==1||substr_count($p->post_content,$item['html'])!==1)exit(9);
   $trbCSS=substr($p->post_content,strlen($trbPrefix),$trbArticlePos-strlen($trbPrefix));
   if(strpbrk($trbCSS,'<>')!==false||!str_ends_with(trim($trbCSS),'}'))exit(9);
   $trbOriginalBody=$trbPrefix.'<style>'.$trbCSS.'</style>'.substr($p->post_content,$trbArticlePos);
   $trbOriginalBody=str_replace($item['html'],'',$trbOriginalBody);
  }
  $p->post_content=$trbOriginalBody;
 }
 if(str_contains($p->post_content,$marker))continue;
 if(!str_contains($p->post_content,'<style>'))exit(10);
 if($item['id']===922){
  $p->post_content=preg_replace('~<h2>Una nuova uscita del catalogo</h2><p>.*?</p>~s','',$p->post_content,1,$trbRemoved);
  if($trbRemoved!==1)exit(11);
 }
 $anchor='<p class="archive-note">';$pos=strpos($p->post_content,$anchor);
 if($pos===false||substr_count($p->post_content,$anchor)!==1)exit(5);
 $next=substr($p->post_content,0,$pos).$item['html'].substr($p->post_content,$pos);
 $next=str_replace('redatto il 6 ottobre 2026','aggiornato il 9 ottobre 2026',$next);
 $r=wp_update_post(wp_slash(['ID'=>$p->ID,'post_content'=>$next,'post_excerpt'=>$item['excerpt']]),true);
 if(is_wp_error($r)||!$r)exit(6);
 if(get_post_field('post_content',$p->ID)!==$next)exit(7);
}
