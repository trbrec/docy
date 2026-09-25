<?php
/** Symphonic role catalogue observed in the track editor on 2026-09-25.
 * No migration, scheduled action or write to existing releases belongs here.
 */
function trb_symphonic_roles() {
	static $roles;
	if ( null === $roles ) {
		$data = json_decode( file_get_contents( __DIR__ . '/../assets/data/symphonic-credit-roles.json' ), true );
		$roles = array();
		foreach ( array( 'writers', 'performers', 'engineering' ) as $group ) {
			$roles[$group] = array();
			foreach ( $data[$group] ?? array() as $role ) $roles[$group][$role['name']] = $role['id'];
		}
	}
	return $roles;
}

function trb_symphonic_release( $id ) {
	return 'symphonic-2026-09' === get_post_meta( $id, '_trb_release_credit_schema', true );
}

/** Validate raw input, so unknown/mis-categorised roles cannot silently disappear. */
function trb_symphonic_credit_errors( $tracks ) {
	$errors = array(); $catalogue = trb_symphonic_roles();
	foreach ( (array) $tracks as $index => $track ) {
		$prefix = 'Brano ' . ($index + 1) . ': ';
		$seen = array();
		foreach ( $catalogue as $group => $allowed ) {
			$seen[$group] = array();
			$rows = $track['credits'][$group] ?? array();
			if ( ! is_array($rows) || ! $rows ) { $errors[] = $prefix . 'compila la categoria ' . $group . '.'; continue; }
			foreach ( $rows as $row ) {
				if (!is_array($row)) { $errors[]=$prefix.'credito non valido.'; continue; }
				$name = is_scalar($row['name'] ?? null) ? trim((string)$row['name']) : '';
				$roles = 'writers' === $group ? ($row['roles'] ?? array()) : array($row['role'] ?? '');
				if ( '' === $name || !is_array($roles) || !$roles ) { $errors[]=$prefix.'inserisci nome e ruolo in ogni riga dei crediti.'; continue; }
				foreach ($roles as $role) {
					if (!is_string($role) || !isset($allowed[$role])) $errors[]=$prefix.'seleziona un ruolo previsto nella categoria corretta.';
					else $seen[$group][]=$role;
				}
			}
		}
		if (!in_array('Producer',$seen['engineering'],true)) $errors[]=$prefix.'indica almeno un Producer nella sezione Produzione e tecnica.';
		if ('no_lyrics' !== ($track['advisory'] ?? '')) {
			if (!in_array('Lyricist',$seen['writers'],true)) $errors[]=$prefix.'indica chi ha scritto il testo con il ruolo Lyricist.';
			if (!in_array('Vocals',$seen['performers'],true)) $errors[]=$prefix.'indica chi canta con il ruolo Vocals nella sezione Interpreti e musicisti.';
		}
	}
	return array_values(array_unique($errors));
}

function trb_symphonic_render_credits() {
	$catalogue=trb_symphonic_roles();
	$labels=array('writers'=>'Autori e compositori','performers'=>'Interpreti e musicisti','engineering'=>'Produzione e tecnica');
	$help=array(
		'writers'=>'Indica nome e cognome reali di chi ha scritto il testo (Lyricist) e composto la musica (Composer). Lyricist è obbligatorio per i brani con testo. Se una persona ha più ruoli, selezionali tutti. Le quote sono ripartite in parti uguali come nel modulo precedente.',
		'performers'=>'Indica chi ha eseguito il brano e i rispettivi strumenti. Per un brano cantato è obbligatorio indicare chi canta con il ruolo Vocals, anche se è già presente fra gli autori. Per attribuire più ruoli alla stessa persona aggiungi una riga per ciascun ruolo.',
		'engineering'=>'Indica chi ha prodotto e curato la registrazione. È sempre obbligatorio almeno un Producer. Aggiungi gli altri ruoli effettivamente svolti, per esempio Mixing Engineer e Mastering Engineer.');
	foreach ($labels as $group=>$label) : ?>
	<div class="trb-contributor-group" data-contributor-group="<?php echo esc_attr($group); ?>" data-symphonic-group>
		<h4><?php echo esc_html($label); ?> <span>*</span></h4><p><?php echo esc_html($help[$group]); ?></p>
		<div data-contributor-rows><div class="trb-contributor-row<?php echo 'writers'===$group?' trb-contributor-row--writer':''; ?>">
			<input type="text" name="trb_tracks[__INDEX__][credits][<?php echo esc_attr($group); ?>][0][name]" required aria-label="Nome completo" placeholder="Nome completo" />
			<?php if ('writers'===$group) : ?>
			<details class="trb-symphonic-writers"><summary>Seleziona i ruoli</summary><input type="search" data-role-filter aria-label="Cerca ruolo autore" placeholder="Cerca ruolo in inglese" /><fieldset class="trb-writer-roles"><legend>Ruoli</legend>
			<?php foreach ($catalogue[$group] as $role=>$id) : ?><label><input type="checkbox" name="trb_tracks[__INDEX__][credits][writers][0][roles][]" value="<?php echo esc_attr($role); ?>" /> <?php echo esc_html($role); ?></label><?php endforeach; ?>
			</fieldset></details><label class="trb-writer-share">Quota diritto d’autore<input type="text" name="trb_tracks[__INDEX__][credits][writers][0][share]" value="100,00%" readonly data-writer-share /></label>
			<?php else : ?>
			<label>Ruolo<input type="search" data-role-filter aria-label="Cerca ruolo" placeholder="Cerca ruolo in inglese" /><select name="trb_tracks[__INDEX__][credits][<?php echo esc_attr($group); ?>][0][role]" required aria-label="Ruolo"><option value="">Seleziona un ruolo</option><?php foreach ($catalogue[$group] as $role=>$id) : ?><option value="<?php echo esc_attr($role); ?>"><?php echo esc_html($role); ?></option><?php endforeach; ?></select></label>
			<?php endif; ?>
			<button type="button" data-remove-contributor hidden>Rimuovi</button>
		</div></div><p data-credit-error role="status"></p><button type="button" class="trb-add-contributor" data-add-contributor>+ Aggiungi partecipante</button>
	</div>
	<?php endforeach;
}
