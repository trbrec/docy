<?php
/**
 * Plugin Name: TRB Site Studio
 * Description: Editor visuale amministratore e directory pubblica collegata al Portale Artisti.
 * Version: 0.1.7
 * Requires PHP: 8.1
 */
namespace TRB\Studio;
if (!defined('ABSPATH')) exit;
const VERSION = '0.1.7';
require_once __DIR__.'/editor.php';
require_once __DIR__.'/portal.php';
require_once __DIR__.'/bundle.php';
require_once __DIR__.'/directory.php';
function source_site() { return strtolower((string)wp_parse_url(home_url(), PHP_URL_HOST)) === 'artist.trbrec.com'; }
function destination_site() { return strtolower((string)wp_parse_url(home_url(), PHP_URL_HOST)) === 'new1.trbrec.com'; }
function admin_permission() { return current_user_can('manage_options'); }
add_action('rest_api_init', function () {
 if (destination_site()) {
  register_rest_route('trb-studio/v1','/editor/(?P<id>\d+)', ['methods'=>'GET','permission_callback'=>__NAMESPACE__.'\\editor_permission','callback'=>__NAMESPACE__.'\\editor_manifest']);
  register_rest_route('trb-studio/v1','/editor/(?P<id>\d+)', ['methods'=>'POST','permission_callback'=>__NAMESPACE__.'\\editor_permission','callback'=>__NAMESPACE__.'\\editor_save']);
 }
 if (source_site()) {
  register_rest_route('trb-studio/v1','/snapshot', ['methods'=>'GET','permission_callback'=>__NAMESPACE__.'\\admin_permission','callback'=>__NAMESPACE__.'\\portal_snapshot']);
  register_rest_route('trb-studio/v1','/asset/(?P<kind>artist|release)/(?P<id>\d+)', ['methods'=>'GET','permission_callback'=>__NAMESPACE__.'\\admin_permission','callback'=>__NAMESPACE__.'\\portal_asset']);
 }
});
add_filter('cron_schedules', function($s){$s['trb_studio_15min']=['interval'=>900,'display'=>'TRB Studio: 15 minuti'];return $s;});
add_action('init', function(){if(destination_site() && get_option('trb_studio_enabled') && !wp_next_scheduled('trb_studio_sync')) wp_schedule_event(time()+60,'trb_studio_15min','trb_studio_sync');});
add_action('trb_studio_sync', __NAMESPACE__.'\\sync_directory');
register_deactivation_hook(__FILE__,function(){wp_clear_scheduled_hook('trb_studio_sync');});
add_action('admin_menu',function(){add_options_page('TRB Site Studio','TRB Site Studio','manage_options','trb-site-studio',__NAMESPACE__.'\\settings_page');});
function settings_page(){
 if(!admin_permission()) return;
 $notice='';
 if(isset($_POST['trb_studio_save'])){
  check_admin_referer('trb_studio_settings');
  if(destination_site()){
   if(get_option('trb_studio_transport')!=='private-bundle')update_option('trb_studio_user',sanitize_text_field(wp_unslash($_POST['portal_user']??'')),false);
   if(!empty($_POST['portal_password'])) update_option('trb_studio_password',trim(wp_unslash($_POST['portal_password'])),false);
   update_option('trb_studio_enabled',!empty($_POST['enabled']),false);
   if(empty($_POST['enabled'])) wp_clear_scheduled_hook('trb_studio_sync');
  }
  if(source_site()){
   $states=array_values(array_filter(array_map('sanitize_key',explode(',',wp_unslash($_POST['states']??'')))));
   update_option('trb_studio_distribution_states',$states,false);
  }
  $notice='Impostazioni salvate.';
 }
 if(isset($_POST['trb_studio_sync_now'])){check_admin_referer('trb_studio_settings');$r=sync_directory();$notice=is_wp_error($r)?$r->get_error_message():'Sincronizzazione completata.';}
 echo '<div class="wrap"><h1>TRB Site Studio</h1><p>'.esc_html($notice).'</p>';
 if(!source_site()&&!destination_site()){echo '<p>Installazione non abilitata: questa versione opera solo su artist.trbrec.com e new1.trbrec.com.</p></div>';return;}
 echo '<form method="post">';wp_nonce_field('trb_studio_settings');
 if(source_site()){
  echo '<p>Esporta esclusivamente profili TRB approvati e materiali artistici. Account di prova e documenti amministrativi sono esclusi.</p><p><label>Stati CRM che attestano la distribuzione approvata<br><input class="regular-text" name="states" value="'.esc_attr(implode(',',get_option('trb_studio_distribution_states',[]))).'"></label></p><p>Valori separati da virgole del campo <code>_trb_crm_workflow_status</code>. Da verificare sul portale live: lasciare vuoto fino alla verifica. Lo stato tecnico “approved” non è una decisione di distribuzione.</p>';
 }else{
  if(get_option('trb_studio_transport')==='private-bundle')echo '<p>Collegamento server attivo: i profili approvati e le release elaborate vengono aggiornati automaticamente. Puoi eseguire qui una sincronizzazione manuale.</p><p><label><input type="checkbox" name="enabled" value="1" '.checked(get_option('trb_studio_enabled'),true,false).'> Aggiorna automaticamente ogni 15 minuti</label></p><p>Il trasferimento server è programmato ogni 15 minuti; eventuali ritardi del servizio di pianificazione compaiono nell’ultimo esito.</p>';
  else echo '<p>Il collegamento legge esclusivamente gli endpoint filtrati di artist.trbrec.com. Le credenziali restano sul server.</p><p><label>Utente del portale<br><input autocomplete="username" name="portal_user" value="'.esc_attr(get_option('trb_studio_user','')).'"></label></p><p><label>Password applicativa del portale<br><input type="password" autocomplete="new-password" name="portal_password" value=""></label> Lascia vuoto per conservarla.</p><p><label><input type="checkbox" name="enabled" value="1" '.checked(get_option('trb_studio_enabled'),true,false).'> Aggiorna automaticamente ogni 15 minuti</label></p><p>WordPress Cron dipende dalle visite; per intervalli regolari utilizzare il cron dell’hosting.</p>';
  echo '<p>Ultimo esito: '.esc_html(wp_json_encode(get_option('trb_studio_last_sync',[]),JSON_UNESCAPED_UNICODE)).'</p>';
 }
 submit_button('Salva impostazioni','primary','trb_studio_save');
 if(destination_site()) submit_button('Sincronizza adesso','secondary','trb_studio_sync_now');
 echo '</form></div>';
}
