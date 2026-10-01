<?php
declare(strict_types=1);
namespace TrbCrm;
require_once __DIR__.'/OnboardingContractCatalog.php';

/** Generated previews are still unsent proposals, not executed contracts. */
final class OnboardingIntake
{
    public static function eligible(array $contract): bool
    {
        return in_array($contract['status']??'', ['draft','prepared','generated'], true)
            && !in_array($contract['source_tab']??'', ['PORTALE_ARTISTI','PORTALE_DEMO'], true)
            && empty($contract['sent_at']) && empty($contract['accepted_at']);
    }

    public static function choices(\PDO $db): array
    {
        $rows=$db->query("SELECT ct.id,ct.contract_number,ct.template_key,ct.status,ct.sent_at,ct.accepted_at,s.source_tab,c.artist_name,c.first_name,c.last_name FROM contracts ct JOIN submissions s ON s.id=ct.submission_id JOIN contacts c ON c.id=s.contact_id LEFT JOIN onboarding_practices p ON p.contract_id=ct.id WHERE ct.status IN ('draft','prepared','generated') AND ct.sent_at IS NULL AND ct.accepted_at IS NULL AND p.id IS NULL AND COALESCE(s.source_tab,'') NOT IN ('PORTALE_ARTISTI','PORTALE_DEMO') ORDER BY ct.id DESC LIMIT 100")->fetchAll(\PDO::FETCH_ASSOC);
        $keys=array_column(OnboardingContractCatalog::all(),'template_key');
        return array_values(array_filter($rows,static fn($row)=>self::eligible($row)&&in_array($row['template_key'],$keys,true)));
    }
}
