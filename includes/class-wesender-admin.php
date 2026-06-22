<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wesender_Admin {

	public function __construct() {
		add_action( 'admin_menu',    [ $this, 'add_menu' ] );
		add_action( 'admin_init',    [ $this, 'handle_callback' ] );
		add_action( 'admin_init',    [ $this, 'handle_connect_redirect' ] );
		add_action( 'admin_init',    [ $this, 'handle_disconnect' ] );
		add_action( 'admin_init',    [ $this, 'handle_settings_save' ] );
		add_action( 'admin_init',    [ $this, 'handle_test_email' ] );
		add_action( 'admin_init',    [ $this, 'handle_log_clear' ] );
		add_action( 'admin_init',    [ $this, 'handle_block_toggle' ] );
		add_action( 'admin_notices', [ $this, 'show_notices' ] );
	}

	// ── Menu ────────────────────────────────────────────────────────────────

	public function add_menu(): void {
		$icon = 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><text x="10" y="15" text-anchor="middle" font-size="13" font-weight="900" fill="#a7aaad" font-family="-apple-system,BlinkMacSystemFont,Arial,sans-serif" letter-spacing="-1">We</text></svg>'
		);

		add_menu_page(
			'Wesender',
			'Wesender',
			'manage_options',
			'wesender',
			[ $this, 'render_verbinding' ],
			$icon,
			99
		);

		add_submenu_page(
			'wesender',
			'Verbinding - Wesender',
			'Verbinding',
			'manage_options',
			'wesender',
			[ $this, 'render_verbinding' ]
		);

		add_submenu_page(
			'wesender',
			'Maillog - Wesender',
			'Maillog',
			'manage_options',
			'wesender-maillog',
			[ $this, 'render_maillog' ]
		);

		add_submenu_page(
			'wesender',
			'Plugins blokkeren - Wesender',
			'Plugins blokkeren',
			'manage_options',
			'wesender-blokkeren',
			[ $this, 'render_blokkeren' ]
		);
	}

	// ── Connect flow ────────────────────────────────────────────────────────

	public function handle_connect_redirect(): void {
		if (
			! isset( $_GET['wesender_action'] ) ||
			'connect' !== $_GET['wesender_action'] ||
			! current_user_can( 'manage_options' )
		) {
			return;
		}

		if ( ! check_admin_referer( 'wesender_connect' ) ) {
			wp_die( esc_html__( 'Beveiligingscontrole mislukt.', 'wesender-wp' ) );
		}

		$state = wp_generate_password( 32, false );
		set_transient( 'wesender_state_' . $state, 1, 600 );

		$callback    = admin_url( 'admin.php?page=wesender' );
		$connect_url = WESENDER_APP_URL . '/plugin/wordpress?' . http_build_query( [
			'callback' => $callback,
			'state'    => $state,
			'site'     => get_bloginfo( 'name' ),
		] );

		wp_redirect( $connect_url );
		exit;
	}

	public function handle_callback(): void {
		if (
			! isset( $_GET['wesender_token'], $_GET['wesender_state'] ) ||
			! current_user_can( 'manage_options' )
		) {
			return;
		}

		$state = sanitize_text_field( wp_unslash( $_GET['wesender_state'] ) );

		if ( ! get_transient( 'wesender_state_' . $state ) ) {
			$this->set_notice( 'error', 'Ongeldige verbindingssessie. Probeer opnieuw.' );
			wp_redirect( admin_url( 'admin.php?page=wesender' ) );
			exit;
		}

		delete_transient( 'wesender_state_' . $state );

		$token = sanitize_text_field( wp_unslash( $_GET['wesender_token'] ) );
		update_option( 'wesender_api_key', $token, false );

		if ( ! get_option( 'wesender_from_email' ) ) {
			update_option( 'wesender_from_email', get_option( 'admin_email' ), false );
		}

		$this->set_notice( 'success', 'Verbonden met Wesender. Je kunt nu e-mails versturen via je account.' );
		wp_redirect( admin_url( 'admin.php?page=wesender' ) );
		exit;
	}

	// ── Disconnect ──────────────────────────────────────────────────────────

	public function handle_disconnect(): void {
		if (
			! isset( $_POST['wesender_action'] ) ||
			'disconnect' !== $_POST['wesender_action'] ||
			! current_user_can( 'manage_options' )
		) {
			return;
		}

		if ( ! check_admin_referer( 'wesender_disconnect' ) ) {
			wp_die( esc_html__( 'Beveiligingscontrole mislukt.', 'wesender-wp' ) );
		}

		delete_option( 'wesender_api_key' );
		delete_option( 'wesender_from_email' );
		delete_option( 'wesender_from_name' );

		$this->set_notice( 'info', 'Wesender-koppeling verbroken.' );
		wp_redirect( admin_url( 'admin.php?page=wesender' ) );
		exit;
	}

	// ── Settings save ───────────────────────────────────────────────────────

	public function handle_settings_save(): void {
		if (
			! isset( $_POST['wesender_action'] ) ||
			'save_settings' !== $_POST['wesender_action'] ||
			! current_user_can( 'manage_options' )
		) {
			return;
		}

		if ( ! check_admin_referer( 'wesender_save_settings' ) ) {
			wp_die( esc_html__( 'Beveiligingscontrole mislukt.', 'wesender-wp' ) );
		}

		$from_email = sanitize_email( wp_unslash( $_POST['wesender_from_email'] ?? '' ) );
		$from_name  = sanitize_text_field( wp_unslash( $_POST['wesender_from_name'] ?? '' ) );

		if ( ! $from_email ) {
			$this->set_notice( 'error', 'Voer een geldig e-mailadres in als afzender.' );
			wp_redirect( admin_url( 'admin.php?page=wesender' ) );
			exit;
		}

		update_option( 'wesender_from_email', $from_email, false );
		update_option( 'wesender_from_name',  $from_name,  false );

		$this->set_notice( 'success', 'Instellingen opgeslagen.' );
		wp_redirect( admin_url( 'admin.php?page=wesender' ) );
		exit;
	}

	// ── Test email ──────────────────────────────────────────────────────────

	public function handle_test_email(): void {
		if (
			! isset( $_POST['wesender_action'] ) ||
			'test_email' !== $_POST['wesender_action'] ||
			! current_user_can( 'manage_options' )
		) {
			return;
		}

		if ( ! check_admin_referer( 'wesender_test_email' ) ) {
			wp_die( esc_html__( 'Beveiligingscontrole mislukt.', 'wesender-wp' ) );
		}

		$to   = sanitize_email( wp_unslash( $_POST['wesender_test_to'] ?? get_option( 'admin_email' ) ) );
		$sent = wp_mail(
			$to,
			'Wesender testbericht',
			'<p>Dit testbericht is verstuurd via de Wesender WordPress-plugin. Als je dit ontvangt, werkt alles correct.</p>',
			[ 'Content-Type: text/html; charset=UTF-8' ]
		);

		$this->set_notice(
			$sent ? 'success' : 'error',
			$sent
				? "Test-e-mail verstuurd naar {$to}."
				: 'Versturen mislukt. Controleer het PHP-errorlog voor details.'
		);

		wp_redirect( admin_url( 'admin.php?page=wesender' ) );
		exit;
	}

	// ── Log clear ───────────────────────────────────────────────────────────

	public function handle_log_clear(): void {
		if (
			! isset( $_POST['wesender_action'] ) ||
			'clear_log' !== $_POST['wesender_action'] ||
			! current_user_can( 'manage_options' )
		) {
			return;
		}

		if ( ! check_admin_referer( 'wesender_clear_log' ) ) {
			wp_die( esc_html__( 'Beveiligingscontrole mislukt.', 'wesender-wp' ) );
		}

		Wesender_Log::clear();
		$this->set_notice( 'success', 'Maillog geleegd.' );
		wp_redirect( admin_url( 'admin.php?page=wesender-maillog' ) );
		exit;
	}

	// ── Block toggle ────────────────────────────────────────────────────────

	public function handle_block_toggle(): void {
		if (
			! isset( $_POST['wesender_action'] ) ||
			'block_toggle' !== $_POST['wesender_action'] ||
			! current_user_can( 'manage_options' )
		) {
			return;
		}

		if ( ! check_admin_referer( 'wesender_block_toggle' ) ) {
			wp_die( esc_html__( 'Beveiligingscontrole mislukt.', 'wesender-wp' ) );
		}

		$source  = sanitize_text_field( wp_unslash( $_POST['wesender_source'] ?? '' ) );
		$action  = sanitize_text_field( wp_unslash( $_POST['wesender_block_action'] ?? '' ) );
		$blocked = (array) get_option( 'wesender_blocked_sources', [] );

		if ( 'block' === $action && ! in_array( $source, $blocked, true ) ) {
			$blocked[] = $source;
			$this->set_notice( 'success', 'Bron geblokkeerd: ' . $source );
		} elseif ( 'unblock' === $action ) {
			$blocked = array_values( array_diff( $blocked, [ $source ] ) );
			$this->set_notice( 'success', 'Blokkering opgeheven: ' . $source );
		}

		update_option( 'wesender_blocked_sources', $blocked, false );
		wp_redirect( admin_url( 'admin.php?page=wesender-blokkeren' ) );
		exit;
	}

	// ── Notices ─────────────────────────────────────────────────────────────

	private function set_notice( string $type, string $message ): void {
		set_transient( 'wesender_notice', [ 'type' => $type, 'message' => $message ], 60 );
	}

	public function show_notices(): void {
		$notice = get_transient( 'wesender_notice' );
		if ( ! $notice || ! is_array( $notice ) ) {
			return;
		}
		delete_transient( 'wesender_notice' );

		if ( 'success' === $notice['type'] ) {
			$class = 'notice-success';
		} elseif ( 'error' === $notice['type'] ) {
			$class = 'notice-error';
		} else {
			$class = 'notice-info';
		}

		printf(
			'<div class="notice %s is-dismissible"><p>%s</p></div>',
			esc_attr( $class ),
			esc_html( $notice['message'] )
		);
	}

	// ── Shared layout helpers ────────────────────────────────────────────────

	private function render_css(): void {
		?>
		<style>
		#wesender-app *,
		#wesender-app *::before,
		#wesender-app *::after { box-sizing: border-box; }

		#wesender-app {
			font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', sans-serif;
			color: #111827;
		}
		#wesender-app .ws-header {
			display: flex;
			align-items: center;
			gap: 12px;
			margin: 16px 0 0;
			padding-bottom: 0;
		}
		#wesender-app .ws-logo-mark {
			width: 38px; height: 38px;
			background: #111827;
			border-radius: 11px;
			display: flex; align-items: center; justify-content: center;
			flex-shrink: 0;
			font-size: 17px; font-weight: 900; color: #fff;
			letter-spacing: -0.06em;
			font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', sans-serif;
			user-select: none;
		}
		#wesender-app .ws-logo-mark svg { display: block; }
		#wesender-app .ws-logo-name {
			font-size: 18px; font-weight: 700;
			color: #111827; letter-spacing: -0.02em;
		}
		#wesender-app .ws-badge-on {
			display: inline-flex; align-items: center; gap: 5px;
			font-size: 11px; font-weight: 600; color: #059669;
			background: #d1fae5; border-radius: 999px; padding: 2px 9px;
		}
		#wesender-app .ws-badge-on::before {
			content: ''; width: 6px; height: 6px;
			background: #10b981; border-radius: 50%;
		}
		#wesender-app .ws-badge-off {
			display: inline-flex; align-items: center; gap: 5px;
			font-size: 11px; font-weight: 600; color: #9ca3af;
			background: #f3f4f6; border-radius: 999px; padding: 2px 9px;
		}
		#wesender-app .ws-badge-off::before {
			content: ''; width: 6px; height: 6px;
			background: #d1d5db; border-radius: 50%;
		}
		#wesender-app .ws-nav {
			display: inline-flex;
			gap: 2px;
			margin: 20px 0 0;
			padding: 4px;
			background: #f3f4f6;
			border-radius: 10px;
		}
		#wesender-app .ws-nav a {
			padding: 6px 14px;
			font-size: 13px; font-weight: 500;
			color: #6b7280;
			text-decoration: none;
			border-radius: 7px;
			transition: all 0.15s;
			white-space: nowrap;
		}
		#wesender-app .ws-nav a:hover { color: #111827; background: rgba(0,0,0,.04); text-decoration: none; }
		#wesender-app .ws-nav a.active {
			color: #111827;
			background: #fff;
			font-weight: 600;
			box-shadow: 0 1px 3px rgba(0,0,0,.1), 0 0 0 1px rgba(0,0,0,.04);
		}
		#wesender-app .ws-card {
			background: #fff;
			border: 1px solid #e5e7eb;
			border-radius: 12px;
			padding: 28px 32px;
			margin-top: 20px;
			box-shadow: 0 1px 4px rgba(0,0,0,.06);
		}
		#wesender-app .ws-section-label {
			font-size: 11px; font-weight: 700; letter-spacing: 0.07em;
			text-transform: uppercase; color: #9ca3af;
			margin: 0 0 14px;
		}
		#wesender-app .ws-heading {
			font-size: 20px; font-weight: 700; color: #111827;
			letter-spacing: -0.02em; margin: 0 0 6px;
		}
		#wesender-app .ws-subtext {
			font-size: 14px; color: #6b7280; line-height: 1.6; margin: 0 0 24px;
		}
		#wesender-app .ws-btn {
			display: inline-flex; align-items: center; justify-content: center;
			height: 38px; padding: 0 18px;
			font-size: 14px; font-weight: 600; border-radius: 8px;
			text-decoration: none; cursor: pointer; border: none;
			transition: opacity 0.15s; line-height: 1;
		}
		#wesender-app .ws-btn:hover { opacity: 0.82; text-decoration: none; }
		#wesender-app .ws-btn:focus { outline: 2px solid #10b981; outline-offset: 2px; }
		#wesender-app .ws-btn-primary   { background: #111827; color: #fff !important; }
		#wesender-app .ws-btn-secondary { background: #fff; color: #374151 !important; border: 1px solid #d1d5db; }
		#wesender-app .ws-btn-danger    { background: #fff; color: #dc2626 !important; border: 1px solid #fca5a5; }
		#wesender-app .ws-btn-sm        { height: 30px; padding: 0 12px; font-size: 12px; border-radius: 6px; }
		#wesender-app .ws-field label {
			display: block; font-size: 13px; font-weight: 600;
			color: #374151; margin-bottom: 5px;
		}
		#wesender-app .ws-field input[type="text"],
		#wesender-app .ws-field input[type="email"] {
			width: 100%;
			height: 38px; padding: 0 12px;
			border: 1px solid #d1d5db; border-radius: 8px;
			font-size: 14px; color: #111827;
			outline: none; transition: border-color 0.15s;
			background: #fff;
		}
		#wesender-app .ws-field input:focus {
			border-color: #10b981;
			box-shadow: 0 0 0 3px rgba(16,185,129,.12);
		}
		#wesender-app .ws-field-row {
			display: grid; gap: 14px;
			grid-template-columns: 1fr 1fr;
			margin-bottom: 16px;
		}
		@media (max-width: 600px) {
			#wesender-app .ws-field-row { grid-template-columns: 1fr; }
		}
		#wesender-app .ws-separator { height: 1px; background: #f3f4f6; margin: 24px 0; }
		#wesender-app .ws-kv {
			display: flex; justify-content: space-between; align-items: center;
			padding: 11px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px;
		}
		#wesender-app .ws-kv:last-child { border-bottom: none; }
		#wesender-app .ws-kv-label { color: #6b7280; }
		#wesender-app .ws-kv-value { color: #111827; font-weight: 500; font-family: monospace; font-size: 13px; }
		#wesender-app .ws-test-row { display: flex; gap: 10px; align-items: flex-end; }
		#wesender-app .ws-test-row .ws-field { flex: 1; }
		#wesender-app .ws-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; }
		#wesender-app .ws-footer {
			margin-top: 14px; font-size: 12px; color: #9ca3af;
			display: flex; gap: 16px; flex-wrap: wrap;
		}
		#wesender-app .ws-footer a { color: #6b7280; text-decoration: none; }
		#wesender-app .ws-footer a:hover { color: #111827; }

		/* Log table */
		#wesender-app .ws-table {
			width: 100%; border-collapse: collapse; font-size: 13px;
		}
		#wesender-app .ws-table th {
			text-align: left; padding: 10px 12px;
			border-bottom: 1px solid #e5e7eb;
			font-size: 11px; font-weight: 700; letter-spacing: 0.06em;
			text-transform: uppercase; color: #9ca3af;
		}
		#wesender-app .ws-table td {
			padding: 11px 12px;
			border-bottom: 1px solid #f3f4f6;
			color: #374151; vertical-align: top;
		}
		#wesender-app .ws-table tr:last-child td { border-bottom: none; }
		#wesender-app .ws-table tr:hover td { background: #fafafa; }
		#wesender-app .ws-table .col-time { white-space: nowrap; color: #6b7280; font-size: 12px; }
		#wesender-app .ws-table .col-to   { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		#wesender-app .ws-table .col-subj { max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		#wesender-app .ws-badge-sent {
			display: inline-flex; align-items: center; gap: 4px;
			font-size: 11px; font-weight: 600; padding: 2px 8px;
			border-radius: 999px; color: #059669; background: #d1fae5;
		}
		#wesender-app .ws-badge-failed {
			display: inline-flex; align-items: center; gap: 4px;
			font-size: 11px; font-weight: 600; padding: 2px 8px;
			border-radius: 999px; color: #dc2626; background: #fee2e2;
		}
		#wesender-app .ws-badge-blocked {
			display: inline-flex; align-items: center; gap: 4px;
			font-size: 11px; font-weight: 600; padding: 2px 8px;
			border-radius: 999px; color: #6b7280; background: #f3f4f6;
		}
		#wesender-app .ws-empty {
			text-align: center; padding: 40px 20px;
			color: #9ca3af; font-size: 14px;
		}
		#wesender-app .ws-pagination {
			display: flex; justify-content: space-between; align-items: center;
			margin-top: 16px; font-size: 13px; color: #6b7280;
		}
		#wesender-app .ws-pagination a {
			display: inline-flex; align-items: center; justify-content: center;
			width: 32px; height: 32px; border-radius: 6px;
			border: 1px solid #e5e7eb; text-decoration: none;
			color: #374151; font-weight: 500; margin: 0 2px;
		}
		#wesender-app .ws-pagination a:hover { border-color: #111827; color: #111827; }
		#wesender-app .ws-pagination .current-page {
			display: inline-flex; align-items: center; justify-content: center;
			width: 32px; height: 32px; border-radius: 6px;
			background: #111827; color: #fff; font-weight: 600;
			font-size: 13px; margin: 0 2px;
		}

		/* Blocklist */
		#wesender-app .ws-source-row {
			display: flex; align-items: center; justify-content: space-between;
			padding: 12px 0; border-bottom: 1px solid #f3f4f6;
		}
		#wesender-app .ws-source-row:last-child { border-bottom: none; }
		#wesender-app .ws-source-name {
			font-size: 14px; color: #111827; font-weight: 500;
		}
		#wesender-app .ws-source-slug {
			font-size: 12px; color: #9ca3af; font-family: monospace;
		}
		#wesender-app .ws-badge-blocked-pill {
			display: inline-flex; align-items: center; gap: 4px;
			font-size: 11px; font-weight: 600; padding: 2px 8px;
			border-radius: 999px; color: #dc2626; background: #fee2e2;
			margin-right: 8px;
		}
		</style>
		<?php
	}

	private function render_header( string $active_page ): void {
		$connected = ! empty( get_option( 'wesender_api_key' ) );
		$pages     = [
			'wesender'           => 'Verbinding',
			'wesender-maillog'   => 'Maillog',
			'wesender-blokkeren' => 'Plugins blokkeren',
		];
		?>
		<div class="ws-header">
			<div class="ws-logo-mark">We</div>
			<span class="ws-logo-name">Wesender</span>
			<?php if ( $connected ) : ?>
				<span class="ws-badge-on">Actief</span>
			<?php else : ?>
				<span class="ws-badge-off">Niet verbonden</span>
			<?php endif; ?>
		</div>

		<nav class="ws-nav">
			<?php foreach ( $pages as $slug => $label ) : ?>
				<a
					href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>"
					class="<?php echo $active_page === $slug ? 'active' : ''; ?>"
				><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	// ── Render: Verbinding ───────────────────────────────────────────────────

	public function render_verbinding(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$api_key    = get_option( 'wesender_api_key' );
		$connected  = ! empty( $api_key );
		$from_email = get_option( 'wesender_from_email', '' );
		$from_name  = get_option( 'wesender_from_name',  get_bloginfo( 'name' ) );
		$masked_key = $connected ? substr( $api_key, 0, 14 ) . '...' : '';
		?>
		<div class="wrap" style="max-width:740px">
		<div id="wesender-app">
		<?php $this->render_css(); ?>
		<?php $this->render_header( 'wesender' ); ?>

		<div class="ws-card">
		<?php if ( $connected ) : ?>

			<p class="ws-section-label">Verbinding</p>
			<div>
				<div class="ws-kv">
					<span class="ws-kv-label">API-sleutel</span>
					<span class="ws-kv-value"><?php echo esc_html( $masked_key ); ?></span>
				</div>
			</div>

			<div class="ws-separator"></div>

			<p class="ws-section-label">Afzender</p>
			<form method="post">
				<?php wp_nonce_field( 'wesender_save_settings' ); ?>
				<input type="hidden" name="wesender_action" value="save_settings">
				<div class="ws-field-row">
					<div class="ws-field">
						<label for="ws_from_email">E-mailadres</label>
						<input
							type="email"
							id="ws_from_email"
							name="wesender_from_email"
							value="<?php echo esc_attr( $from_email ); ?>"
							placeholder="noreply@jouwdomein.nl"
							required
						>
					</div>
					<div class="ws-field">
						<label for="ws_from_name">Naam (optioneel)</label>
						<input
							type="text"
							id="ws_from_name"
							name="wesender_from_name"
							value="<?php echo esc_attr( $from_name ); ?>"
							placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
						>
					</div>
				</div>
				<p style="font-size:12px;color:#6b7280;margin:-4px 0 16px">
					Het afzenderdomein
					<strong><?php echo esc_html( $from_email ? strstr( $from_email, '@' ) : '@jouwdomein.nl' ); ?></strong>
					moet geverifieerd zijn in je Wesender-account.
					<a href="<?php echo esc_url( WESENDER_APP_URL . '/domeinen' ); ?>" target="_blank" rel="noopener">Domeinen beheren</a>
				</p>
				<button type="submit" class="ws-btn ws-btn-primary">Opslaan</button>
			</form>

			<div class="ws-separator"></div>

			<p class="ws-section-label">Test-e-mail</p>
			<form method="post">
				<?php wp_nonce_field( 'wesender_test_email' ); ?>
				<input type="hidden" name="wesender_action" value="test_email">
				<div class="ws-test-row">
					<div class="ws-field">
						<label for="ws_test_to">Ontvanger</label>
						<input
							type="email"
							id="ws_test_to"
							name="wesender_test_to"
							value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"
							required
						>
					</div>
					<button type="submit" class="ws-btn ws-btn-secondary">Versturen</button>
				</div>
			</form>

			<div class="ws-separator"></div>

			<form
				method="post"
				onsubmit="return confirm('Wil je de Wesender-koppeling verbreken? E-mails worden daarna niet meer verstuurd via Wesender.')"
			>
				<?php wp_nonce_field( 'wesender_disconnect' ); ?>
				<input type="hidden" name="wesender_action" value="disconnect">
				<button type="submit" class="ws-btn ws-btn-danger">Koppeling verbreken</button>
			</form>

		<?php else : ?>

			<h2 class="ws-heading">Verbind WordPress met Wesender</h2>
			<p class="ws-subtext">
				Stuur alle WordPress e-mails via jouw Wesender-account. Contactformulieren, wachtwoordresets,
				WooCommerce-bestellingen - alles loopt automatisch via Wesender zodra je verbonden bent.
				Geen SMTP-instellingen nodig.
			</p>

			<ul style="list-style:none;padding:0;margin:0 0 24px;display:flex;flex-direction:column;gap:10px">
				<?php foreach ( [
					'Verbinding in twee klikken - geen API-sleutel kopieren',
					'Alle e-mails via je eigen, geverifieerde domein',
					'Maillog en blokkeerlijst direct in WordPress',
				] as $item ) : ?>
					<li style="display:flex;align-items:flex-start;gap:10px;font-size:14px;color:#374151">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:2px">
							<polyline points="20 6 9 17 4 12"/>
						</svg>
						<?php echo esc_html( $item ); ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<a
				href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wesender&wesender_action=connect' ), 'wesender_connect' ) ); ?>"
				class="ws-btn ws-btn-primary"
			>
				Verbinden met Wesender
			</a>

			<p style="margin-top:14px;font-size:13px;color:#9ca3af">
				Nog geen account?
				<a href="<?php echo esc_url( WESENDER_APP_URL . '/registreren' ); ?>" target="_blank" rel="noopener" style="color:#6b7280">
					Gratis aanmaken bij Wesender
				</a>
			</p>

		<?php endif; ?>
		</div><!-- .ws-card -->

		<div class="ws-footer">
			<a href="https://wesender.nl" target="_blank" rel="noopener">wesender.nl</a>
			<a href="<?php echo esc_url( WESENDER_APP_URL ); ?>" target="_blank" rel="noopener">Dashboard</a>
			<a href="https://wesender.nl/docs/apps/wordpress" target="_blank" rel="noopener">Documentatie</a>
		</div>
		</div><!-- #wesender-app -->
		</div><!-- .wrap -->
		<?php
	}

	// ── Render: Maillog ──────────────────────────────────────────────────────

	public function render_maillog(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$per_page = 30;
		$page     = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$data     = Wesender_Log::get_entries( $page, $per_page );
		$rows     = $data['rows'];
		$total    = $data['total'];
		$pages    = $total > 0 ? (int) ceil( $total / $per_page ) : 1;
		?>
		<div class="wrap" style="max-width:1000px">
		<div id="wesender-app">
		<?php $this->render_css(); ?>
		<?php $this->render_header( 'wesender-maillog' ); ?>

		<div class="ws-card" style="padding:0">

			<?php if ( empty( $rows ) ) : ?>
				<div class="ws-empty">
					<svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 10px;display:block">
						<path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
					</svg>
					<p style="margin:0;font-weight:600;color:#6b7280">Geen e-mails gelogd</p>
					<p style="margin:4px 0 0;font-size:12px">E-mails die via Wesender worden verstuurd verschijnen hier.</p>
				</div>
			<?php else : ?>
				<table class="ws-table">
					<thead>
						<tr>
							<th>Tijdstip</th>
							<th>Aan</th>
							<th>Onderwerp</th>
							<th>Bron</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td class="col-time"><?php echo esc_html( $this->format_time( $row->sent_at ) ); ?></td>
							<td class="col-to" title="<?php echo esc_attr( $row->to_address ); ?>">
								<?php echo esc_html( $row->to_address ); ?>
							</td>
							<td class="col-subj" title="<?php echo esc_attr( $row->subject ); ?>">
								<?php echo esc_html( $row->subject ); ?>
							</td>
							<td>
								<span style="font-size:12px;color:#6b7280;font-family:monospace">
									<?php echo esc_html( $this->format_source( $row->source ) ); ?>
								</span>
							</td>
							<td>
								<?php echo $this->render_status_badge( $row->status, $row->error_message ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<?php if ( 'failed' === $row->status && ! empty( $row->error_message ) ) : ?>
									<br><a
										href="<?php echo esc_url( 'https://wesender.nl/support?error=' . rawurlencode( substr( $row->error_message, 0, 200 ) ) . '&source=wp-plugin&v=' . WESENDER_VERSION ); ?>"
										target="_blank"
										rel="noopener"
										style="font-size:11px;color:#9ca3af;text-decoration:none"
									>Meld fout &rsaquo;</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<div style="padding:16px 16px;border-top:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center">
					<?php if ( $total > 0 ) : ?>
						<div class="ws-pagination">
							<span><?php printf( '%d van %d e-mails', count( $rows ) + ( $page - 1 ) * $per_page, $total ); ?></span>
							<span>
							<?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
								<?php if ( $i === $page ) : ?>
									<span class="current-page"><?php echo esc_html( $i ); ?></span>
								<?php else : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=wesender-maillog&paged=' . $i ) ); ?>"><?php echo esc_html( $i ); ?></a>
								<?php endif; ?>
							<?php endfor; ?>
							</span>
						</div>
					<?php endif; ?>

					<?php if ( $total > 0 ) : ?>
						<form method="post" onsubmit="return confirm('Log wissen? Dit kan niet ongedaan worden gemaakt.')">
							<?php wp_nonce_field( 'wesender_clear_log' ); ?>
							<input type="hidden" name="wesender_action" value="clear_log">
							<button type="submit" class="ws-btn ws-btn-secondary ws-btn-sm">Log wissen</button>
						</form>
					<?php endif; ?>
				</div>
			<?php endif; ?>

		</div><!-- .ws-card -->

		<div class="ws-footer">
			<a href="https://wesender.nl" target="_blank" rel="noopener">wesender.nl</a>
			<a href="<?php echo esc_url( WESENDER_APP_URL ); ?>" target="_blank" rel="noopener">Dashboard</a>
		</div>
		</div><!-- #wesender-app -->
		</div><!-- .wrap -->
		<?php
	}

	// ── Render: Blokkeren ────────────────────────────────────────────────────

	public function render_blokkeren(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$blocked     = (array) get_option( 'wesender_blocked_sources', [] );
		$log_sources = Wesender_Log::get_sources();

		// Build plugin list from all installed plugins.
		$installed = get_plugins();
		$plugins   = [];
		foreach ( $installed as $plugin_file => $plugin_data ) {
			$slug = dirname( $plugin_file );
			if ( '.' === $slug || 'wesender-wp' === $slug ) {
				continue;
			}
			$plugins[ $slug ] = $plugin_data['Name'];
		}
		ksort( $plugins );

		// Extra sources from log or blocklist not in installed plugins (themes, WP core, etc.)
		$extra = [];
		foreach ( array_merge( $log_sources, $blocked ) as $source ) {
			if ( ! isset( $plugins[ $source ] ) && 'wordpress' !== $source ) {
				$extra[ $source ] = true;
			}
		}
		ksort( $extra );
		?>
		<div class="wrap" style="max-width:780px">
		<div id="wesender-app">
		<?php $this->render_css(); ?>
		<style>
		#wesender-app .ws-plugin-table { width:100%; border-collapse:collapse; }
		#wesender-app .ws-plugin-table th {
			text-align:left; padding:9px 12px;
			border-bottom:1px solid #e5e7eb;
			font-size:11px; font-weight:700; letter-spacing:.06em;
			text-transform:uppercase; color:#9ca3af;
		}
		#wesender-app .ws-plugin-table td {
			padding:11px 12px; border-bottom:1px solid #f3f4f6;
			vertical-align:middle; font-size:13px; color:#374151;
		}
		#wesender-app .ws-plugin-table tr:last-child td { border-bottom:none; }
		#wesender-app .ws-plugin-table tr:hover td { background:#fafafa; }
		#wesender-app .ws-plugin-name-cell { font-weight:600; color:#111827; }
		#wesender-app .ws-plugin-slug-cell { color:#9ca3af; font-family:monospace; font-size:12px; }
		#wesender-app .ws-mail-count { font-size:12px; color:#6b7280; }
		#wesender-app .ws-has-mail { color:#059669; font-weight:600; }
		</style>
		<?php $this->render_header( 'wesender-blokkeren' ); ?>

		<div class="ws-card" style="padding:0">
			<div style="padding:20px 24px 16px">
				<p class="ws-section-label" style="margin:0 0 6px">Plugins blokkeren</p>
				<p style="font-size:13px;color:#6b7280;margin:0">
					Geblokkeerde plugins kunnen geen e-mails versturen via Wesender.
					E-mails worden onderschept en gelogd als "geblokkeerd".
				</p>
			</div>

			<?php if ( empty( $plugins ) && empty( $extra ) ) : ?>
				<div class="ws-empty">Geen plugins gevonden.</div>
			<?php else : ?>

				<?php
				// Get mail counts per source from log.
				global $wpdb;
				$counts_raw = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					"SELECT source, COUNT(*) as cnt FROM `{$wpdb->prefix}wesender_log` GROUP BY source"
				);
				$counts = [];
				foreach ( $counts_raw as $row ) {
					$counts[ $row->source ] = (int) $row->cnt;
				}
				?>

				<table class="ws-plugin-table">
					<thead>
						<tr>
							<th>Plugin</th>
							<th>Map</th>
							<th>E-mails</th>
							<th>Status</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $plugins as $slug => $name ) :
						$is_blocked   = in_array( $slug, $blocked, true );
						$mail_count   = $counts[ $slug ] ?? 0;
						$block_action = $is_blocked ? 'unblock' : 'block';
						$btn_label    = $is_blocked ? 'Deblokkeren' : 'Blokkeren';
						$btn_class    = $is_blocked ? 'ws-btn-danger' : 'ws-btn-secondary';
						?>
						<tr>
							<td class="ws-plugin-name-cell"><?php echo esc_html( $name ); ?></td>
							<td class="ws-plugin-slug-cell"><?php echo esc_html( $slug ); ?></td>
							<td class="ws-mail-count <?php echo $mail_count > 0 ? 'ws-has-mail' : ''; ?>">
								<?php echo $mail_count > 0 ? esc_html( $mail_count ) : '-'; ?>
							</td>
							<td>
								<?php if ( $is_blocked ) : ?>
									<span class="ws-badge-blocked-pill">geblokkeerd</span>
								<?php else : ?>
									<span style="font-size:12px;color:#9ca3af">Actief</span>
								<?php endif; ?>
							</td>
							<td style="text-align:right">
								<form method="post">
									<?php wp_nonce_field( 'wesender_block_toggle' ); ?>
									<input type="hidden" name="wesender_action"       value="block_toggle">
									<input type="hidden" name="wesender_source"       value="<?php echo esc_attr( $slug ); ?>">
									<input type="hidden" name="wesender_block_action" value="<?php echo esc_attr( $block_action ); ?>">
									<button type="submit" class="ws-btn ws-btn-sm <?php echo esc_attr( $btn_class ); ?>">
										<?php echo esc_html( $btn_label ); ?>
									</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>

					<?php foreach ( array_keys( $extra ) as $source ) :
						$is_blocked   = in_array( $source, $blocked, true );
						$mail_count   = $counts[ $source ] ?? 0;
						$block_action = $is_blocked ? 'unblock' : 'block';
						$btn_label    = $is_blocked ? 'Deblokkeren' : 'Blokkeren';
						$btn_class    = $is_blocked ? 'ws-btn-danger' : 'ws-btn-secondary';
						?>
						<tr>
							<td class="ws-plugin-name-cell"><?php echo esc_html( $this->format_source_label( $source ) ); ?></td>
							<td class="ws-plugin-slug-cell"><?php echo esc_html( $source ); ?></td>
							<td class="ws-mail-count <?php echo $mail_count > 0 ? 'ws-has-mail' : ''; ?>">
								<?php echo $mail_count > 0 ? esc_html( $mail_count ) : '-'; ?>
							</td>
							<td>
								<?php if ( $is_blocked ) : ?>
									<span class="ws-badge-blocked-pill">geblokkeerd</span>
								<?php else : ?>
									<span style="font-size:12px;color:#9ca3af">Actief</span>
								<?php endif; ?>
							</td>
							<td style="text-align:right">
								<form method="post">
									<?php wp_nonce_field( 'wesender_block_toggle' ); ?>
									<input type="hidden" name="wesender_action"       value="block_toggle">
									<input type="hidden" name="wesender_source"       value="<?php echo esc_attr( $source ); ?>">
									<input type="hidden" name="wesender_block_action" value="<?php echo esc_attr( $block_action ); ?>">
									<button type="submit" class="ws-btn ws-btn-sm <?php echo esc_attr( $btn_class ); ?>">
										<?php echo esc_html( $btn_label ); ?>
									</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="ws-footer" style="margin-top:14px">
			<a href="https://wesender.nl" target="_blank" rel="noopener">wesender.nl</a>
			<a href="<?php echo esc_url( WESENDER_APP_URL ); ?>" target="_blank" rel="noopener">Dashboard</a>
		</div>
		</div><!-- #wesender-app -->
		</div><!-- .wrap -->
		<?php
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	private function render_status_badge( string $status, ?string $error = null ): string {
		if ( 'sent' === $status ) {
			return '<span class="ws-badge-sent">Verstuurd</span>';
		}
		if ( 'blocked' === $status ) {
			return '<span class="ws-badge-blocked">Geblokkeerd</span>';
		}
		$tip = $error ? ' title="' . esc_attr( $error ) . '"' : '';
		return '<span class="ws-badge-failed"' . $tip . '>Mislukt</span>';
	}

	private function format_time( string $mysql_time ): string {
		$ts = strtotime( $mysql_time );
		if ( ! $ts ) {
			return $mysql_time;
		}
		$diff = time() - $ts;
		if ( $diff < 60 ) {
			return 'zojuist';
		}
		if ( $diff < 3600 ) {
			return round( $diff / 60 ) . ' min geleden';
		}
		if ( $diff < 86400 ) {
			return round( $diff / 3600 ) . ' uur geleden';
		}
		return date_i18n( 'd M H:i', $ts );
	}

	private function format_source( string $source ): string {
		if ( '' === $source ) {
			return '-';
		}
		if ( 0 === strpos( $source, 'theme:' ) ) {
			return 'thema: ' . substr( $source, 6 );
		}
		return $source;
	}

	private function format_source_label( string $source ): string {
		if ( '' === $source || 'wordpress' === $source ) {
			return 'WordPress (kern)';
		}
		if ( 0 === strpos( $source, 'theme:' ) ) {
			$slug = substr( $source, 6 );
			return 'Thema: ' . ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );
		}
		return ucwords( str_replace( [ '-', '_' ], ' ', $source ) );
	}
}
