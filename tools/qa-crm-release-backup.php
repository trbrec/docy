<?php
/** Fresh, private, readback-verified DB snapshot. The release never imports or migrates data. */
function trb_crm_release_database_backup(string $public,string $destination): array {
    trb_release_file_check_path($public.'/.env',$public);
    if(is_link($destination)||file_exists($destination)||!mkdir($destination,0700))throw new RuntimeException('Backup destination already exists.');
    $config=[];
    foreach(file($public.'/.env',FILE_IGNORE_NEW_LINES)?:[] as $line){
        $line=trim($line);if($line===''||$line[0]==='#'||!str_contains($line,'='))continue;
        [$k,$v]=array_map('trim',explode('=',$line,2));if(preg_match('/^[A-Z][A-Z0-9_]*$/D',$k))$config[$k]=trim($v,"\"'");
    }
    $database=$config['DB_DATABASE']??'';$port=$config['DB_PORT']??'3306';
    if($database!=='dbsfrbzpgv5b4j'||!ctype_digit($port)||!isset($config['DB_USERNAME']))throw new RuntimeException('CRM backup database guard.');
    $quote=static fn(string $v):string=>'"'.strtr($v,["\\"=>"\\\\",'"'=>'\\"',"\r"=>'\r',"\n"=>'\n']).'"';
    $cnf=$destination.'/dump.cnf';$settings="[client]\nuser=".$quote($config['DB_USERNAME'])."\npassword=".$quote($config['DB_PASSWORD']??'')."\nhost=".$quote($config['DB_HOST']??'localhost')."\nport=".$port."\n";
    trb_release_file_replace($cnf,$settings,0600);$sql=$destination.'/database-before.sql';$error=$destination.'/dump.err';
    try{
        $process=proc_open(['/bin/mysqldump','--defaults-extra-file='.$cnf,'--single-transaction','--skip-lock-tables','--no-tablespaces','--hex-blob','--skip-comments',$database],[0=>['pipe','r'],1=>['file',$sql,'w'],2=>['file',$error,'w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('Database snapshot unavailable.');
        fclose($pipes[0]);if(proc_close($process)!==0)throw new RuntimeException('Database snapshot failed.');
    }finally{if(is_file($cnf))unlink($cnf);}
    $size=filesize($sql);$sha=hash_file('sha256',$sql);if($size<1000000)throw new RuntimeException('Database snapshot unexpectedly small.');
    $input=fopen($sql,'rb');$gz=gzopen($sql.'.gz','wb6');
    if(!$input||!$gz)throw new RuntimeException('Database snapshot compression unavailable.');
    while(!feof($input)){$bytes=fread($input,1048576);if($bytes===false||gzwrite($gz,$bytes)!==strlen($bytes))throw new RuntimeException('Database snapshot compression failed.');}fclose($input);gzclose($gz);
    $gz=gzopen($sql.'.gz','rb');if(!$gz)throw new RuntimeException('Database snapshot readback unavailable.');
    $hash=hash_init('sha256');$read=0;while(!gzeof($gz)){$bytes=gzread($gz,1048576);if($bytes===false)throw new RuntimeException('Database snapshot readback failed.');$read+=strlen($bytes);hash_update($hash,$bytes);}gzclose($gz);
    if($read!==$size||hash_final($hash)!==$sha)throw new RuntimeException('Database snapshot differs on readback.');
    if(!unlink($sql))throw new RuntimeException('Plain snapshot cleanup failed.');
    $record=['readback_verified'=>true,'compressed_sha256'=>hash_file('sha256',$sql.'.gz'),'uncompressed_sha256'=>$sha,'uncompressed_bytes'=>$size,'created_at'=>gmdate('c'),'database_written'=>false];
    trb_release_file_replace($destination.'/verified.json',json_encode($record,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT),0600);
    return $record;
}
