<?php
declare(strict_types=1);
namespace TrbCrm;

/** Validate the two onboarding boundaries before identifiers or text are coerced. */
final class OnboardingRequest
{
    public static function json(string $body): array
    {
        try {
            $object = json_decode($body, false, 32, JSON_THROW_ON_ERROR);
            if (!$object instanceof \stdClass) throw new \JsonException('Object required');
            return json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \RuntimeException('Oggetto JSON non valido');
        }
    }

    public static function rpc(array $input): array
    {
        // A null practice selects the next queued item for the background worker.
        if (array_key_exists('practice_id', $input) && $input['practice_id'] === null) unset($input['practice_id']);
        self::texts($input, ['action'=>64, 'token'=>64, 'session'=>64, 'practice_id'=>32,
            'code'=>6, 'salt'=>64, 'email'=>254, 'slot'=>32, 'plan_key'=>64,
            'proposal_sha256'=>64, 'tax_code'=>32]);
        foreach (['retry', 'proposal_read', 'privacy_acknowledged'] as $key) {
            if (array_key_exists($key, $input) && !is_bool($input[$key])) self::invalid($key);
        }
        foreach (['file', 'billing', 'profile', 'invoice'] as $key) {
            if (array_key_exists($key, $input)
                && (!is_array($input[$key]) || ($input[$key] !== [] && array_is_list($input[$key])))) self::invalid($key);
        }
        if (($input['action'] ?? '') === 'upload') $input['file'] = self::uploadFile($input['file'] ?? null);
        if (array_key_exists('portal_user_id', $input)) $input['portal_user_id'] = self::identifier($input['portal_user_id']);
        return $input;
    }

    /** Public documents must be validated before any archive or ledger mutation. */
    public static function uploadFile(mixed $file, bool $identity = true): array
    {
        if (!is_array($file) || array_is_list($file)) self::invalid('file');
        $name = $file['name'] ?? null; $mime = $file['mime'] ?? null; $size = $file['size'] ?? null;
        if (!is_string($name) || !mb_check_encoding($name, 'UTF-8') || trim($name) === ''
            || mb_strlen($name, 'UTF-8') > 255 || preg_match('~[\x00-\x1f\x7f/\\\\]~', $name)) self::invalid('file.name');
        $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!$identity) $allowed[] = 'application/xml';
        if (!is_string($mime) || !in_array($mime, $allowed, true)) self::invalid('file.mime');
        if (!is_int($size) || $size < 1 || $size > 10485760) self::invalid('file.size');
        return ['name'=>$name, 'mime'=>$mime, 'size'=>$size];
    }

    public static function form(array $input): array
    {
        self::texts($input, ['action'=>64, 'id'=>32, 'template_key'=>64, 'slot'=>32,
            'confirm_signature'=>1, 'first_name'=>200, 'last_name'=>200, 'birth_date'=>10,
            'expiry_date'=>10, 'tax_first_name'=>200, 'tax_last_name'=>200,
            'tax_birth_date'=>10, 'tax_code'=>32]);
        foreach (['submission_id', 'contract_id', 'folder_id'] as $key) {
            if (array_key_exists($key, $input)) $input[$key] = self::identifier($input[$key], $key === 'folder_id');
        }
        if (isset($input['id']) && $input['id'] !== '' && !preg_match('/^[a-f0-9]{32}$/D', $input['id'])) self::invalid('id');
        return $input;
    }

    private static function texts(array $input, array $limits): void
    {
        foreach ($limits as $key=>$maximum) {
            if (!array_key_exists($key, $input)) continue;
            $value = $input[$key];
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')
                || mb_strlen($value, 'UTF-8') > $maximum || preg_match('/[\x00-\x1f\x7f]/', $value)) self::invalid($key);
        }
    }

    private static function identifier(mixed $value, bool $zeroAllowed = false): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[0-9]{1,18}$/D', (string)$value)
            || (!$zeroAllowed && (int)$value < 1)) self::invalid('id');
        return (int)$value;
    }

    private static function invalid(string $key): never
    {
        throw new \RuntimeException('Campo della richiesta non valido: '.$key);
    }
}
