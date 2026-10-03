<?php
declare(strict_types=1);
namespace TrbCrm;

/** Transactional portal emails. Codes are never logged or placed in subjects. */
final class OnboardingMail
{
    private static function escape(string $value): string
    {
        return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    }
    public static function accessCode(string $firstName,string $code): array
    {
        if(!preg_match('/^[0-9]{6}$/D',$code))throw new \InvalidArgumentException('Codice di accesso non valido');
        $name=trim(preg_replace('/[\r\n]+/',' ',$firstName));
        $greeting=$name!==''?'Ciao '.$name.',':'Ciao,';
        $text=$greeting."\n\nPer accedere alla tua adesione, inserisci questo codice nella pagina che hai aperto:\n\n".$code."\n\nIl codice è valido per 10 minuti. Non condividerlo con altre persone.\nSe non hai richiesto il codice, puoi ignorare questa email.\n\nTRB rec · Portale Artisti\nMessaggio di servizio. Per assistenza puoi rispondere a questa email.";
        $content='<p style="margin:0 0 18px">'.self::escape($greeting).'</p><p style="margin:0 0 24px">Per accedere alla tua adesione, inserisci questo codice nella pagina che hai aperto.</p>'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" bgcolor="#E6F7EF" style="border:1px solid #B8DACC;border-radius:12px;padding:22px 12px"><div style="font-size:13px;font-weight:600;color:#365348;margin-bottom:10px">IL TUO CODICE DI ACCESSO</div><div dir="ltr" style="font-family:Consolas,Menlo,monospace;font-size:36px;line-height:1.35;font-weight:700;letter-spacing:4px;color:#183C2D">'.self::escape($code).'</div></td></tr></table>'
            .'<p style="margin:20px 0 0">Il codice è valido per <strong>10 minuti</strong>.</p><p style="margin:8px 0 0;font-size:14px;color:#52635B">Non condividerlo con altre persone.</p>'
            .'<p style="margin:24px 0 0;font-size:14px;color:#52635B">Se non hai richiesto il codice, puoi ignorare questa email.</p>';
        return ['subject'=>'Il tuo codice di accesso | TRB rec','text'=>$text,'html'=>self::layout('Accedi alla tua adesione',$content)];
    }
    public static function welcome(string $firstName,string $url,string $group='TRB'): array
    {
        if(!preg_match('~^https://artist\.trbrec\.com/adesione/(?:\?accesso=1)?#invite=[a-f0-9]{64}$~D',$url))throw new \InvalidArgumentException('Collegamento di accesso non valido');
        if(!in_array($group,['TRB','DDS','DDB12','DDB','DDB-TRB'],true))throw new \InvalidArgumentException('Profilo contrattuale non valido');
        $brand=$group==='TRB'?'TRB rec':'Digital Distribution Bundle';$title='Benvenuto in '.$brand;
        $name=trim(preg_replace('/[\r\n]+/',' ',$firstName));$greeting=$name!==''?'Ciao '.$name.',':'Ciao,';
        $text=$greeting."\n\nBenvenuto nel mondo TRB rec! Il tuo contratto è stato firmato da entrambe le parti e archiviato. Ora puoi iniziare a utilizzare il Portale Artisti.\n\nNel portale potrai:\n• Preparare le tue uscite, caricando audio, copertina e informazioni dei brani.\n• Seguire lo stato delle tue richieste e delle lavorazioni.\n• Aggiornare il tuo profilo e i tuoi dati.\n• Consultare il contratto e i documenti della tua pratica.\nLe attività disponibili dipendono dai servizi previsti dal tuo contratto.\n\nACCEDI AL PORTALE ARTISTI\n".$url."\n\nAl primo accesso conferma la tua email con il codice che ti invieremo, poi scegli la tua password personale. Entrerai direttamente nel portale. Se hai già completato la creazione dell’account, accedi con la password che hai scelto su https://artist.trbrec.com/accedi/.\nNon condividere il collegamento o i codici di accesso.\n\nTRB rec · Portale Artisti\nPer assistenza puoi rispondere a questa email.";
        $content='<p style="margin:0 0 18px">'.self::escape($greeting).'</p><p style="margin:0 0 22px">Il tuo contratto è stato <strong>firmato da entrambe le parti e archiviato</strong>. Ora puoi iniziare a utilizzare il Portale Artisti.</p>'
            .'<p style="margin:0 0 10px;font-weight:700">Il tuo spazio per lavorare con TRB rec</p><ul style="padding-left:22px;margin:0 0 20px;line-height:1.8"><li>Prepara le tue uscite: audio, copertina e informazioni dei brani.</li><li>Segui lo stato delle tue richieste e delle lavorazioni.</li><li>Aggiorna il tuo profilo e i tuoi dati.</li><li>Consulta il contratto e i documenti della tua pratica.</li></ul>'
            .'<p style="margin:0 0 24px;font-size:14px;color:#52635B">Le attività disponibili dipendono dai servizi previsti dal tuo contratto.</p>'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" bgcolor="#AFEBD2" style="border-radius:10px"><a href="'.self::escape($url).'" style="display:block;padding:18px 12px;color:#183C2D;font-size:16px;font-weight:700;text-decoration:none">ACCEDI AL PORTALE ARTISTI</a></td></tr></table>'
            .'<p style="margin:22px 0 0"><strong>Primo accesso:</strong> conferma la tua email con il codice che ti invieremo e scegli la tua password personale. Entrerai direttamente nel portale.</p><p style="margin:12px 0 0;font-size:14px;color:#52635B">Se hai già creato l’account, accedi con la password che hai scelto dalla pagina di accesso del portale. Non condividere il collegamento o i codici.</p>';
        $text=$title."\n\n".str_replace('Benvenuto nel mondo TRB rec! ','',$text);
        $text=str_replace('TRB rec · Portale Artisti',$brand.' · Portale Artisti',$text);
        $content=str_replace('Il tuo spazio per lavorare con TRB rec','Il tuo spazio per lavorare con '.self::escape($brand),$content);
        return ['subject'=>'Il tuo accesso al Portale Artisti è pronto | '.$brand,'text'=>$text,'html'=>self::layout($title,$content)];
    }
    public static function notice(string $title,string $text): string
    {
        return self::layout($title,'<div style="line-height:1.65">'.nl2br(self::escape($text),false).'</div>');
    }
    private static function layout(string $title,string $content): string
    {
        $titleHtml=self::escape($title);
        return '<!doctype html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$titleHtml.'</title></head>'
            .'<body style="margin:0;padding:0;background:#F3F6F4;color:#1B2D23;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#F3F6F4"><tr><td align="center" style="padding:24px 12px">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px"><tr><td bgcolor="#FFFFFF" style="padding:28px 24px;border:1px solid #DCE6DF;border-radius:16px">'
            .'<p style="margin:0;color:#234D3A;font-size:20px;font-weight:700">TRB rec</p><p style="margin:2px 0 26px;color:#65746B;font-size:14px">Portale Artisti</p>'
            .'<h1 style="margin:0 0 24px;font-size:24px;line-height:1.25;font-weight:700">'.$titleHtml.'</h1>'.$content
            .'</td></tr><tr><td style="padding:18px 12px;color:#65746B;font-size:12px;line-height:1.6">Messaggio di servizio di TRB rec · Portale Artisti.<br>Per assistenza puoi rispondere a questa email.</td></tr></table>'
            .'</td></tr></table></body></html>';
    }
    public static function mime(string $email,string $subject,string $text,?string $html,string $messageId,string $boundary): string
    {
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$email)
            ||!preg_match('/^<trbcrm\.onboarding\.[a-f0-9]{32}@crm\.trbrec\.com>$/D',$messageId)
            ||!preg_match('/^=_trbonboarding_[a-f0-9]{24}$/D',$boundary))throw new \InvalidArgumentException('Email di servizio non valida');
        $subject=mb_encode_mimeheader(trim(preg_replace('/[\r\n]+/',' ',$subject)),'UTF-8','B',"\r\n");
        $html??=self::notice('Aggiornamento della tua adesione',$text);
        $part=static fn(string $type,string $body):string=>'--'.$boundary."\r\nContent-Type: ".$type."; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($body),76,"\r\n");
        return 'To: '.$email."\r\nFrom: TRB rec - Portale Artisti <andrea.tognassi@trbrec.com>\r\nReply-To: andrea.tognassi@trbrec.com\r\nSubject: ".$subject."\r\nDate: ".gmdate('D, d M Y H:i:s O')."\r\nMessage-ID: ".$messageId."\r\nMIME-Version: 1.0\r\nAuto-Submitted: auto-generated\r\nX-Auto-Response-Suppress: All\r\nContent-Language: it\r\nContent-Type: multipart/alternative; boundary=\"".$boundary."\"\r\n\r\n"
            .$part('text/plain',$text).$part('text/html',$html).'--'.$boundary."--\r\n";
    }
}
