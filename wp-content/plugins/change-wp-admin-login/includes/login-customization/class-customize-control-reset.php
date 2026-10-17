<?php
/**
 * Customizer control: master reset button (no meaningful saved value).
 *
 * @package AIO_Login_Pro
 */

namespace AIO_Login_Pro\Login_Customization;

defined( 'ABSPATH' ) || exit;

/**
 * Class Customize_Control_Reset
 */
class Customize_Control_Reset extends \WP_Customize_Control {

	/**
	 * Control type.
	 *
	 * @var string
	 */
	public $type = 'aio_login_reset';

	/**
	 * Render control markup.
	 */
	protected function render_content() {
		?>
		<?php if ( $this->label ) { ?>
			<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
		<?php } ?>
		<?php if ( $this->description ) { ?>
			<p class="description"><?php echo esc_html( $this->description ); ?></p>
		<?php } ?>
		<p>
			<button type="button" class="button button-secondary aio-login-customizer-reset-btn" id="aio-login-customizer-reset-all">
				<?php esc_html_e( 'Reset all to defaults', 'change-wp-admin-login' ); ?>
			</button>
		</p>
		<?php
	}
}
