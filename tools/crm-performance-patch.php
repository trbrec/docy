<?php
/** Pure source transformation. Never loads the live application or database. */
function trb_crm_performance_patch(string $source,string $component): string
{
    if($component==='repository'){
        $old='$portalSync=$this->reconcileArtistPortalSync(false);';
        $new='$portalSync=array_merge($this->artistPortalSyncStatus(),[\'skipped\'=>true,\'reason\'=>\'scheduled_worker\']);';
        if(strpos($source,$new)!==false)return $source;
        if(substr_count($source,$old)!==1)throw new RuntimeException('Repository anchor changed');
        return str_replace($old,$new,$source);
    }
    if($component!=='controller')throw new RuntimeException('Unknown component');
    // Only authenticated read handlers. Login, logout, token refresh and mutations retain their session semantics.
    $methods=['dashboardData','systemReadiness','submissions','artists','legacyDemoArchive','activeArtists','activeArtistWorkspace','inbox','inboundCandidates','messageTemplates','contracts','accounting','accountingImports','followups','materials','releases','releaseCaseSheet','statistics'];
    $tokens=token_get_all($source);$offset=0;$edits=[];$seen=[];
    for($i=0;$i<count($tokens);$i++){
        $token=$tokens[$i];$text=is_array($token)?$token[1]:$token;
        if(is_array($token)&&$token[0]===T_FUNCTION){
            $j=$i+1;while(isset($tokens[$j])&&is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE)$j++;
            $name=is_array($tokens[$j]??null)?$tokens[$j][1]:'';
            if(in_array($name,$methods,true)){
                $start=$offset;$body='';$depth=0;$opened=false;
                for($k=$i;$k<count($tokens);$k++){
                    $t=$tokens[$k];$v=is_array($t)?$t[1]:$t;$body.=$v;
                    if($t==='{'||(is_array($t)&&in_array($t[0],[T_CURLY_OPEN,T_DOLLAR_OPEN_CURLY_BRACES],true))){$depth++;$opened=true;}elseif($t==='}'){$depth--;if($opened&&$depth===0)break;}
                }
                if(!$opened||$depth!==0)throw new RuntimeException('Handler parse failed');
                $seen[]=$name;
                if(strpos($body,'session_write_close()')!==false){$offset+=strlen($text);continue;}
                $anchor='Security::requireUser();';
                if(substr_count($body,$anchor)!==1)throw new RuntimeException('Authentication anchor changed');
                $originalLength=strlen($body);
                $body=str_replace($anchor,$anchor."\n        if(session_status()===PHP_SESSION_ACTIVE)session_write_close();",$body);
                $edits[]=[$start,$originalLength,$body];
            }
        }
        $offset+=strlen($text);
    }
    if(count(array_unique($seen))!==count($methods))throw new RuntimeException('Read handler inventory changed: '.implode(',',array_diff($methods,$seen)));
    foreach(array_reverse($edits) as [$start,$length,$body])$source=substr_replace($source,$body,$start,$length);
    return $source;
}
