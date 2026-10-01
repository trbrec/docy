<?php
declare(strict_types=1);
namespace TrbCrm;
require_once __DIR__.'/OnboardingPolicy.php';

/** New copies only. Never replaces the registry used for historical proposals. */
final class OnboardingContractCatalog
{
    private const MODELS=[
        'dds_pimd_49'=>['DDS','1uiETHRrjQXHSI7wMyuYXF8RNrBq18oa3ffDZuuk-Yiw','f7ff0eaf9b68f6d10df9a8a43641c84549f2f5edef2bbb1d223aa3af2ed7cb3a',49,1,0,0],
        'ddb_csae_600'=>['DDB12','1xsUOkcDT9LjwAXR7qjrTGH9OxEsH7MqSjxEQWkwgnkc','5e92834130f2af82b709a61a8054446079307aee0fc244c5fadc64a91f161215',600,4,500,1000],
        'ddb_ccad_600'=>['DDB','1JtCIYt7g64eusvVCQdWE_ghM292ibY71N8R3moDqrPQ','31f2a0e35eda83023642225a76f999271f15d5cb90caf53cf544f0e095b287c2',600,4,500,1000],
        'ddb_ccad_800'=>['DDB','1CeWuvHPA2d6fv8w_wXosDX4WmNIrWuLYydANHTOa9IA','86637bb8dbae726d3685183dcfcac67ceeae50c5148d267d94b32db9628e86aa',800,5,500,1000],
        'ddb_ccad_1200'=>['DDB','1l_4ENK1B5tcEulWjyAs5Byd-LedlgkiH2QeAqmhYe_U','20c55155aff7b9273db5e2bc08614148da7592c816db5aeba2091d6ba308b526',1200,6,500,1000],
        'ddb_trb_ccad_2000'=>['DDB-TRB','1Bm-ZOIabbdjANAP8ZFeE59E1Ajo9yvT-oi7VCK4OH3Y','b3b1622387da8badff8fe69b82116466a31428bb1123a7dbb4da21e18431a998',2000,10,1000,2000],
        'ddb_trb_ccad_3000'=>['DDB-TRB','1M25Fr0oaJu7Ld1QI_VD_zAyeAEqoSX12GPi31OL_-wI','8a87eca31f9beb0c0f00f5c56955b8f292387f6f8507563159182605dafc84da',3000,12,1000,2000],
        'ddb_trb_ccad_4000'=>['DDB-TRB','1OKkazZXljhR7n8wuxVW_tNG5zGZcTo3Mx8IUs25ti0o','d8c1f97ae98e11d8ec6c7d5d4e9ddf7819efb567ef8dbb397a1ea71216dc072b',4000,16,1000,2000],
        'ddb_trb_ccad_6000'=>['DDB-TRB','1nwkUgDhcvGf5D8pRLuBDCtPl6LJVNQ9elk5Dt58V3JI','69b0b07b32066a0672b68324988e9babfee1d2fcba0cf4422448043c2234991f',6000,15,1000,2000],
        'trb_ccde'=>['TRB','11QreYLe5GW4PsMW5pNR55MFxWR9N9PAj-1BfgLNAY3Q','e42852bbf415083e89346074b73424c37b2169d67d16c7904345decbc5ee4f0b',0,0,0,0],
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
