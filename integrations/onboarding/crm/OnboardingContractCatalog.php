<?php
declare(strict_types=1);
namespace TrbCrm;
require_once __DIR__.'/OnboardingPolicy.php';

/** New copies only. Never replaces the registry used for historical proposals. */
final class OnboardingContractCatalog
{
    private const MODELS=[
        'dds_pimd_49'=>['DDS','1uiETHRrjQXHSI7wMyuYXF8RNrBq18oa3ffDZuuk-Yiw','21efcc314ce006b0f4ee08831c69acc703830abb78260782660e42b8a03c7c23',49,1,0,0],
        'ddb_csae_600'=>['DDB12','1xsUOkcDT9LjwAXR7qjrTGH9OxEsH7MqSjxEQWkwgnkc','175b27120a6e1e190209f880aaafaf74b1a1d7e2151abf9abed8e223d639cf7a',600,4,500,1000],
        'ddb_ccad_600'=>['DDB','1JtCIYt7g64eusvVCQdWE_ghM292ibY71N8R3moDqrPQ','614877023810f8e40ff3cf14a363fd23735547fb575d17ac7082ae5834ef575c',600,4,500,1000],
        'ddb_ccad_800'=>['DDB','1CeWuvHPA2d6fv8w_wXosDX4WmNIrWuLYydANHTOa9IA','f3e2992f8f05400ef3e0039b5914dae9225568d7b3baa22fb005193b2894662b',800,5,500,1000],
        'ddb_ccad_1200'=>['DDB','1l_4ENK1B5tcEulWjyAs5Byd-LedlgkiH2QeAqmhYe_U','5d645e98cb4abd645aa2059375be97827c8d605e7a346cfc9785e93cc4e4caf1',1200,6,500,1000],
        'ddb_trb_ccad_2000'=>['DDB-TRB','1Bm-ZOIabbdjANAP8ZFeE59E1Ajo9yvT-oi7VCK4OH3Y','7704c96348df87c4571c9c1cc124f7b2076de4043a2240c6c60480f690c381fb',2000,10,1000,2000],
        'ddb_trb_ccad_3000'=>['DDB-TRB','1M25Fr0oaJu7Ld1QI_VD_zAyeAEqoSX12GPi31OL_-wI','8725b21dbc69398d178acf3f2dc912921f6154f5712bad11a00ffa319bc98191',3000,12,1000,2000],
        'ddb_trb_ccad_4000'=>['DDB-TRB','1OKkazZXljhR7n8wuxVW_tNG5zGZcTo3Mx8IUs25ti0o','03a8b7ef9e6a9f927d03ec810502dcace6cf8ad967bad2b6f7821b26b3de34cf',4000,16,1000,2000],
        'ddb_trb_ccad_6000'=>['DDB-TRB','1nwkUgDhcvGf5D8pRLuBDCtPl6LJVNQ9elk5Dt58V3JI','5ee00d58d4d1e842d60098432ed5a90ba36f673ffc0570be8a2f56c6e927b033',6000,15,1000,2000],
        'trb_ccde'=>['TRB','11QreYLe5GW4PsMW5pNR55MFxWR9N9PAj-1BfgLNAY3Q','2efb0674db3db319b8df5ea8278c315b0962ca88fca80ef8f4ad35d587a8b6d0',0,0,0,0],
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
