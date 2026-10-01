<?php
declare(strict_types=1);
namespace TrbCrm;
require_once __DIR__.'/OnboardingPolicy.php';

/** New copies only. Never replaces the registry used for historical proposals. */
final class OnboardingContractCatalog
{
    private const MODELS=[
        'dds_pimd_49'=>['DDS','1uiETHRrjQXHSI7wMyuYXF8RNrBq18oa3ffDZuuk-Yiw','15d6d6440b521e415c013dba31c5cd2567e9f3b37b918db3e38b9bb2e60c77a5',49,1,0,0],
        'ddb_csae_600'=>['DDB12','1xsUOkcDT9LjwAXR7qjrTGH9OxEsH7MqSjxEQWkwgnkc','8b77ab75eabe048be62ae2a4af2e15e2d1eaf9e353782c187a03acaf49fa5b68',600,4,500,1000],
        'ddb_ccad_600'=>['DDB','1JtCIYt7g64eusvVCQdWE_ghM292ibY71N8R3moDqrPQ','11e68cd83f73905a8b1bc869e069fde3fe9c7ce58d3d950081081c1bf5970dba',600,4,500,1000],
        'ddb_ccad_800'=>['DDB','1CeWuvHPA2d6fv8w_wXosDX4WmNIrWuLYydANHTOa9IA','75e275a73cc1e8d7f42ef94fa3d8e4e884fc006e3cdea3525d723c3abfddbccb',800,5,500,1000],
        'ddb_ccad_1200'=>['DDB','1l_4ENK1B5tcEulWjyAs5Byd-LedlgkiH2QeAqmhYe_U','c4d65aad90f3aa8d1a845aa49b0129ee86e23361f9e8f64630450c89367278c1',1200,6,500,1000],
        'ddb_trb_ccad_2000'=>['DDB-TRB','1Bm-ZOIabbdjANAP8ZFeE59E1Ajo9yvT-oi7VCK4OH3Y','4ac0b16e01617245ec61ad050133e464dea9ae58b7b23b0a469553bda1a50d84',2000,10,1000,2000],
        'ddb_trb_ccad_3000'=>['DDB-TRB','1M25Fr0oaJu7Ld1QI_VD_zAyeAEqoSX12GPi31OL_-wI','810a59b6d73d31d6b272e614e7dccacbaa4700032adfe94ee633ab90cd9acedb',3000,12,1000,2000],
        'ddb_trb_ccad_4000'=>['DDB-TRB','1OKkazZXljhR7n8wuxVW_tNG5zGZcTo3Mx8IUs25ti0o','3103e9044c3af5a0a1fabcef34e993a110fbb1e0b403dc46071d57b9732376c3',4000,16,1000,2000],
        'ddb_trb_ccad_6000'=>['DDB-TRB','1nwkUgDhcvGf5D8pRLuBDCtPl6LJVNQ9elk5Dt58V3JI','8183445bcb36423fbcb47bad137ba1bab66142d14a534dcc9ff505453d3bc2e8',6000,15,1000,2000],
        'trb_ccde'=>['TRB','11QreYLe5GW4PsMW5pNR55MFxWR9N9PAj-1BfgLNAY3Q','0a17a9218d81187ecaa43037a0cc8c6bb5d048c90fe35d39b810eb4b414427e7',0,0,0,0],
    ];
    public static function all(): array {return array_map(self::model(...),array_keys(self::MODELS));}
    public static function model(string $key): array
    {
        if(!isset(self::MODELS[$key]))throw new \InvalidArgumentException('Modello contrattuale non supportato');
        [$group,$doc,$source,$euros,$count,$shortDiscount,$singleDiscount]=self::MODELS[$key];
        $make=static function(string $kind,array $amounts,int $discount,string $label)use($source):array {
            return OnboardingPolicy::validatePlan(['kind'=>$kind,'amounts_cents'=>$amounts,'total_cents'=>array_sum($amounts),'currency'=>'EUR','discount_basis_points'=>$discount,'source_sha256'=>$source,'service_grace_days'=>0,'label'=>$label]);
        };
        if($group==='TRB')$plans=['free'=>$make('free',[],0,'Nessuna quota di attivazione')];
        elseif($group==='DDS')$plans=['monthly'=>$make('recurring',[4900],0,'Quota mensile di € 49,00, IVA inclusa')];
        else{
            $total=$euros*100;$short=intdiv($total*(10000-$shortDiscount),10000);$single=intdiv($total*(10000-$singleDiscount),10000);
            if($total%$count!==0 || $short%2!==0)throw new \LogicException('Modello con frazioni di centesimo');
            $plans=['C'=>$make('single',[$single],$singleDiscount,'Opzione C – unica soluzione'),'B'=>$make('two_installments',[intdiv($short,2),intdiv($short,2)],$shortDiscount,'Opzione B – due versamenti mensili'),'A'=>$make('monthly',array_fill(0,$count,intdiv($total,$count)),0,'Opzione A – rate mensili standard')];
        }
        return ['onboarding_version'=>OnboardingPolicy::VERSION,'template_key'=>$key,'group_code'=>$group,'template_document_id'=>$doc,'source_sha256'=>$source,'nominal_cents'=>$euros*100,'plans'=>$plans];
    }
    public static function appendix(array $practice): array
    {
        $plan=OnboardingPolicy::validatePlan($practice['selected_plan']);$date=$practice['first_payment_date']??null;
        if($plan['kind']!=='free' && !$date)throw new \RuntimeException('Primo versamento non confermato');
        $appendix=['version'=>OnboardingPolicy::VERSION,'practice_id'=>$practice['id'],'contract_id'=>(int)$practice['contract_id'],'currency'=>'EUR','plan'=>$plan,'first_payment_date'=>$date,'schedule'=>$plan['kind']==='free'?[]:OnboardingPolicy::schedule($plan,$date),'time_zone'=>'Europe/Rome'];
        $appendix['sha256']=hash('sha256',json_encode($appendix,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        return $appendix;
    }
}
