<?php
/** Restore only reviewed public artistic material; preserve all existing profile documents. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
$revision=$argv[1]??'';
if(!preg_match('/^[a-f0-9]{40}$/D',$revision)||trim((string)@file_get_contents(dirname(__DIR__).'/.trb-deployed-sha'))!==$revision)exit(2);
define('WP_USE_THEMES',false);require '/home/customer/www/artist.trbrec.com/public_html/wp-load.php';
$items=[['id'=>46,'expected_display'=>'Emiliano Di Meo','name'=>'Emiliano Di Meo','photo'=>'/Upload files - TRB rec/Media/Photos/2 files from Emiliano Di Meo on Oct 12, 2023/Emiliano Di Meo 02.jpg','bio'=>'Emiliano Di Meo è un compositore, produttore, musicista e cantautore romano. Il suo percorso unisce canzone, hip hop, elettronica e musica per immagini, con un lavoro che attraversa scrittura, arrangiamento, campionamento e produzione.

Alla fine degli anni Novanta entra nello studio Nonsense come assistente di Riccardo Sinigallia. Il passaggio dalla registrazione analogica al lavoro con i computer diventa un laboratorio di ricerca sui suoni e sui linguaggi musicali. In questo contesto collabora a produzioni legate a cantautori, gruppi e artisti della scena hip hop italiana.

La sua attività comprende contributi a La descrizione di un attimo dei Tiromancino, al lavoro di Roberto Angelini e alle produzioni di Riccardo Sinigallia. Ha inoltre lavorato con Frankie hi-nrg mc, Duke Montana e Gel, svolgendo ruoli di produzione, registrazione, mix e programmazione secondo i singoli progetti.

Accanto alla discografia, sviluppa una lunga attività come coautore di colonne sonore per cinema e televisione. Tra i titoli riportati nella sua biografia figurano Amatemi, Ride, Lo spietato, Magari e Maledetta primavera. La ricerca sul rapporto tra suono e racconto accompagna così tanto le canzoni quanto la musica composta per lo schermo.','bio_source'=>'/Upload files - TRB rec/Media/Biographies/1 file from Emiliano Di Meo on Nov 30, 2023/BIOGRAFIA EMILIANO DI MEO ok_doc.docx'],['id'=>128,'expected_display'=>'Alessio de Franzoni','name'=>'Alessio de Franzoni','photo'=>'/Discografia - TRB rec/FaDe - Alessio de Fanzoni/01. Materiale aggiornato - foto e bio/defra defra.jpeg','bio'=>'Alessio de Franzoni è un pianista, compositore e artista multidisciplinare friulano, attivo da oltre trent’anni nel panorama musicale e coreutico italiano e internazionale. Diplomato in pianoforte al Conservatorio Tomadini di Udine, ha approfondito la formazione con il Diploma Accademico di II Livello e un Master in Composizione di Musica per Film all’Università di Udine.

Dal 2012 è Maestro Accompagnatore per la Danza presso il Liceo Coreutico dell’Educandato Uccellis di Udine. La sua attività unisce pianoforte, composizione, musica per la danza, canzone e ricerca elettronica, con collaborazioni con coreografi e istituzioni internazionali.

Ha pubblicato numerosi album di musica originale e per la danza ed è fondatore del duo AccorDòs con la fisarmonicista Sara Rigo. Le sue composizioni hanno ricevuto riconoscimenti in concorsi nazionali e internazionali. È direttore artistico dell’associazione culturale “Parcè no?” di Montenars e autore di musical e spettacoli che intrecciano musica, parole, immagini e danza.','bio_source'=>'/Discografia - TRB rec/FaDe - Alessio de Fanzoni/01. Materiale aggiornato - foto e bio/alessio de franzoni bio riscritta - riassunta.docx'],['id'=>73,'expected_display'=>'Alessandro Solidoro','name'=>'Solidoro','photo'=>'/Discografia - TRB rec/Solidoro/Bestemmiare/Photo/FOTO1.JPG','bio'=>'Solidoro è un musicista e compositore attivo tra scrittura musicale, performance dal vivo e musica applicata. Diplomato in Composizione, affianca all’attività di autore quella di tastierista turnista, collaborando con diversi progetti e contesti artistici.

Il suo percorso si sviluppa tra palco e studio, con una ricerca costante sul rapporto tra struttura e libertà espressiva e sul dialogo tra musica e immagine. È attivo nella composizione di colonne sonore per videogiochi, ambito in cui la musica assume un ruolo narrativo centrale, pensata per accompagnare l’azione, il movimento e le scelte del giocatore.

Tra composizione, arrangiamento e musica per immagini, Solidoro porta avanti una visione coerente e contemporanea, in cui il suono non è semplice accompagnamento ma parte integrante del racconto, contribuendo alla costruzione dell’esperienza complessiva.','bio_source'=>'/Discografia - TRB rec/Solidoro/Proverò a descriverti/PROMO/INPUT_ARTISTA/Comunicato-stampa-e-bio.docx'],['id'=>41,'expected_display'=>'Edmondo Romano','name'=>'Edmondo Romano e Simona Fasano','photo'=>'/Discografia - DDB/Edmondo Romano  Simona Fasano/ES SÉ female side (EP)/PROMO/INPUT_ARTISTA/Edmondo-Romano-Simona-Fasano-ESSE-Foto-1.jpg','photo_hash'=>'a8e7678d9c50434fa985b9603247cd48f4c82d91d373026126203292bd7b18ca','bio'=>'Edmondo Romano e Simona Fasano uniscono musica e teatro in un progetto nato dal loro incontro a Genova nel 2006. Il loro percorso intreccia composizione, voce, parola, danza e ricerca sonora, con spettacoli, recital e concerti realizzati nell’ambito della Compagnia Teatro Nudo. Dal 2012 la ricerca musicale prosegue anche attraverso Eden Production, tra musica per immagini, sperimentazione, world music e linguaggi contemporanei.

Edmondo Romano è polistrumentista, compositore e produttore. La sua attività attraversa musica sperimentale, etnica, world, minimalista e contemporanea. Ha partecipato a oltre 140 incisioni discografiche e lavorato a colonne sonore cinematografiche, reading poetici, teatro e danza, portando la propria musica in numerosi concerti internazionali. Nei suoi progetti cura anche la produzione artistica e gli aspetti audio, video e grafici.

Simona Fasano è attrice e cantante. La sua ricerca esplora il linguaggio del corpo, del suono e della parola. Nel 2007 ha fondato la Compagnia Teatro Nudo, per la quale realizza spettacoli occupandosi anche di drammaturgia e regia.

Nel progetto ES/SÉ, nato da questo dialogo artistico, strumenti antichi e moderni, vocalità ed elettronica si incontrano in una ricerca che mette al centro suono, parola e interiorità.','bio_source'=>'/Discografia - DDB/Edmondo Romano  Simona Fasano/ES SÉ (Album)/PROMO/INPUT_ARTISTA/Edmondo-Romano-Simona-Fasano-ES_SE-aggiornato.pdf']];
$uploads=wp_upload_dir();$base=trailingslashit($uploads['basedir']).'trb-artist-private';
if(!wp_mkdir_p($base))exit(3);
if(!is_file($base.'/.htaccess'))file_put_contents($base.'/.htaccess',"Require all denied\nDeny from all\nOptions -Indexes\n");
foreach($items as $item){
 $id=$item['id'];$u=get_userdata($id);
 if(!$u||!\TRB\Studio\portal_artist_allowed($u)||$u->display_name!==$item['expected_display'])exit(4);
 $pending=[];
 if(!\TRB\Studio\material('artist',$id,'photo')){
  $r=trb_demo_webdav_request('GET',$item['photo']);
  if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200)exit(5);
  $bytes=wp_remote_retrieve_body($r);if(isset($item['photo_hash'])&&!hash_equals($item['photo_hash'],hash('sha256',$bytes)))exit(13);if(strlen($bytes)>15*1024*1024)exit(6);
  $tmp=wp_tempnam('trb-recovered');file_put_contents($tmp,$bytes);
  if(!in_array(wp_get_image_mime($tmp),['image/jpeg','image/png','image/webp'],true)){unlink($tmp);exit(7);}
  $ed=wp_get_image_editor($tmp);if(is_wp_error($ed)){unlink($tmp);exit(8);}
  $ed->resize(1600,1600,false);$ed->set_quality(90);
  $directory=$base.'/recovered-'.$id;if(!wp_mkdir_p($directory)){unlink($tmp);exit(9);}
  $saved=$ed->save($directory.'/photo-'.substr(hash('sha256',$bytes),0,16).'.jpg','image/jpeg');unlink($tmp);
  if(is_wp_error($saved))exit(10);
  $pending[]=['group'=>'photo','label'=>'Foto artista','file'=>$saved['path'],'type'=>'image/jpeg','source'=>$item['photo']];
 }
 if(!\TRB\Studio\material('artist',$id,'biography')&&!trim((string)get_user_meta($id,'_trb_artist_bio',true))){
  $directory=$base.'/recovered-'.$id;if(!wp_mkdir_p($directory))exit(9);
  $file=$directory.'/biography-'.substr(hash('sha256',$item['bio']),0,16).'.txt';
  if(file_put_contents($file,$item['bio'])===false)exit(11);
  $pending[]=['group'=>'biography','label'=>'Biografia artistica','file'=>$file,'type'=>'text/plain','source'=>$item['bio_source']];
 }
 // Re-read after downloads so a concurrent artist upload is never overwritten.
 $files=trb_portal_private_profile_files($id);
 foreach($pending as $p){
  if(\TRB\Studio\material('artist',$id,$p['group']))continue;
  if($p['group']==='biography'&&trim((string)get_user_meta($id,'_trb_artist_bio',true)))continue;
  $hash=hash_file('sha256',$p['file']);
  $files[]=['id'=>wp_generate_uuid4(),'group'=>$p['group'],'label'=>$p['label'],'name'=>basename($p['file']),'path'=>str_replace(trailingslashit($uploads['basedir']),'',$p['file']),'type'=>$p['type'],'size'=>filesize($p['file']),'time'=>time(),'sha256'=>$hash,'archive_source'=>$p['source']];
  if($p['group']==='biography')update_user_meta($id,'_trb_artist_bio',$item['bio']);
 }
 update_user_meta($id,'_trb_artist_private_files',$files);
 $old=trim((string)get_user_meta($id,'_trb_artist_artist_name',true));
 if($old===''&&!trb_portal_artist_name_owner($item['name'],$id))update_user_meta($id,'_trb_artist_artist_name',$item['name']);
 if(!\TRB\Studio\material('artist',$id,'photo')||!trb_portal_has_valid_biography_content($id))exit(12);
}
