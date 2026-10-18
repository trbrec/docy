<?php
/** Isolated, opt-in WooCommerce adapter. No payment is captured by these routes. */
if (!defined('ABSPATH')) exit;

function trb_onboarding_deferral_policy() { return '2026.10.18-deferral-v1'; }
function trb_onboarding_deferral_enabled() { return in_array(get_option('trb_onboarding_deferral_payments_enabled', false), array(true, 1, '1'), true); }
function trb_onboarding_deferral_error($message, $status = 409) { return new WP_Error('onboarding_deferral', $message, array('status' => $status)); }
function trb_onboarding_deferral_today() { return wp_date('Y-m-d', (int)current_time('timestamp', true), new DateTimeZone('Europe/Rome')); }
function trb_onboarding_deferral_expires($paidOn) {
    $anchor = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$paidOn, new DateTimeZone('Europe/Rome'));
    if (!$anchor || $anchor->format('Y-m-d') !== $paidOn) throw new RuntimeException('Data di incasso del deposito da verificare.');
    $month = $anchor->modify('first day of this month')->modify('+6 months');
    return $month->setDate((int)$month->format('Y'), (int)$month->format('m'), min((int)$anchor->format('d'), (int)$month->format('t')))->format('Y-m-d');
}

/** This exact object is both the immutable request binding and the response echo. */
function trb_onboarding_deferral_binding($p) {
    $email = (string)($p['email'] ?? '');
    if (($p['purpose'] ?? '') !== 'activation_deferral' || ($p['policy_version'] ?? '') !== trb_onboarding_deferral_policy()
        || ($p['amount_cents'] ?? null) !== 10000 || ($p['currency'] ?? '') !== 'EUR'
        || !is_email($email) || strtolower(sanitize_email($email)) !== strtolower($email)) throw new RuntimeException('Dati del versamento di proroga non validi.');
    foreach (array('practice_id' => 32, 'deferral_id' => 32, 'request_key' => 64, 'proposal_sha256' => 64, 'snapshot_sha256' => 64) as $field => $length) {
        if (!preg_match('/^[a-f0-9]{'.$length.'}$/D', (string)($p[$field] ?? ''))) throw new RuntimeException('Riferimenti del versamento di proroga non validi.');
    }
    return array('purpose' => 'activation_deferral', 'practice_id' => $p['practice_id'], 'deferral_id' => $p['deferral_id'], 'request_key' => $p['request_key'],
        'proposal_sha256' => $p['proposal_sha256'], 'snapshot_sha256' => $p['snapshot_sha256'], 'policy_version' => trb_onboarding_deferral_policy(),
        'email' => strtolower($email), 'amount_cents' => 10000, 'currency' => 'EUR');
}
function trb_onboarding_deferral_same($a, $b) { return is_array($a) && is_array($b) && $a === $b; }
function trb_onboarding_deferral_request_key($binding) { return 'trb_onboarding_deferral_request_'.$binding['request_key']; }
function trb_onboarding_deferral_lock($name) {
    $key = 'trb_onboarding_deferral_lock_'.hash('sha256', $name);
    $value = wp_json_encode(array('token' => bin2hex(random_bytes(16)), 'expires' => time() + 600));
    if (!add_option($key, $value, '', 'no')) {
        $old = get_option($key); $decoded = json_decode((string)$old, true);
        if (is_array($decoded) && ($decoded['expires'] ?? PHP_INT_MAX) < time()) {
            global $wpdb; $wpdb->delete($wpdb->options, array('option_name' => $key, 'option_value' => $old)); wp_cache_delete($key, 'options');
        }
        if (!add_option($key, $value, '', 'no')) throw new RuntimeException('Versamento in preparazione. Riprova tra poco.');
    }
    return array($key, $value);
}
function trb_onboarding_deferral_unlock($lock) {
    if (!$lock) return;
    global $wpdb; $wpdb->delete($wpdb->options, array('option_name' => $lock[0], 'option_value' => $lock[1])); wp_cache_delete($lock[0], 'options');
}
function trb_onboarding_deferral_write($key, $value) {
    update_option($key, $value, false);
    if (get_option($key, null) !== $value) throw new RuntimeException('Registro del versamento non disponibile: non ripetere il pagamento.');
}
function trb_onboarding_deferral_index($key, $value) {
    if (!add_option($key, $value, '', 'no') && get_option($key, null) !== $value) throw new RuntimeException('Versamento già associato a riferimenti diversi.');
}

