<?php
if(PHP_SAPI!=='cli')exit;
$crm='/home/customer/www/crm.trbrec.com/public_html';
foreach(glob($crm.'/app/*.php') as $file){
 $source=file_get_contents($file);
 if(!str_contains($source,'outbound_batch_payloads')&&!str_contains($source,'processOutboundBatch'))continue;
 echo "\nFILE ".basename($file)."\n";
 $lines=explode("\n",$source);$chosen=[];
 foreach($lines as $i=>$line)if(preg_match('/outbound_batch_payloads|outbound_batch_items|UPDATE outbound_batches|function.*(Queue|Batch)|GET_LOCK/',$line))for($j=max(0,$i-8);$j<min(count($lines),$i+12);$j++)$chosen[$j]=true;
 ksort($chosen);foreach($chosen as $i=>$_)echo ($i+1).': '.$lines[$i]."\n";
}
