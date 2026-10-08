<?php
define('ABSPATH','test');function add_action(...$a){}
require __DIR__.'/../inc/trb-release-sheet-data.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);echo "OK $message\n";}
$file=tempnam(sys_get_temp_dir(),'lyrics-test-');
try{
file_put_contents($file,"Primo verso\r\nSecondo verso\r\n\r\nRitornello");
check(trb_release_sheet_text($file,'txt')==="Primo verso\nSecondo verso\n\nRitornello",'TXT preserves verses and blank lines');
file_put_contents($file,'{\rtf1\ansi{\fonttbl{\f0 Arial;}}Primo verso\par Secondo verso}');
check(trb_release_sheet_text($file,'rtf')==="Primo verso\nSecondo verso",'RTF excludes metadata and preserves verses');
file_put_contents($file,'{\rtf1\ansi\uc1 Citt\u224?\par Perch\u233?}');
check(trb_release_sheet_text($file,'rtf')==="Città\nPerché",'RTF Unicode accents preserved');
if(class_exists('ZipArchive')){
foreach(['docx'=>['word/document.xml','<w:document xmlns:w="urn:word"><w:p><w:r><w:t>Primo &amp; verso</w:t></w:r></w:p><w:p><w:r><w:t>Secondo verso</w:t></w:r></w:p></w:document>'],'odt'=>['content.xml','<office:document xmlns:office="urn:office" xmlns:text="urn:text"><text:p>Primo &amp; verso</text:p><text:p>Secondo verso</text:p></office:document>']] as $ext=>$fixture){
    unlink($file);$zip=new ZipArchive();$zip->open($file,ZipArchive::CREATE);$zip->addFromString($fixture[0],$fixture[1]);$zip->close();
    check(trb_release_sheet_text($file,$ext)==="Primo & verso\nSecondo verso",strtoupper($ext).' preserves paragraphs and entities');
}
}
check(trb_release_value_label('owned')==='Di proprietà','Ownership translated');
check(trb_release_value_label('clean')==='Censurato','Clean differs from non explicit');
}finally{unlink($file);}
