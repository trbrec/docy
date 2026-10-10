<?php
require __DIR__ . '/integration-syntax-preflight.php';
$before = glob( sys_get_temp_dir() . '/trb-integration-lint-*' );
trb_integration_syntax_preflight( array( 'candidate.php' => '<?php echo "synthetic";' ) );
$failed = false;
try { trb_integration_syntax_preflight( array( 'invalid.php' => '<?php function {' ) ); }
catch ( RuntimeException $expected ) { $failed = true; }
if ( ! $failed || glob( sys_get_temp_dir() . '/trb-integration-lint-*' ) !== $before ) throw new RuntimeException( 'Syntax failure accepted or disposable files leaked.' );
echo "Valid and invalid integration source checked without retaining temporary files.\n";
