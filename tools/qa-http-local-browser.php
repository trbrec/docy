<?php
/** A real local browser carries only this isolated installation's requests. */
function trb_qa_browser_controller_source( $work, $token, $base ) {
    $source = <<<'PHP'
$browser_work=__WORK__;
$browser_token=__TOKEN__;
$browser_base=__BASE__;
$browser_mode=$_GET['qa_browser']??'';
if($browser_mode!==''){
 if(!hash_equals($browser_token,(string)($_GET['qa_token']??''))){http_response_code(404);exit;}
 header('Cache-Control: no-store');
 if($browser_mode==='poll'){
  header('Content-Type: application/json');
  if(is_file($browser_work.'/browser-finished.json')){echo file_get_contents($browser_work.'/browser-finished.json');exit;}
  if(is_file($browser_work.'/browser-request.json')){echo file_get_contents($browser_work.'/browser-request.json');exit;}
  echo json_encode(['waiting'=>true,'fixture_visible'=>is_dir($browser_work)]);exit;
 }
 if($browser_mode==='ack'){
  $ack=json_decode(file_get_contents('php://input'),true,16,JSON_THROW_ON_ERROR);
  $expected=json_decode((string)@file_get_contents($browser_work.'/browser-request.json'),true);
  if(!is_array($expected)||($ack['id']??'')!==($expected['id']??null)){http_response_code(409);exit;}
  $observed=$browser_work.'/browser-observed-'.$expected['id'].'.json';
  $answer=is_file($observed)?json_decode(file_get_contents($observed),true,16,JSON_THROW_ON_ERROR):$ack['response'];
  $cookie_name='wordpress_logged_in_'.md5($browser_base);
  $answer['cookies']="# Netscape HTTP Cookie File\n";
  if(isset($_COOKIE[$cookie_name]))$answer['cookies'].="artist.trbrec.com\tFALSE\t/\tTRUE\t0\t".$cookie_name."\t".rawurlencode($_COOKIE[$cookie_name])."\n";
  $json=json_encode($answer,JSON_THROW_ON_ERROR);
  if(file_put_contents($browser_work.'/browser-response-'.$expected['id'].'.json',$json,LOCK_EX)!==strlen($json)){http_response_code(500);exit;}
  echo '{"accepted":true}';exit;
 }
 if($browser_mode!=='controller'){http_response_code(404);exit;}
 header('Content-Type: text/html; charset=utf-8');
 $browser_config=json_encode(['token'=>$browser_token,'endpoint'=>$browser_base.'/index.php'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
 echo '<!doctype html><html lang="it"><meta charset="utf-8"><title>Collaudo riservato — artista fittizio</title><h1>Collaudo dell’artista fittizio Tunisia</h1><p id="state">Verifiche in corso, dati separati dagli artisti reali.</p><pre id="result"></pre><script>const config='.$browser_config.';';
 ?>
let seen='',sequence=0,step='poll';
const bytes64=bytes=>{let s='';for(const b of bytes)s+=String.fromCharCode(b);return btoa(s);};
async function cycle(){
 const query='?qa_token='+encodeURIComponent(config.token)+'&qa_browser=';
 const packet=await fetch(config.endpoint+query+'poll',{cache:'no-store',credentials:'include'}).then(r=>r.json());
 if(packet.fixture_visible===false){document.getElementById('state').textContent='Ambiente separato non visibile dal processo web: collaudo non confermato.';return;}
 if(typeof packet.completed==='boolean'){document.getElementById('state').textContent=packet.completed?'Collaudo completato.':'Collaudo interrotto: verificare il risultato.';document.getElementById('result').textContent=JSON.stringify(packet,null,2);return;}
 if(packet.id&&packet.id!==seen){
  seen=packet.id;sequence=packet.sequence;step='request';document.getElementById('state').textContent='Richiesta di verifica '+packet.sequence+' in corso…';
  const headers=Object.assign({},packet.headers,{'X-TRB-QA-Browser-Id':packet.id});
  const options={headers,credentials:'include',redirect:'manual',cache:'no-store'};
  if(packet.fields!==null){options.method='POST';const form=new FormData();for(const[name,value]of Object.entries(packet.fields)){if(value&&typeof value==='object'){const bytes=Uint8Array.from(atob(value.base64),c=>c.charCodeAt(0));form.append(name,new Blob([bytes],{type:value.mime}),value.filename);}else form.append(name,String(value));}options.body=form;}
  const response=await fetch(packet.url,options);
  const body=new Uint8Array(await response.arrayBuffer());let responseHeaders='';response.headers.forEach((value,name)=>responseHeaders+=name+': '+value+'\r\n');
  const answer={id:packet.id,response:{status:response.status,headers:responseHeaders,body_base64:bytes64(body)}};
  step='ack';const accepted=await fetch(config.endpoint+query+'ack',{method:'POST',credentials:'include',headers:{'Content-Type':'application/json'},body:JSON.stringify(answer)});
  if(!accepted.ok)throw Error('Conferma della richiesta non riuscita.');
 }
 step='poll';setTimeout(()=>cycle().catch(fail),250);
}
function fail(){document.getElementById('state').textContent='Il collaudo si è fermato senza confermare un esito positivo. Richiesta '+sequence+', fase '+step+'.';}
cycle().catch(fail);
</script></html>
<?php
 exit;
}
// Capture actual WordPress status/Location even for a browser opaque redirect.
$browser_id=$_SERVER['HTTP_X_TRB_QA_BROWSER_ID']??'';
if(preg_match('/^[a-f0-9]{24}$/D',$browser_id)){
 $expected=json_decode((string)@file_get_contents($browser_work.'/browser-request.json'),true);
 if(is_array($expected)&&($expected['id']??'')===$browser_id){
  ob_start(static function($body)use($browser_work,$browser_id){
   $response=['status'=>http_response_code()?:200,'headers'=>implode("\r\n",headers_list())."\r\n",'body_base64'=>base64_encode($body)];
   file_put_contents($browser_work.'/browser-observed-'.$browser_id.'.json',json_encode($response,JSON_THROW_ON_ERROR),LOCK_EX);
   return $body;
  });
 }
}
PHP;
    return str_replace( array( '__WORK__', '__TOKEN__', '__BASE__' ), array( var_export( $work, true ), var_export( $token, true ), var_export( $base, true ) ), $source );
}

function trb_qa_browser_request( $url, $headers, $fields, $authenticated, $cookie, $work ) {
    static $sequence = 0;
    $serialized = $fields;
    if ( is_array( $serialized ) ) foreach ( $serialized as &$value ) if ( $value instanceof CURLFile ) {
        $path = $value->getFilename();
        if ( is_link( $path ) || dirname( $path ) !== $work || ! in_array( basename( $path ), array( 'synthetic.png', 'synthetic.txt', 'forged.png' ), true ) || filesize( $path ) > 65536 ) throw new RuntimeException( 'Unexpected browser upload.' );
        $value = array( 'filename' => $value->getPostFilename(), 'mime' => $value->getMimeType(), 'base64' => base64_encode( file_get_contents( $path ) ) );
    }
    unset( $value );
    $id = bin2hex( random_bytes( 12 ) );
    $json = json_encode( array( 'id' => $id, 'sequence' => ++$sequence, 'url' => $url, 'headers' => $headers, 'fields' => $serialized, 'authenticated' => $authenticated ), JSON_THROW_ON_ERROR );
    $temporary = $work . '/browser-request.next';
    if ( file_put_contents( $temporary, $json, LOCK_EX ) !== strlen( $json ) || ! chmod( $temporary, 0600 ) || ! rename( $temporary, $work . '/browser-request.json' ) ) throw new RuntimeException( 'Browser request unavailable.' );
    $response_path = $work . '/browser-response-' . $id . '.json'; $deadline = time() + ( $sequence === 1 ? 600 : 90 );
    while ( ! is_file( $response_path ) && time() < $deadline ) { clearstatcache( true, $response_path ); usleep( 100000 ); }
    if ( ! is_file( $response_path ) ) throw new RuntimeException( 'Local browser response not confirmed.' );
    $answer = json_decode( file_get_contents( $response_path ), true, 16, JSON_THROW_ON_ERROR );
    $body = base64_decode( $answer['body_base64'] ?? '', true );
    if ( ! is_string( $body ) || ! is_int( $answer['status'] ?? null ) || ! is_string( $answer['headers'] ?? null ) || $answer['status'] < 100 ) throw new RuntimeException( 'Local browser response invalid.' );
    if ( $authenticated && ( file_put_contents( $cookie, $answer['cookies'] ?? '', LOCK_EX ) === false || ! chmod( $cookie, 0600 ) ) ) throw new RuntimeException( 'Synthetic browser session unavailable.' );
    return array( $answer['status'], $answer['headers'], $body );
}
