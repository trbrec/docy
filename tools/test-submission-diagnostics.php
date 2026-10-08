<?php
$source = file_get_contents(__DIR__ . '/../inc/trb-owner-dashboard.php');
$start = strpos($source, 'function trb_owner_dashboard_render_submission_diagnostics(');
$end = strpos($source, 'function trb_owner_dashboard_render()', $start);
if ($start === false || $end === false) throw new RuntimeException('Diagnostic function missing');
eval(substr($source, $start, $end - $start));
$allowed = false; $meta = array();
function current_user_can($cap) { global $allowed; return $allowed && $cap === 'manage_options'; }
function absint($value) { return abs((int) $value); }
function get_user_meta($id, $key, $single) { global $meta; return $meta[$key] ?? ''; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function wp_date($format, $timestamp) { return gmdate($format, $timestamp); }
function render_panel() { ob_start(); trb_owner_dashboard_render_submission_diagnostics(174); return ob_get_clean(); }
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); echo "PASS: $message\n"; }
check(render_panel() === '', 'Non-administrators cannot read diagnostics');
$allowed = true;
check(strpos(render_panel(), 'non dimostra') !== false, 'Missing error is not reported as success');
$meta['_trb_release_last_submission_error'] = array('code'=>'invalid','message'=>'<script>alert(1)</script>','http_status'=>422,'at'=>1788630000);
$meta['_trb_release_form_draft'] = array('pairs'=>array(
 array('trb_release_title','Example release'),
 array('trb_tracks[0][title]','Example track'),
 array('trb_portal_release_nonce','NEVER-RENDER-THIS'),
 array('trb_tracks[0][private_notes]','NEVER-RENDER-THIS-EITHER')
));
$html = render_panel();
check(strpos($html, '&lt;script&gt;') !== false && strpos($html, '<script>') === false, 'Stored error text is escaped');
check(strpos($html, 'Example track') !== false, 'Track title diagnostic is visible');
check(strpos($html, 'NEVER-RENDER') === false, 'Unlisted fields and nonce remain hidden');
check(strpos($html, '422') !== false, 'Server response status is retained');