/** Ordinary and credited installments share a durable, fail-closed creation intent. */
function trb_onboarding_store_option_compare_exchange($key, $previous, $next) {
    if ($previous === $next) {
        if (get_option($key, null) !== $next) throw new RuntimeException('Registro della rata modificato.');
        return; // Never rewrite a value another worker may already have advanced.
    }
    if ($previous === null) {
        if (!add_option($key, $next, '', 'no')) throw new RuntimeException('Registro della rata già modificato.');
    } else {
        global $wpdb;
        $changed = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND BINARY option_value=BINARY %s", serialize($next), $key, serialize($previous)));
        if ($changed !== 1) throw new RuntimeException('Registro della rata modificato: verifica amministrativa necessaria.');
        wp_cache_delete($key, 'options'); wp_cache_delete('alloptions', 'options'); wp_cache_delete('notoptions', 'options');
    }
    if (get_option($key, null) !== $next) throw new RuntimeException('Registrazione della rata da verificare.');
}
function trb_onboarding_store_attempt_check($key, $attempt, $practiceId, $number) {
    if (!is_array($attempt) || ($attempt['version'] ?? null) !== 1
        || !preg_match('/^[a-f0-9]{32}$/D', (string)($attempt['token'] ?? ''))
        || !is_array($attempt['binding'] ?? null) || !is_array($attempt['previous_ids'] ?? null)
        || ($attempt['binding']['practice_id'] ?? null) !== $practiceId || ($attempt['binding']['number'] ?? null) !== $number
        || $key !== 'trb_onboarding_order_'.$practiceId.'_'.$number
        || !in_array($attempt['state'] ?? null, array('allocating', 'allocated', 'complete'), true)
        || !is_int($attempt['order_id'] ?? null) || $attempt['order_id'] < 0) throw new RuntimeException('Registro della rata da verificare: non ripetere il pagamento.');
}
function trb_onboarding_store_attempt_order($attempt) {
    $binding = $attempt['binding']; $order = wc_get_order($attempt['order_id']);
    if (!$order || $order->get_meta('_trb_onboarding_attempt_token') !== $attempt['token']
        || $order->get_meta('_trb_onboarding_practice_id') !== $binding['practice_id']
        || (int)$order->get_meta('_trb_onboarding_number') !== $binding['number']
        || (int)$order->get_meta('_trb_onboarding_amount_cents') !== $binding['amount_cents']
        || (int)$order->get_meta('_trb_onboarding_installment_total_cents') !== $binding['installment_total_cents']
        || $order->get_meta('_trb_onboarding_snapshot_sha256') !== $binding['snapshot_sha256']
        || strtolower($order->get_billing_email()) !== $binding['email'] || $order->get_currency() !== 'EUR'
        || (int)round((float)$order->get_total() * 100) !== $binding['amount_cents']) throw new RuntimeException('Preparazione della rata incompleta: verifica amministrativa necessaria.');
    trb_onboarding_deferral_installment_matches($order, $binding['credit']);
    return $order;
}
function trb_onboarding_store_attempt_complete($key, $ids, $attempt) {
    if (get_option($key.'_attempt', null) !== $attempt || $attempt['state'] !== 'allocated' || $attempt['order_id'] < 1) throw new RuntimeException('Intento della rata da verificare.');
    trb_onboarding_store_attempt_order($attempt);
    $expected = $attempt['previous_ids']; $expected[] = $attempt['order_id'];
    if ($ids !== $attempt['previous_ids'] && $ids !== $expected) throw new RuntimeException('Storico della rata modificato: verifica amministrativa necessaria.');
    $currentIndex = get_option($key, null);
    if ($currentIndex !== null && $currentIndex !== $ids) throw new RuntimeException('Indice della rata modificato.');
    trb_onboarding_store_option_compare_exchange($key, $currentIndex, $expected);
    $complete = $attempt; $complete['state'] = 'complete';
    trb_onboarding_store_option_compare_exchange($key.'_attempt', $attempt, $complete);
    return $expected;
}
function trb_onboarding_store_attempt_recover($key, $ids, $practiceId, $number) {
    $attempt = get_option($key.'_attempt', null);
    if ($attempt === null) return $ids; // Existing legacy pending and completed orders remain valid.
    trb_onboarding_store_attempt_check($key, $attempt, $practiceId, $number);
    if ($attempt['state'] === 'complete') {
        if ($attempt['order_id'] < 1 || !in_array($attempt['order_id'], $ids, true)) throw new RuntimeException('Ordine della rata non indicizzato: verifica amministrativa necessaria.');
        return $ids;
    }
    if ($attempt['state'] !== 'allocated' || $attempt['order_id'] < 1) throw new RuntimeException('Esito della preparazione da verificare: non creare un altro versamento.');
    return trb_onboarding_store_attempt_complete($key, $ids, $attempt);
}
function trb_onboarding_store_attempt_reserve($key, $ids, $p, $number, $amount, $total, $email, $credit) {
    $previous = get_option($key.'_attempt', null);
    if ($previous !== null) {
        trb_onboarding_store_attempt_check($key, $previous, $p['practice_id'], $number);
        if ($previous['state'] !== 'complete' || !in_array($previous['order_id'], $ids, true)) throw new RuntimeException('Tentativo precedente da verificare: non ripetere il pagamento.');
    }
    $attempt = array('version' => 1, 'token' => bin2hex(random_bytes(16)), 'state' => 'allocating', 'order_id' => 0, 'previous_ids' => $ids,
        'binding' => array('practice_id' => $p['practice_id'], 'number' => $number, 'amount_cents' => $amount, 'installment_total_cents' => $total,
            'snapshot_sha256' => $p['snapshot_sha256'], 'email' => strtolower($email), 'credit' => $credit));
    trb_onboarding_store_option_compare_exchange($key.'_attempt', $previous, $attempt);
    return $attempt;
}
function trb_onboarding_store_attempt_allocated($key, $attempt, $order) {
    if (get_option($key.'_attempt', null) !== $attempt || !$order || !is_int($order->get_id()) || $order->get_id() < 1) throw new RuntimeException('Allocazione della rata da verificare.');
    $allocated = $attempt; $allocated['state'] = 'allocated'; $allocated['order_id'] = $order->get_id();
    trb_onboarding_store_option_compare_exchange($key.'_attempt', $attempt, $allocated);
    return $allocated;
}
function trb_onboarding_deferral_order_matches($order, $binding) {
    if (!$order || $order->get_meta('_trb_onboarding_deferral_binding') !== $binding || $order->get_meta('_trb_onboarding_purpose') !== 'activation_deferral'
        || $order->get_meta('_trb_onboarding_practice_id') !== $binding['practice_id'] || $order->get_meta('_trb_onboarding_deferral_id') !== $binding['deferral_id']
        || $order->get_meta('_trb_onboarding_deferral_request_key') !== $binding['request_key'] || (int)$order->get_meta('_trb_onboarding_amount_cents') !== 10000
        || $order->get_meta('_trb_onboarding_snapshot_sha256') !== $binding['snapshot_sha256'] || strtolower($order->get_billing_email()) !== $binding['email']
        || $order->get_currency() !== 'EUR' || (int)round((float)$order->get_total() * 100) !== 10000
        || (int)$order->get_meta('_trb_onboarding_number') !== 0) throw new RuntimeException('Ordine di proroga non coerente con la proposta.');
    return $order;
}
/** Read-only recovery. An unresolved create intent must never create a second order. */
function trb_onboarding_deferral_find($binding, $requestedOrderId = 0) {
    $ledger = get_option(trb_onboarding_deferral_request_key($binding), null);
    if (!$ledger) return null;
    if (!is_array($ledger) || !trb_onboarding_deferral_same($ledger['binding'] ?? null, $binding)
        || get_option('trb_onboarding_deferral_id_'.$binding['deferral_id'], null) !== $binding['request_key']
        || get_option('trb_onboarding_deferral_practice_'.$binding['practice_id'], null) !== $binding['deferral_id']) throw new RuntimeException('Registro del versamento non coerente.');
    $id = (int)($ledger['order_id'] ?? 0);
    if ($id > 0) {
        if ($requestedOrderId && $requestedOrderId !== $id) throw new RuntimeException('Ordine non associato a questa richiesta.');
        return trb_onboarding_deferral_order_matches(wc_get_order($id), $binding);
    }
    throw new RuntimeException('Esito della preparazione da verificare: non creare un altro versamento.');
}
function trb_onboarding_deferral_proof($order, $binding) {
    trb_onboarding_deferral_order_matches($order, $binding);
    $capture = trb_onboarding_store_capture($order); $refunded = (int)round((float)$order->get_total_refunded() * 100);
    $verified = $capture['captured'] && $order->is_paid(); $date = $capture['date'];
    $unsafeHold = $order->has_status('on-hold') && $order->get_payment_method() !== 'bacs';
    $status = $refunded > 0 ? 'reversed' : ($verified ? 'confirmed' : ($unsafeHold || $capture['captured'] || $order->get_transaction_id() !== '' || $order->has_status(array('cancelled', 'refunded', 'completed', 'processing')) ? 'review' : 'pending'));
    $checkout = $status === 'pending' && $order->has_status(array('pending', 'failed', 'on-hold'))
        ? ($order->has_status('on-hold') && $order->get_payment_method() === 'bacs' ? $order->get_checkout_order_received_url() : $order->get_checkout_payment_url()) : '';
    return $binding + array('order_id' => $order->get_id(), 'checkout_url' => $checkout, 'paid' => $status === 'confirmed', 'status' => $status,
        'captured' => $capture['captured'], 'refunded_cents' => $refunded, 'provider' => 'woocommerce',
        'transaction_id' => $capture['transaction'] === '' ? '' : 'order:'.$order->get_id().':'.hash('sha256', $capture['transaction']),
        'paid_on' => $date ? wp_date('Y-m-d', $date->getTimestamp(), new DateTimeZone('Europe/Rome')) : null,
        'awaiting_bank_transfer' => $order->get_payment_method() === 'bacs' && $order->has_status('on-hold') && !$capture['captured']);
}
function trb_onboarding_deferral_order($request) {
    if (!trb_onboarding_deferral_enabled() || !get_option('trb_onboarding_payments_enabled', false) || !function_exists('wc_create_order')) return trb_onboarding_deferral_error('Versamenti di proroga non ancora abilitati.', 503);
    $lock = null;
    try {
        $p = $request->get_json_params(); $binding = trb_onboarding_deferral_binding($p);
        $lock = trb_onboarding_deferral_lock('practice:'.$binding['practice_id']);
        $existing = trb_onboarding_deferral_find($binding);
        if ($existing) return trb_onboarding_deferral_proof($existing, $binding);
        // Validate billing before a durable create intent, including WC country support.
        $billing = (array)($p['billing'] ?? array());
        foreach (array('first_name', 'last_name', 'address_1', 'city', 'postcode', 'country') as $field) if (empty($billing[$field])) throw new RuntimeException('Completa i dati amministrativi prima del versamento.');
        if (empty($p['tax_code']) || !array_key_exists(strtoupper((string)$billing['country']), WC()->countries->get_countries())) throw new RuntimeException('Dati amministrativi del versamento non validi.');
        $billing = array_intersect_key($billing, array_flip(array('first_name', 'last_name', 'address_1', 'address_2', 'city', 'postcode', 'country', 'state', 'phone')));
        $billing['country'] = strtoupper($billing['country']); $billing['email'] = $binding['email'];
        $indices = array('trb_onboarding_deferral_id_'.$binding['deferral_id'] => $binding['request_key'], 'trb_onboarding_deferral_practice_'.$binding['practice_id'] => $binding['deferral_id']);
        foreach ($indices as $key => $value) if (get_option($key, $value) !== $value) throw new RuntimeException('Versamento già associato a riferimenti diversi.');
        foreach ($indices as $key => $value) trb_onboarding_deferral_index($key, $value);
        $ledger = array('binding' => $binding, 'order_id' => 0, 'state' => 'creating');
        if (!add_option(trb_onboarding_deferral_request_key($binding), $ledger, '', 'no')) throw new RuntimeException('Richiesta già registrata: verifica lo stato prima di procedere.');
        $order = wc_create_order(array('created_via' => 'trb-activation-deferral'));
        if (is_wp_error($order)) throw new RuntimeException('Preparazione non riuscita: verifica lo stato prima di ripetere.');
        // Bind the newly allocated ID before the first metadata/tax write. A crash is review-only, never a second create.
        $ledger['order_id'] = $order->get_id(); trb_onboarding_deferral_write(trb_onboarding_deferral_request_key($binding), $ledger);
        $order->set_currency('EUR'); $order->set_address(array_map('sanitize_text_field', $billing), 'billing');
        $fee = new WC_Order_Item_Fee(); $fee->set_name('Versamento per proroga dell’avvio della collaborazione');
        $taxes = wc_tax_enabled() ? WC_Tax::find_rates(array('country' => $billing['country'], 'state' => $billing['state'] ?? '', 'postcode' => $billing['postcode'], 'city' => $billing['city'], 'tax_class' => '')) : array();
        $inclusive = $taxes ? array_sum(WC_Tax::calc_inclusive_tax(100, $taxes)) : 0;
        $fee->set_amount(100 - $inclusive); $fee->set_total(100 - $inclusive); $fee->set_tax_status($taxes ? 'taxable' : 'none'); $order->add_item($fee);
        foreach (array('_trb_onboarding_purpose' => 'activation_deferral', '_trb_onboarding_practice_id' => $binding['practice_id'], '_trb_onboarding_deferral_id' => $binding['deferral_id'],
            '_trb_onboarding_deferral_request_key' => $binding['request_key'], '_trb_onboarding_deferral_binding' => $binding, '_trb_onboarding_amount_cents' => 10000,
            '_trb_onboarding_snapshot_sha256' => $binding['snapshot_sha256'], '_billing_fiscal_code' => sanitize_text_field($p['tax_code'])) as $key => $value) $order->update_meta_data($key, $value);
        foreach (array('vat_number', 'sdi_code', 'pec') as $field) if (isset($p['invoice'][$field])) $order->update_meta_data('_billing_'.$field, sanitize_text_field($p['invoice'][$field]));
        $order->calculate_totals(true);
        if ((int)round((float)$order->get_total() * 100) !== 10000) { $order->update_status('cancelled', 'Importo fiscale non coerente: nessun addebito richiesto.'); throw new RuntimeException('Calcolo fiscale da verificare prima del versamento.'); }
        $order->save();
        $ledger['order_id'] = $order->get_id(); $ledger['state'] = 'created'; trb_onboarding_deferral_write(trb_onboarding_deferral_request_key($binding), $ledger);
        return trb_onboarding_deferral_proof($order, $binding);
    } catch (Throwable $e) { return trb_onboarding_deferral_error($e->getMessage()); }
    finally { trb_onboarding_deferral_unlock($lock); }
}
function trb_onboarding_deferral_status($request) {
    if (!function_exists('wc_get_order')) return trb_onboarding_deferral_error('Verifica versamento non disponibile.', 503);
    try {
        $p = $request->get_json_params(); $binding = trb_onboarding_deferral_binding($p);
        if (isset($p['order_id']) && (!is_int($p['order_id']) || $p['order_id'] < 1)) throw new RuntimeException('Riferimento ordine non valido.');
        $order = trb_onboarding_deferral_find($binding, (int)($p['order_id'] ?? 0));
        return $order ? trb_onboarding_deferral_proof($order, $binding) : trb_onboarding_deferral_error('Versamento non registrato.', 404);
    } catch (Throwable $e) { return trb_onboarding_deferral_error($e->getMessage()); }
}

