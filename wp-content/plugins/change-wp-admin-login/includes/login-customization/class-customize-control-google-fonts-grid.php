<?php
/**
 * Customizer control: Google Fonts as a scrollable grid with live name + preview.
 *
 * @package AIO_Login_Pro
 */

namespace AIO_Login_Pro\Login_Customization;

defined( 'ABSPATH' ) || exit;

/**
 * Class Customize_Control_Google_Fonts_Grid
 */
class Customize_Control_Google_Fonts_Grid extends \WP_Customize_Control {

	/**
	 * Control type.
	 *
	 * @var string
	 */
	public $type = 'aio_login_google_fonts_grid';

	/**
	 * Font value => label.
	 *
	 * @var array<string, string>
	 */
	public $choices = array();

	/**
	 * Whether Pro is active (unlocks fonts other than free value).
	 *
	 * @var bool
	 */
	public $has_pro = true;

	/**
	 * Value usable without Pro (usually Inherit).
	 *
	 * @var string
	 */
	public $free_value = 'Inherit';

	/**
	 * Render control markup.
	 */
	protected function render_content() {
		$input_id = '_customize-input-' . $this->id;
		$value    = $this->value();

		if ( ! isset( $this->choices[ $value ] ) ) {
			$value = $this->free_value;
			if ( ! isset( $this->choices[ $value ] ) ) {
				foreach ( $this->choices as $k => $_ ) {
					$value = $k;
					break;
				}
			}
		}

		$catalog_rest = array();
		foreach ( $this->choices as $slug => $label ) {
			if ( (string) $slug !== (string) $value ) {
				$catalog_rest[ $slug ] = $label;
			}
		}

		$show_pro_overlay = ! $this->has_pro;
		if ( $show_pro_overlay ) {
			$show_pro_overlay = false;
			foreach ( array_keys( $catalog_rest ) as $slug ) {
				if ( (string) $slug !== (string) $this->free_value ) {
					$show_pro_overlay = true;
					break;
				}
			}
		}

		?>
		<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
		<?php if ( ! empty( $this->description ) ) : ?>
			<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
		<?php endif; ?>
		<div class="aio-login__customizer-font-grid-wrap">
			<label class="screen-reader-text" for="<?php echo esc_attr( $this->id ); ?>-search"><?php esc_html_e( 'Search fonts', 'change-wp-admin-login' ); ?></label>
			<input
				type="search"
				id="<?php echo esc_attr( $this->id ); ?>-search"
				class="aio-login__customizer-font-search"
				placeholder="<?php esc_attr_e( 'Search fonts…', 'change-wp-admin-login' ); ?>"
				autocomplete="off"
			/>
			<div class="aio-login__customizer-font-grid aio-login__customizer-font-grid--selected" role="list" aria-label="<?php esc_attr_e( 'Selected font', 'change-wp-admin-login' ); ?>">
				<?php
				if ( isset( $this->choices[ $value ] ) ) {
					$this->render_font_button( (string) $value, (string) $this->choices[ $value ], $value, false );
				}
				?>
			</div>
			<?php if ( count( $this->choices ) > 1 ) : ?>
			<div class="aio-login__customizer-pro-gate-area<?php echo ! $this->has_pro ? ' has-pro-gate' : ''; ?>">
				<div class="aio-login__customizer-font-grid aio-login__customizer-font-grid--catalog" role="list" aria-label="<?php esc_attr_e( 'Google Fonts catalog', 'change-wp-admin-login' ); ?>">
					<?php
					foreach ( $catalog_rest as $slug => $label ) {
						$is_locked = ! $this->has_pro && (string) $slug !== (string) $this->free_value;
						$this->render_font_button( (string) $slug, (string) $label, $value, $is_locked );
					}
					?>
				</div>
				<?php if ( $show_pro_overlay ) : ?>
				<div class="aio-login__customizer-font-pro-unified-overlay" aria-hidden="true">
				</div>
				<?php endif; ?>
			</div>
			<?php endif; ?>
			<input type="hidden" id="<?php echo esc_attr( $input_id ); ?>" <?php $this->link(); ?> value="<?php echo esc_attr( $value ); ?>" />
		</div>
		<?php
	}

	/**
	 * Single font tile (no per-tile Pro badge — catalog uses one overlay when gated).
	 *
	 * @param string $slug Font setting value.
	 * @param string $label Label.
	 * @param string $value Current value.
	 * @param bool   $is_locked Whether tile requires Pro.
	 */
	private function render_font_button( $slug, $label, $value, $is_locked ) {
		$sel        = ( $value === $slug );
		$is_inherit = ( 'Inherit' === $slug );
		$preview_ff = $is_inherit
			? 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'
			: "'" . $slug . "', sans-serif";
		?>
		<button
			type="button"
			role="listitem"
			class="aio-login__customizer-font-item<?php echo $sel ? ' is-selected' : ''; ?><?php echo $is_locked ? ' is-locked' : ''; ?>"
			data-value="<?php echo esc_attr( $slug ); ?>"
			data-font="<?php echo esc_attr( $slug ); ?>"
			aria-pressed="<?php echo $sel ? 'true' : 'false'; ?>"
			<?php echo $is_locked ? ' aria-disabled="true"' : ''; ?>
		>
			<span class="aio-login__customizer-font-preview-wrap">
				<span class="aio-login__customizer-font-preview" style="font-family: <?php echo esc_attr( $preview_ff ); ?>"><?php esc_html_e( 'Aa Bb', 'change-wp-admin-login' ); ?></span>
			</span>
			<span class="aio-login__customizer-font-name"><?php echo esc_html( $label ); ?></span>
		</button>
		<?php
	}
}
