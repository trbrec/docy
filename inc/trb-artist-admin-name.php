<?php
/** Administrator-only stage-name editing on the users screen. */
if (!defined('ABSPATH')) exit;

function trb_artist_admin_name($id) {
 return trim((string)get_user_meta($id, '_trb_artist_artist_name', true));
}
function trb_artist_admin_save_name($id, $name, $expected) {
 $id = absint($id);
 if (!current_user_can('edit_users') || !current_user_can('edit_user', $id)) return new WP_Error('forbidden', 'Non puoi modificare questo utente.', ['status'=>403]);
 $u = get_userdata($id);
 if (!$u || !trb_portal_user_profile($u)) return new WP_Error('not_artist', 'Utente senza profilo artista.', ['status'=>400]);
 if (trb_artist_admin_name($id) !== $expected) return new WP_Error('conflict', 'Il nome è stato aggiornato. Ricarica la pagina prima di salvarlo.', ['status'=>409]);
 $name = trim(sanitize_text_field($name));
 if ($name === '' || strlen($name)>240) return new WP_Error('invalid_name', 'Inserisci un nome d’arte, fino a 240 byte.', ['status'=>400]);
 if (trb_portal_artist_name_owner($name, $id)) return new WP_Error('name_taken', 'Nome d’arte già assegnato a un altro artista.', ['status'=>409]);
 update_user_meta($id, '_trb_artist_artist_name', $name);
 if (trb_artist_admin_name($id)!==$name) return new WP_Error('save_failed', 'Salvataggio non riuscito.', ['status'=>500]);
 do_action('trb_artist_admin_name_saved', $id, $name);
 return ['id'=>$id,'name'=>$name];
}
add_filter('manage_users_columns', function($columns) {
 $columns['trb_artist_name'] = 'Nome d’arte';
 return $columns;
});
add_filter('manage_users_custom_column', function($value, $column, $id) {
 if ($column !== 'trb_artist_name') return $value;
 $u = get_userdata($id);
 if (!$u || !trb_portal_user_profile($u)) return '—';
 $name = trb_artist_admin_name($id);
 $display = $name ?: $u->display_name;
 $out = '<span class="trb-stage-name">'.esc_html($display).'</span>';
 if ($name==='') $out .= '<small style="display:block">Nome pubblico precedente</small>';
 if (current_user_can('edit_users') && current_user_can('edit_user', $id)) {
  $out .= '<div class="row-actions"><button type="button" class="button-link trb-name-edit" data-user="'.esc_attr($id).'" data-name="'.esc_attr($name).'" data-display="'.esc_attr($display).'">Modifica rapida</button></div>';
 }
 return $out;
}, 10, 3);
add_action('wp_ajax_trb_artist_admin_name', function() {
 if (!check_ajax_referer('trb_artist_admin_name', 'nonce', false)) wp_send_json_error(['message'=>'Sessione scaduta. Ricarica la pagina.'], 403);
 if (!isset($_POST['user_id'], $_POST['name'], $_POST['expected']) || !is_scalar($_POST['name']) || !is_scalar($_POST['expected'])) wp_send_json_error(['message'=>'Dati non validi.'], 400);
 $result = trb_artist_admin_save_name(absint($_POST['user_id']), wp_unslash($_POST['name']), wp_unslash($_POST['expected']));
 if (is_wp_error($result)) {
  $data = $result->get_error_data();
  wp_send_json_error(['message'=>$result->get_error_message()], is_array($data) ? ($data['status']??400) : 400);
 }
 wp_send_json_success($result);
});
add_action('admin_enqueue_scripts', function($hook) {
 if ($hook !== 'users.php' || !current_user_can('edit_users')) return;
 wp_enqueue_script('trb-admin-stage-name', get_template_directory_uri().'/assets/js/trb-admin-stage-name.js', [], '1.0.0', true);
 wp_localize_script('trb-admin-stage-name', 'trbAdminStageName', ['url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('trb_artist_admin_name')]);
});