/** Resolve a real, fully paid and unrefunded deposit. Never trust a CRM/browser paid flag. */
function trb_onboarding_deferral_credit_context($p, $number, $requireEnabled = true) {
    $hasCredit = array_key_exists('credit_cents', $p) || array_key_exists('deferral_id', $p);
    if (!$hasCredit) return null;
    if (($requireEnabled && !trb_onboarding_deferral_enabled()) || $number !== 1 || ($p['purpose'] ?? '') !== 'activation_installment'
        || ($p['credit_cents'] ?? null) !== 10000 || ($p['policy_version'] ?? '') !== trb_onboarding_deferral_policy()
        || !preg_match('/^[a-f0-9]{32}$/D', (string)($p['deferral_id'] ?? '')) || !preg_match('/^[a-f0-9]{64}$/D', (string)($p['proposal_sha256'] ?? ''))
        || ($p['installment_total_cents'] ?? 0) <= 10000) throw new RuntimeException('Credito di proroga non applicabile a questa rata.');
    $requestKey = get_option('trb_onboarding_deferral_id_'.$p['deferral_id'], null);
    $ledger = is_string($requestKey) ? get_option('trb_onboarding_deferral_request_'.$requestKey, null) : null;
    $binding = is_array($ledger) ? ($ledger['binding'] ?? null) : null;
    if (!is_array($binding) || ($binding['deferral_id'] ?? '') !== $p['deferral_id'] || ($binding['practice_id'] ?? '') !== ($p['practice_id'] ?? '')
        || ($binding['email'] ?? '') !== strtolower((string)($p['email'] ?? '')) || ($binding['proposal_sha256'] ?? '') !== $p['proposal_sha256']
        || ($binding['snapshot_sha256'] ?? '') !== ($p['snapshot_sha256'] ?? '') || ($binding['policy_version'] ?? '') !== $p['policy_version']) throw new RuntimeException('Credito non associato a questa proposta.');
    $order = trb_onboarding_deferral_find($binding); $proof = $order ? trb_onboarding_deferral_proof($order, $binding) : null;
    if (!$proof || $proof['status'] !== 'confirmed' || !$proof['captured'] || $proof['refunded_cents'] !== 0) throw new RuntimeException('Il versamento di proroga non risulta interamente incassato e disponibile.');
    $credit = array('purpose' => 'activation_installment', 'credit_cents' => 10000, 'deferral_id' => $p['deferral_id'], 'proposal_sha256' => $p['proposal_sha256'],
        'policy_version' => $p['policy_version'], 'practice_id' => $p['practice_id'], 'email' => strtolower($p['email']), 'snapshot_sha256' => $p['snapshot_sha256'],
        'number' => $number, 'installment_total_cents' => $p['installment_total_cents'], 'deposit_order_id' => $order->get_id());
    $today = trb_onboarding_deferral_today(); $expires = trb_onboarding_deferral_expires($proof['paid_on']);
    if ($requireEnabled && ($today < $proof['paid_on'] || ($today > $expires && !trb_onboarding_deferral_first_net_captured($credit, $proof['paid_on'], $expires)))) throw new RuntimeException('Il termine per il primo versamento è scaduto: richiedi una verifica amministrativa.');
    return $credit;
}
/** An initial NET capture in the allowed window changes the regime; later refund debts remain payable. */
function trb_onboarding_deferral_first_net_captured($credit, $paidOn, $expires) {
    if (get_option('trb_onboarding_deferral_credit_'.$credit['deferral_id'], null) !== $credit) return false;
    $ids = array_unique(array_filter(array_map('intval', (array)get_option('trb_onboarding_order_'.$credit['practice_id'].'_1', array()))));
    foreach ($ids as $id) {
        $past = wc_get_order($id);
        if (!$past || $past->get_meta('_trb_onboarding_purpose') !== 'activation_installment' || $past->get_meta('_trb_onboarding_deferral_credit') !== $credit
            || $past->get_meta('_trb_onboarding_practice_id') !== $credit['practice_id'] || $past->get_meta('_trb_onboarding_snapshot_sha256') !== $credit['snapshot_sha256']
            || strtolower($past->get_billing_email()) !== $credit['email'] || (int)$past->get_meta('_trb_onboarding_number') !== 1
            || (int)$past->get_meta('_trb_onboarding_installment_total_cents') !== $credit['installment_total_cents']
            || (int)$past->get_meta('_trb_onboarding_amount_cents') !== $credit['installment_total_cents'] - 10000
            || !$past->has_status(array('processing', 'completed', 'refunded'))) continue;
        $capture = trb_onboarding_store_capture($past); $refunded = (int)round((float)$past->get_total_refunded() * 100);
        if (!$capture['captured'] || !$capture['date'] || $refunded < 0 || $refunded > $capture['amount_cents'] || ($past->has_status('refunded') && $refunded < 1)) continue;
        $date = wp_date('Y-m-d', $capture['date']->getTimestamp(), new DateTimeZone('Europe/Rome'));
        if ($date >= $paidOn && $date <= $expires && $date <= trb_onboarding_deferral_today()) return true;
    }
    return false;
}
function trb_onboarding_deferral_claim_credit($credit) {
    if (!$credit) return;
    // Atomic, immutable and separate from attempts. Retries for this same installment are allowed.
    trb_onboarding_deferral_index('trb_onboarding_deferral_credit_'.$credit['deferral_id'], $credit);
}
function trb_onboarding_deferral_installment_meta($order, $credit) {
    $order->update_meta_data('_trb_onboarding_purpose', 'activation_installment');
    if ($credit) $order->update_meta_data('_trb_onboarding_deferral_credit', $credit);
}
function trb_onboarding_deferral_installment_matches($order, $credit) {
    if (($order->get_meta('_trb_onboarding_purpose') ?: 'activation_installment') !== 'activation_installment'
        || ($order->get_meta('_trb_onboarding_deferral_credit') ?: null) !== $credit) throw new RuntimeException('Storico credito della rata non coerente.');
}
function trb_onboarding_deferral_installment_result($result, $order, $p, $statusLookup = false) {
    $purpose = $order->get_meta('_trb_onboarding_purpose') ?: 'activation_installment';
    if ($purpose !== 'activation_installment') return trb_onboarding_deferral_error('Questo ordine non è una rata contrattuale.');
    $credit = $order->get_meta('_trb_onboarding_deferral_credit');
    $result['purpose'] = 'activation_installment'; $result['installment_total_cents'] = (int)$order->get_meta('_trb_onboarding_installment_total_cents');
    $result['credit_cents'] = 0;
    if (!$credit) {
        if (array_key_exists('credit_cents', $p) || array_key_exists('deferral_id', $p)) return trb_onboarding_deferral_error('Ordine senza credito di proroga.');
        return $result;
    }
    foreach (array('practice_id' => '_trb_onboarding_practice_id', 'snapshot_sha256' => '_trb_onboarding_snapshot_sha256', 'number' => '_trb_onboarding_number', 'installment_total_cents' => '_trb_onboarding_installment_total_cents') as $key => $meta) {
        $actual = in_array($key, array('number', 'installment_total_cents'), true) ? (int)$order->get_meta($meta) : $order->get_meta($meta);
        if (!array_key_exists($key, $credit) || $actual !== $credit[$key]) return trb_onboarding_deferral_error('Ordine non coerente con il credito memorizzato.');
    }
    if (strtolower($order->get_billing_email()) !== $credit['email'] || $order->get_currency() !== 'EUR') return trb_onboarding_deferral_error('Intestatario del credito non coerente.');
    foreach (array('purpose', 'credit_cents', 'deferral_id', 'proposal_sha256', 'policy_version', 'practice_id', 'snapshot_sha256', 'email', 'number', 'installment_total_cents') as $key) {
        if ($statusLookup && !array_key_exists($key, $p)) continue;
        $input = $key === 'email' ? strtolower((string)($p[$key] ?? '')) : ($p[$key] ?? null);
        if (!array_key_exists($key, $credit) || $input !== $credit[$key]) return trb_onboarding_deferral_error('Richiesta non coerente con il credito della rata.');
    }
    $claim = get_option('trb_onboarding_deferral_credit_'.$credit['deferral_id'], null);
    if ($claim !== $credit) return trb_onboarding_deferral_error('Registro credito della rata non coerente.');
    $result = array_merge($result, array_intersect_key($credit, array_flip(array('credit_cents', 'deferral_id', 'proposal_sha256', 'policy_version'))));
    try {
        trb_onboarding_deferral_credit_context($credit, $credit['number'], false);
        $deposit = wc_get_order($credit['deposit_order_id']); $depositDate = $deposit ? $deposit->get_date_paid() : null;
        if (!$depositDate) throw new RuntimeException('Data del deposito da verificare.');
        $paidOn = wp_date('Y-m-d', $depositDate->getTimestamp(), new DateTimeZone('Europe/Rome')); $expires = trb_onboarding_deferral_expires($paidOn);
        $capture = trb_onboarding_store_capture($order); $netDate = $capture['date'] ? wp_date('Y-m-d', $capture['date']->getTimestamp(), new DateTimeZone('Europe/Rome')) : null;
        $anchored = trb_onboarding_deferral_first_net_captured($credit, $paidOn, $expires);
        if (($netDate && ($netDate < $paidOn || $netDate > trb_onboarding_deferral_today())) || (!$anchored && (($capture['captured'] && (!$netDate || $netDate > $expires)) || trb_onboarding_deferral_today() > $expires))) throw new RuntimeException('Primo incasso fuori dal periodo di proroga.');
        $result['credit_status'] = 'confirmed';
    }
    catch (Throwable $e) { $result['credit_status'] = 'review'; if (isset($result['status']) && $result['status'] === 'confirmed') $result['status'] = 'review'; $result['paid'] = false; $result['checkout_url'] = ''; }
    return $result;
}

