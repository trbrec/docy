<?php
if(!defined('ABSPATH')||!trb_onboarding_is_privacy_page()){http_response_code(404);exit;}
header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store, max-age=0');
header('Referrer-Policy: no-referrer');header('X-Robots-Tag: noindex, nofollow');header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
$privacy_page=get_post((int)get_option('wp_page_for_privacy_policy'));
?><!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Informativa privacy · TRB rec</title><style>
*{box-sizing:border-box}body{margin:0;background:#f4f6f8;color:#19283e;font:17px/1.7 system-ui,sans-serif}main{max-width:840px;margin:32px auto;padding:0 24px}.brand{font-size:20px;font-weight:750}h1,h2{line-height:1.3}h1{font-size:30px}h2{font-size:23px;margin-top:30px}article{background:#fff;border:1px solid #dce3ed;border-radius:12px;padding:28px;overflow-wrap:anywhere}a{color:#245b9a}li{margin:12px 0}footer{margin:24px 0;color:#52627b}@media(max-width:600px){main{padding:0 16px;margin:24px auto}article{padding:20px}h1{font-size:26px}h2{font-size:21px}}
</style></head><body><main><header><div class="brand">TRB rec</div><h1>Informativa privacy</h1></header><article><?php echo wp_kses_post($privacy_page->post_content); ?></article><footer>Puoi chiudere questa scheda per tornare alla tua adesione.</footer></main></body></html>
