<?php
declare(strict_types=1);
namespace TrbCrm;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Pure rules: no legacy records, network calls, email or activation side effects. */
final class OnboardingPolicy
{
    public const VERSION = '2026.2';

    public static function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Europe/Rome'));
        if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('Data non valida');
        return $date;
    }

    public static function normalizedName(string $value): string
    {
        if (class_exists('Normalizer')) $value = \Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value;
        return trim(preg_replace('/[^\p{L}\p{M}\p{N}]+/u', ' ', mb_strtolower($value, 'UTF-8')) ?? '');
    }

    /** AI extracts fields; this function alone makes the name/age decision. */
    public static function identity(array $contract, array $document, string $today): array
    {
        if (($document['legible'] ?? false) !== true) return ['status'=>'review', 'reason'=>'document_unreadable'];
        $surname = self::normalizedName((string)($contract['last_name'] ?? ''));
        $documentSurname = self::normalizedName((string)($document['last_name'] ?? ''));
        $given = self::givenNames((string)($contract['first_name'] ?? ''));
        $documentGiven = self::givenNames((string)($document['first_name'] ?? ''));
        if ($surname === '' || $surname !== $documentSurname || !array_intersect($given, $documentGiven)) {
            return ['status'=>'review', 'reason'=>'name_mismatch'];
        }
        try { $birth = self::date((string)($document['birth_date'] ?? '')); $now = self::date($today); }
        catch (InvalidArgumentException $e) { return ['status'=>'review', 'reason'=>'birth_date_unreadable']; }
        if ($birth > $now || $birth->diff($now)->y > 120) return ['status'=>'review', 'reason'=>'birth_date_invalid'];
        // Calendar anniversary, including leap-day birthdays: no seconds/year approximation.
        if ($birth->modify('+18 years') > $now) return ['status'=>'blocked', 'reason'=>'minor'];
        return ['status'=>'matched', 'reason'=>'name_and_age', 'birth_date'=>$birth->format('Y-m-d')];
    }

    /** The two fronts must agree with each other and the supplied tax code. */
    public static function documents(array $contract,array $document,string $taxCode,string $today): array
    {
        if(($document['identity_document']??true)!==true&&($document['tax_document']??true)!==true)return ['status'=>'review','reason'=>'documents_required'];
        if(($document['identity_document']??true)!==true)return ['status'=>'review','reason'=>'identity_document_required'];
        if(($document['tax_document']??true)!==true)return ['status'=>'review','reason'=>'tax_document_required'];
        $identity=self::identity($contract,$document,$today);
        if($identity['status']!=='matched')return $identity;
        try{$expiry=self::date((string)($document['expiry_date']??''));}
        catch(InvalidArgumentException $e){return ['status'=>'review','reason'=>'identity_expiry_unreadable'];}
        if($expiry<self::date($today))return ['status'=>'review','reason'=>'identity_expired'];
        if(($document['tax_legible']??false)!==true)return ['status'=>'review','reason'=>'tax_document_unreadable'];
        $taxIdentity=self::identity(['first_name'=>$document['first_name'],'last_name'=>$document['last_name']],['legible'=>true,'first_name'=>$document['tax_first_name']??'','last_name'=>$document['tax_last_name']??'','birth_date'=>$document['tax_birth_date']??''],$today);
        if($taxIdentity['status']!=='matched'||($document['tax_birth_date']??'')!==$document['birth_date'])return ['status'=>'review','reason'=>'tax_identity_mismatch'];
        $normalize=static fn(string $value):string=>mb_strtoupper(preg_replace('/\s+/u','',trim($value))??'');
        $typed=$normalize($taxCode);$read=$normalize((string)($document['tax_code']??''));
        if(!preg_match('/^(?:[A-Z0-9]{16}|[0-9]{11})$/D',$typed)||$typed!==$read)return ['status'=>'review','reason'=>'tax_code_mismatch'];
        return ['status'=>'matched','reason'=>'identity_and_tax','birth_date'=>$document['birth_date'],'expiry_date'=>$document['expiry_date'],'tax_code'=>$read];
    }

    private static function givenNames(string $value): array
    {
        if(class_exists('Normalizer'))$value=\Normalizer::normalize($value,\Normalizer::FORM_C)?:$value;
        // Hyphenated names are one given name, not a matchable substring.
        $value=str_replace(['’','‐','‑','–'],["'",'-','-','-'],mb_strtolower($value,'UTF-8'));
        $value=trim(preg_replace("/[^\\p{L}\\p{M}\\p{N}'-]+/u",' ',$value)??'');
        return array_filter(explode(' ',$value));
    }

    /** A plan consists of exact contractual cents, not recalculated headline discounts. */
    public static function validatePlan(array $plan): array
    {
        if (!in_array($plan['kind'] ?? '', ['single','two_installments','monthly','recurring','free'], true)) throw new InvalidArgumentException('Formula non valida');
        $amounts = $plan['amounts_cents'] ?? [];
        if (!is_array($amounts) || count($amounts) > 60 || $amounts !== array_values($amounts)) throw new InvalidArgumentException('Rate non valide');
        if (($plan['currency'] ?? '') !== 'EUR') throw new InvalidArgumentException('Valuta non valida');
        foreach ($amounts as $amount) if (!is_int($amount) || $amount < 1 || $amount > 100000000) throw new InvalidArgumentException('Importo rata non valido');
        $kind = $plan['kind'];
        if (($kind === 'free' && $amounts !== []) || ($kind !== 'free' && $amounts === [])) throw new InvalidArgumentException('Formula priva di importi');
        if (in_array($kind, ['single','recurring'], true) && count($amounts) !== 1) throw new InvalidArgumentException('Formula incompatibile con le rate');
        if ($kind === 'two_installments' && count($amounts) !== 2) throw new InvalidArgumentException('Servono due rate');
        if (!is_int($plan['total_cents'] ?? null) || $plan['total_cents'] !== array_sum($amounts)) throw new InvalidArgumentException('Totale diverso dalla somma delle rate');
        if (!is_int($plan['discount_basis_points'] ?? null) || $plan['discount_basis_points'] < 0 || $plan['discount_basis_points'] > 10000) throw new InvalidArgumentException('Sconto non valido');
        if (!preg_match('/^[a-f0-9]{64}$/D', (string)($plan['source_sha256'] ?? ''))) throw new InvalidArgumentException('Condizioni contrattuali non verificate');
        if (($plan['service_grace_days'] ?? null) !== 0) throw new InvalidArgumentException('Il nuovo percorso richiede sospensione dal giorno seguente');
        return $plan;
    }

    /** Anchor stays the ORIGINAL day: Jan 31 -> Feb 28 -> Mar 31, never drifts. */
    public static function dueDate(string $firstPaymentDate, int $monthOffset): string
    {
        if ($monthOffset < 0 || $monthOffset > 1200) throw new InvalidArgumentException('Scadenza fuori intervallo');
        $anchor = self::date($firstPaymentDate);
        $month = $anchor->modify('first day of this month')->modify('+'.$monthOffset.' months');
        return $month->setDate((int)$month->format('Y'), (int)$month->format('m'), min((int)$anchor->format('d'), (int)$month->format('t')))->format('Y-m-d');
    }

    public static function schedule(array $plan, string $firstPaymentDate, int $recurringHorizon = 12): array
    {
        $plan = self::validatePlan($plan);
        self::date($firstPaymentDate);
        if ($plan['kind'] === 'free') return [];
        $count = $plan['kind'] === 'recurring' ? max(1,min(120,$recurringHorizon)) : count($plan['amounts_cents']);
        $rows=[];
        for ($i=0;$i<$count;$i++) $rows[]=['number'=>$i+1, 'due_date'=>self::dueDate($firstPaymentDate,$i), 'amount_cents'=>$plan['amounts_cents'][$plan['kind']==='recurring'?0:$i]];
        return $rows;
    }

    public static function services(array $practice, array $installments, string $today): array
    {
        self::date($today);
        if (($practice['onboarding_version'] ?? '') !== self::VERSION) return ['managed'=>false,'allowed'=>true,'reason'=>'legacy'];
        if (!empty($practice['cancelled_at'])) return ['managed'=>true,'allowed'=>false,'reason'=>'cancelled'];
        foreach (['owner_approved_at','signed_at','signed_pcloud_file_id','portal_activated_at'] as $field) {
            if (empty($practice[$field])) return ['managed'=>true,'allowed'=>false,'reason'=>'activation_pending'];
        }
        $overdue=[];
        foreach ($installments as $row) {
            self::date($row['due_date']);
            $balance=max(0,(int)$row['amount_cents']-(int)($row['confirmed_cents']??0));
            if ($row['due_date'] < $today && $balance > 0) $overdue[]=array_merge($row,['balance_cents'=>$balance]);
        }
        return ['managed'=>true,'allowed'=>$overdue===[], 'reason'=>$overdue===[]?'current':'overdue', 'overdue'=>$overdue];
    }

    public static function reminderKey(string $practiceId, int $number): string
    {
        if ($number < 1) throw new InvalidArgumentException('Numero rata non valido');
        return 'onboarding:'.$practiceId.':installment:'.$number.':overdue-v1';
    }
}