add_action('rest_api_init', static function () {
    register_rest_route('trb/v1', '/onboarding/deferral-order', array('methods' => 'POST', 'permission_callback' => 'trb_onboarding_store_permission', 'callback' => 'trb_onboarding_deferral_order'));
    register_rest_route('trb/v1', '/onboarding/deferral-status', array('methods' => 'POST', 'permission_callback' => 'trb_onboarding_store_permission', 'callback' => 'trb_onboarding_deferral_status'));
});
function trb_onboarding_store_protocol_order($order) {
    if (!$order) return false;
    foreach (array('_trb_onboarding_practice_id', '_trb_onboarding_purpose', '_trb_onboarding_attempt_token', '_trb_onboarding_deferral_binding', '_trb_onboarding_deferral_credit', '_trb_onboarding_snapshot_sha256', '_trb_onboarding_amount_cents', '_trb_onboarding_number') as $key) {
        $value = $order->get_meta($key);
        if ($value !== '' && $value !== null) return true;
    }
    return method_exists($order, 'get_created_via') && in_array($order->get_created_via(), array('trb-contract-onboarding', 'trb-activation-deferral'), true);
}
/** Validate the final order-pay boundary, including URLs issued before a later order edit. */
function trb_onboarding_store_checkout_integrity($order) {
    $amount = (int)$order->get_meta('_trb_onboarding_amount_cents');
    if ($order->get_currency() !== 'EUR' || $amount < 1 || (int)round((float)$order->get_total() * 100) !== $amount) throw new RuntimeException('Importo del checkout non coerente con la quota.');
    $purpose = $order->get_meta('_trb_onboarding_purpose') ?: 'activation_installment';
    if ($purpose === 'activation_deferral') {
        $binding = $order->get_meta('_trb_onboarding_deferral_binding');
        if (!is_array($binding)) throw new RuntimeException('Binding del deposito non disponibile.');
        trb_onboarding_deferral_order_matches($order, $binding);
        if (!trb_onboarding_deferral_find($binding, $order->get_id())) throw new RuntimeException('Deposito non registrato.');
        return;
    }
    $practice = (string)$order->get_meta('_trb_onboarding_practice_id'); $number = (int)$order->get_meta('_trb_onboarding_number');
    if ($purpose !== 'activation_installment' || !preg_match('/^[a-f0-9]{32}$/D', $practice) || $number < 1
        || (int)$order->get_meta('_trb_onboarding_installment_total_cents') < $amount
        || !preg_match('/^[a-f0-9]{64}$/D', (string)$order->get_meta('_trb_onboarding_snapshot_sha256')) || !is_email($order->get_billing_email())) throw new RuntimeException('Rata del checkout non coerente.');
    $key = 'trb_onboarding_order_'.$practice.'_'.$number;
    if (!in_array($order->get_id(), array_map('intval', (array)get_option($key, array())), true)) throw new RuntimeException('Ordine del checkout non indicizzato.');
    $attempt = get_option($key.'_attempt', null);
    if (is_array($attempt) && ($attempt['order_id'] ?? null) === $order->get_id()) {
        trb_onboarding_store_attempt_check($key, $attempt, $practice, $number);
        if ($attempt['state'] !== 'complete') throw new RuntimeException('Checkout della rata ancora da verificare.');
        trb_onboarding_store_attempt_order($attempt);
    }
    if ($order->get_meta('_trb_onboarding_deferral_credit')) {
        $proof = trb_onboarding_deferral_installment_result(array(), $order, array('practice_id' => $practice), true);
        if (is_wp_error($proof) || ($proof['credit_status'] ?? '') !== 'confirmed') throw new RuntimeException('Credito del checkout da verificare.');
    }
}
// Recheck financial bindings and credit immediately before exposing protocol checkout gateways.
add_filter('woocommerce_available_payment_gateways', static function ($gateways) {
    $id = absint(get_query_var('order-pay')); $order = $id ? wc_get_order($id) : false; $credit = $order ? $order->get_meta('_trb_onboarding_deferral_credit') : null;
    if (!trb_onboarding_store_protocol_order($order)) return $gateways;
    try { trb_onboarding_store_checkout_integrity($order); if ($credit) trb_onboarding_deferral_credit_context($credit, (int)$credit['number']); return $gateways; } catch (Throwable $e) { return array(); }
}, 110);
