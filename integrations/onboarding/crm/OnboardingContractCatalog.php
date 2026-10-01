<?php
declare(strict_types=1);
namespace TrbCrm;
require_once __DIR__.'/OnboardingPolicy.php';

/** New copies only. Never replaces the registry used for historical proposals. */
final class OnboardingContractCatalog
{
    private const MODELS=[
        'dds_pimd_49'=>['DDS','1uiETHRrjQXHSI7wMyuYXF8RNrBq18oa3ffDZuuk-Yiw','52b4811f3e8c17d4097e11a6e84af5713166535e7ab9e435d7a20a7cff75d19f',49,1,0,0],
        'ddb_csae_600'=>['DDB12','1xsUOkcDT9LjwAXR7qjrTGH9OxEsH7MqSjxEQWkwgnkc','db94870fcd612ad3c763d7a2ef3d2529cbea723ee6f95151f550e61e939c2889',600,4,500,1000],
        'ddb_ccad_600'=>['DDB','1JtCIYt7g64eusvVCQdWE_ghM292ibY71N8R3moDqrPQ','ef1a802e272b9a34d6334d43eb145c2535cb22e7d915aff1bf17e14bbf9fe3cc',600,4,500,1000],
        'ddb_ccad_800'=>['DDB','1CeWuvHPA2d6fv8w_wXosDX4WmNIrWuLYydANHTOa9IA','2d4bc302408caef7e24d260f8d2a3bfc9a47ec89bf3dd2232960e329fd08a47f',800,5,500,1000],
        'ddb_ccad_1200'=>['DDB','1l_4ENK1B5tcEulWjyAs5Byd-LedlgkiH2QeAqmhYe_U','423b14d3dec92e76eee9b933d8dc640f7c7095851cf114faf73cdb20bc58759a',1200,6,500,1000],
        'ddb_trb_ccad_2000'=>['DDB-TRB','1Bm-ZOIabbdjANAP8ZFeE59E1Ajo9yvT-oi7VCK4OH3Y','527c2760dfb28c8c938fbd9bfbcedde6088c178bf2cba398bd2340c22892689e',2000,10,1000,2000],
        'ddb_trb_ccad_3000'=>['DDB-TRB','1M25Fr0oaJu7Ld1QI_VD_zAyeAEqoSX12GPi31OL_-wI','572fccc2ffeb4ed4636f404f75890028538156f7f49faca64697ee7587e2dbfe',3000,12,1000,2000],
        'ddb_trb_ccad_4000'=>['DDB-TRB','1OKkazZXljhR7n8wuxVW_tNG5zGZcTo3Mx8IUs25ti0o','0504c659064b3858b418ae604b17b647353862ed991102c6ca6bc026dc5e1c1d',4000,16,1000,2000],
        'ddb_trb_ccad_6000'=>['DDB-TRB','1nwkUgDhcvGf5D8pRLuBDCtPl6LJVNQ9elk5Dt58V3JI','3e684b9e05dbe6466615dd55d766c33b05788505653a6f5404137762bd2a4e3e',6000,15,1000,2000],
        'trb_ccde'=>['TRB','11QreYLe5GW4PsMW5pNR55MFxWR9N9PAj-1BfgLNAY3Q','4c699fdf7a422eba7743a9a16b3b97060d4b39d9645146e3ebb11a543e45be1a',0,0,0,0],
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
