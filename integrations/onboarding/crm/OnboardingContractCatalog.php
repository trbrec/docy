<?php
declare(strict_types=1);
namespace TrbCrm;
require_once __DIR__.'/OnboardingPolicy.php';

/** New copies only. Never replaces the registry used for historical proposals. */
final class OnboardingContractCatalog
{
    private const MODELS=[
        'dds_pimd_49'=>['DDS','1uiETHRrjQXHSI7wMyuYXF8RNrBq18oa3ffDZuuk-Yiw','279059b18b70edbbfe5826ad7aca356521225c0c87567da7479131da0ebd9258',49,1,0,0],
        'ddb_csae_600'=>['DDB12','1xsUOkcDT9LjwAXR7qjrTGH9OxEsH7MqSjxEQWkwgnkc','d47cf7ca9808e788f8c32000a2e834eb2f792d1f912754a65a7bbd082bfadffd',600,4,500,1000],
        'ddb_ccad_600'=>['DDB','1JtCIYt7g64eusvVCQdWE_ghM292ibY71N8R3moDqrPQ','36433f99844d99a8927cbcf7e5a0b809cf0c2392ac934eb1a31052af3693cf67',600,4,500,1000],
        'ddb_ccad_800'=>['DDB','1CeWuvHPA2d6fv8w_wXosDX4WmNIrWuLYydANHTOa9IA','b581b1a2a5ee6810bd04c2162c994c78c95a433e931cdf1f74e38736be2a371e',800,5,500,1000],
        'ddb_ccad_1200'=>['DDB','1l_4ENK1B5tcEulWjyAs5Byd-LedlgkiH2QeAqmhYe_U','719807fdc9791481f370b845ed699cfbf5a82391dead7fb4e2e2dab5e6d43461',1200,6,500,1000],
        'ddb_trb_ccad_2000'=>['DDB-TRB','1Bm-ZOIabbdjANAP8ZFeE59E1Ajo9yvT-oi7VCK4OH3Y','685837639317a809f1b9782a9c8ee4db41baa5b86ba99f277b6ba8f57a1477e7',2000,10,1000,2000],
        'ddb_trb_ccad_3000'=>['DDB-TRB','1M25Fr0oaJu7Ld1QI_VD_zAyeAEqoSX12GPi31OL_-wI','d0bc4dcbe01733ec868bec4f9254ac78e90a9e1d51d247ac1f241109965c08ea',3000,12,1000,2000],
        'ddb_trb_ccad_4000'=>['DDB-TRB','1OKkazZXljhR7n8wuxVW_tNG5zGZcTo3Mx8IUs25ti0o','67d3d2842333dd27e167cfbf57cf64790fa089e5a4124be080b5da295a77bfff',4000,16,1000,2000],
        'ddb_trb_ccad_6000'=>['DDB-TRB','1nwkUgDhcvGf5D8pRLuBDCtPl6LJVNQ9elk5Dt58V3JI','6cf8a84ebef5205b8eb8a25ee094044fef02d77a4a85ea36ab450ed9fea78a7f',6000,15,1000,2000],
        'trb_ccde'=>['TRB','11QreYLe5GW4PsMW5pNR55MFxWR9N9PAj-1BfgLNAY3Q','2705471aeaaaf1b04d9f40324f0c696d3fc7edb0d0afed123a08e9eae39b50aa',0,0,0,0],
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
