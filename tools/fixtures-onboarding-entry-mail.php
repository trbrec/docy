<?php
namespace TrbCrm;
final class OutboundMail
{
    public static array $last=[];public static bool $sentCopy=true;
    public static function withSignature(string $body): string{return $body;}
    public static function alternativePayload(string $body,string $boundary): string{return '--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n".$body."\r\n--".$boundary."--\r\n";}
    public static function candidateBridge(array $payload): array{self::$last=$payload;return ['sent'=>true,'gmail_message_id'=>'fixture','sent_copy'=>self::$sentCopy];}
}
