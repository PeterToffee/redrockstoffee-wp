<?php
/**
 * Harry Transition — path + sprite registry (all hand-editable in WP Admin)
 *
 * Ported from TheUnframer-WP (wp-content/themes/TheUnframer/inc/harry-path.php).
 * The flight engine (assets/harry-path.js) and its CSS are copied over
 * byte-for-byte — they only ever read window.HARRY_PATHS, no theme-specific
 * dependencies. What's different here is the admin screen: TheUnframer
 * builds it with ACF Pro's Options Page + repeater fields; this site has
 * no ACF installed (confirmed absent 2026-10), so this is a native
 * wp-admin page instead — same Sprites/Layouts repeater idea, plain HTML
 * forms + a little vanilla JS for add/remove rows and the media picker,
 * saved to two plain options (rrt_harry_sprites / rrt_harry_layouts)
 * instead of ACF fields. Same "nothing needs editing in code again once
 * it's in place" goal.
 *
 * Dashboard → "Harry": two repeaters.
 *   Sprites  — key / label / image upload   (harry is built in)
 *   Layouts  — key / sprite / path JSON / active / start–end dates
 *
 * The flight engine reads this data as window.HARRY_PATHS and is fired
 * from a page with:  playHarryTransition('<layout key>')
 *
 * @package RRT_Label_Configurator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 *  1. Admin screen  (Dashboard → Harry)
 * ---------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_menu_page(
		'Harry Transitions',
		'Harry',
		'edit_posts',
		'rrt-harry-transitions',
		'rrt_harry_render_admin_page',
		'dashicons-airplane',
		59
	);
} );

/* -------------------------------------------------------------------------
 *  2. Save handler
 * ---------------------------------------------------------------------- */
add_action( 'admin_post_rrt_save_harry_paths', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'rrt_save_harry_paths' );

	$sprites_in = isset( $_POST['sprites'] ) && is_array( $_POST['sprites'] ) ? wp_unslash( $_POST['sprites'] ) : array();
	$sprites    = array();
	foreach ( $sprites_in as $row ) {
		$key = isset( $row['key'] ) ? sanitize_key( $row['key'] ) : '';
		if ( '' === $key ) {
			continue;
		}
		$sprites[] = array(
			'key'   => $key,
			'label' => isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '',
			'image' => isset( $row['image'] ) ? (int) $row['image'] : 0,
		);
	}

	$layouts_in = isset( $_POST['layouts'] ) && is_array( $_POST['layouts'] ) ? wp_unslash( $_POST['layouts'] ) : array();
	$layouts    = array();
	foreach ( $layouts_in as $row ) {
		$key = isset( $row['key'] ) ? sanitize_key( $row['key'] ) : '';
		if ( '' === $key ) {
			continue;
		}
		$layouts[] = array(
			'key'            => $key,
			'sprite'         => isset( $row['sprite'] ) ? sanitize_text_field( $row['sprite'] ) : 'harry',
			'active'         => ! empty( $row['active'] ) ? 1 : 0,
			'start'          => isset( $row['start'] ) ? sanitize_text_field( $row['start'] ) : '',
			'end'            => isset( $row['end'] ) ? sanitize_text_field( $row['end'] ) : '',
			'path_json'      => isset( $row['path_json'] ) ? wp_unslash( $row['path_json'] ) : '',
			'path_json_mobile' => isset( $row['path_json_mobile'] ) ? wp_unslash( $row['path_json_mobile'] ) : '',
			'autoplay_paths' => isset( $row['autoplay_paths'] ) ? wp_unslash( $row['autoplay_paths'] ) : '',
		);
	}

	update_option( 'rrt_harry_sprites', $sprites );
	update_option( 'rrt_harry_layouts', $layouts );

	wp_safe_redirect( add_query_arg( array( 'page' => 'rrt-harry-transitions', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
	exit;
} );

/* -------------------------------------------------------------------------
 *  3. Admin screen markup
 * ---------------------------------------------------------------------- */
function rrt_harry_render_admin_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	wp_enqueue_media();

	$sprites = get_option( 'rrt_harry_sprites', array() );
	$layouts = get_option( 'rrt_harry_layouts', array() );
	if ( ! is_array( $sprites ) ) {
		$sprites = array();
	}
	if ( ! is_array( $layouts ) ) {
		$layouts = array();
	}
	?>
	<div class="wrap">
		<h1>Harry Transitions</h1>
		<?php if ( isset( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Saved.</p></div>
		<?php endif; ?>

		<p><strong>Sprites</strong> are the icons Harry can appear as. <strong>Layouts</strong> are saved flight paths.</p>
		<ol>
			<li>Add a sprite row (upload a PNG, give it a lower-case key such as <code>santa</code>).</li>
			<li>Lay out a path with the Harry Path Editor on the real page, hit Export → JSON.</li>
			<li>Add a layout row: pick a key (e.g. <code>co-branding</code>), choose a sprite, paste the JSON, tick Active.</li>
			<li>On the page where it should fire, the theme calls <code>playHarryTransition('co-branding')</code>.</li>
		</ol>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'rrt_save_harry_paths' ); ?>
			<input type="hidden" name="action" value="rrt_save_harry_paths">

			<h2>Sprites</h2>
			<p class="description">"harry" is built in (the Harry logo). Add a row for each extra icon.</p>
			<table class="widefat" id="rrt-harry-sprites-table">
				<thead>
					<tr>
						<th style="width:20%">Key</th>
						<th style="width:25%">Label</th>
						<th style="width:40%">Image</th>
						<th style="width:15%"></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sprites as $i => $row ) : ?>
						<?php rrt_harry_render_sprite_row( $i, $row ); ?>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button" id="rrt-harry-add-sprite">+ Add sprite</button></p>

			<h2>Layouts</h2>
			<p class="description">One saved flight path per row.</p>
			<div id="rrt-harry-layouts-list">
				<?php foreach ( $layouts as $i => $row ) : ?>
					<?php rrt_harry_render_layout_row( $i, $row ); ?>
				<?php endforeach; ?>
			</div>
			<p><button type="button" class="button" id="rrt-harry-add-layout">+ Add layout</button></p>

			<?php submit_button( 'Save Harry Transitions' ); ?>
		</form>
	</div>

	<template id="rrt-harry-sprite-row-tpl">
		<?php rrt_harry_render_sprite_row( '__INDEX__', array() ); ?>
	</template>
	<template id="rrt-harry-layout-row-tpl">
		<?php rrt_harry_render_layout_row( '__INDEX__', array() ); ?>
	</template>

	<style>
		.rrt-harry-layout-row { border: 1px solid #dcdcde; background: #fff; padding: 14px 16px; margin-bottom: 14px; }
		.rrt-harry-layout-row .rrt-harry-field { margin-bottom: 10px; }
		.rrt-harry-layout-row label { display: block; font-weight: 600; margin-bottom: 4px; }
		.rrt-harry-layout-row textarea { width: 100%; font-family: monospace; font-size: 12px; }
		.rrt-harry-layout-row .rrt-harry-row-top { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; }
		.rrt-harry-layout-row .rrt-harry-row-top > div { flex: 1 1 160px; }
		.rrt-harry-sprite-thumb { max-width: 48px; max-height: 48px; vertical-align: middle; margin-right: 8px; }
	</style>
	<script>
	(function () {
		var spriteIdx = <?php echo (int) count( $sprites ); ?>;
		var layoutIdx = <?php echo (int) count( $layouts ); ?>;

		document.getElementById( 'rrt-harry-add-sprite' ).addEventListener( 'click', function () {
			var tpl = document.getElementById( 'rrt-harry-sprite-row-tpl' ).innerHTML.replace( /__INDEX__/g, spriteIdx++ );
			document.querySelector( '#rrt-harry-sprites-table tbody' ).insertAdjacentHTML( 'beforeend', tpl );
		} );
		document.getElementById( 'rrt-harry-add-layout' ).addEventListener( 'click', function () {
			var tpl = document.getElementById( 'rrt-harry-layout-row-tpl' ).innerHTML.replace( /__INDEX__/g, layoutIdx++ );
			document.getElementById( 'rrt-harry-layouts-list' ).insertAdjacentHTML( 'beforeend', tpl );
		} );

		document.addEventListener( 'click', function ( e ) {
			var rm = e.target.closest( '.rrt-harry-remove-row' );
			if ( rm ) {
				e.preventDefault();
				rm.closest( 'tr, .rrt-harry-layout-row' ).remove();
				return;
			}
			var pick = e.target.closest( '.rrt-harry-pick-image' );
			if ( pick ) {
				e.preventDefault();
				var wrap = pick.closest( '.rrt-harry-image-cell' );
				var frame = wp.media( { title: 'Choose sprite image', multiple: false } );
				frame.on( 'select', function () {
					var att = frame.state().get( 'selection' ).first().toJSON();
					wrap.querySelector( 'input[type=hidden]' ).value = att.id;
					var img = wrap.querySelector( 'img' );
					var url = ( att.sizes && att.sizes.thumbnail ) ? att.sizes.thumbnail.url : att.url;
					if ( ! img ) {
						img = document.createElement( 'img' );
						img.className = 'rrt-harry-sprite-thumb';
						wrap.insertBefore( img, pick );
					}
					img.src = url;
				} );
				frame.open();
			}
		} );
	})();
	</script>
	<?php
}

function rrt_harry_render_sprite_row( $i, $row ) {
	$key   = isset( $row['key'] ) ? $row['key'] : '';
	$label = isset( $row['label'] ) ? $row['label'] : '';
	$image = isset( $row['image'] ) ? (int) $row['image'] : 0;
	?>
	<tr>
		<td><input type="text" name="sprites[<?php echo esc_attr( $i ); ?>][key]" value="<?php echo esc_attr( $key ); ?>" placeholder="e.g. santa" style="width:100%"></td>
		<td><input type="text" name="sprites[<?php echo esc_attr( $i ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" style="width:100%"></td>
		<td class="rrt-harry-image-cell">
			<?php if ( $image ) : ?>
				<img class="rrt-harry-sprite-thumb" src="<?php echo esc_url( wp_get_attachment_image_url( $image, 'thumbnail' ) ); ?>">
			<?php endif; ?>
			<input type="hidden" name="sprites[<?php echo esc_attr( $i ); ?>][image]" value="<?php echo esc_attr( $image ); ?>">
			<button type="button" class="button rrt-harry-pick-image">Choose Image</button>
		</td>
		<td><button type="button" class="button-link-delete rrt-harry-remove-row">Remove</button></td>
	</tr>
	<?php
}

function rrt_harry_render_layout_row( $i, $row ) {
	$key      = isset( $row['key'] ) ? $row['key'] : '';
	$sprite   = isset( $row['sprite'] ) ? $row['sprite'] : 'harry';
	$active   = ! empty( $row['active'] );
	$start    = isset( $row['start'] ) ? $row['start'] : '';
	$end      = isset( $row['end'] ) ? $row['end'] : '';
	$pj       = isset( $row['path_json'] ) ? $row['path_json'] : '';
	$pjm      = isset( $row['path_json_mobile'] ) ? $row['path_json_mobile'] : '';
	$autoplay = isset( $row['autoplay_paths'] ) ? $row['autoplay_paths'] : '';
	?>
	<div class="rrt-harry-layout-row">
		<div class="rrt-harry-row-top">
			<div>
				<label>Key</label>
				<input type="text" name="layouts[<?php echo esc_attr( $i ); ?>][key]" value="<?php echo esc_attr( $key ); ?>" placeholder="e.g. co-branding">
			</div>
			<div>
				<label>Sprite</label>
				<input type="text" name="layouts[<?php echo esc_attr( $i ); ?>][sprite]" value="<?php echo esc_attr( $sprite ); ?>" placeholder="harry">
			</div>
			<div>
				<label><input type="checkbox" name="layouts[<?php echo esc_attr( $i ); ?>][active]" value="1" <?php checked( $active ); ?>> Active</label>
			</div>
			<div>
				<label>Show from</label>
				<input type="date" name="layouts[<?php echo esc_attr( $i ); ?>][start]" value="<?php echo esc_attr( $start ); ?>">
			</div>
			<div>
				<label>Show until</label>
				<input type="date" name="layouts[<?php echo esc_attr( $i ); ?>][end]" value="<?php echo esc_attr( $end ); ?>">
			</div>
			<div>
				<button type="button" class="button-link-delete rrt-harry-remove-row">Remove layout</button>
			</div>
		</div>
		<div class="rrt-harry-field">
			<label>Desktop path JSON</label>
			<textarea name="layouts[<?php echo esc_attr( $i ); ?>][path_json]" rows="4"><?php echo esc_textarea( $pj ); ?></textarea>
		</div>
		<div class="rrt-harry-field">
			<label>Mobile path JSON (optional — used ≤768px wide)</label>
			<textarea name="layouts[<?php echo esc_attr( $i ); ?>][path_json_mobile]" rows="4"><?php echo esc_textarea( $pjm ); ?></textarea>
		</div>
		<div class="rrt-harry-field">
			<label>Auto-play on these page paths (one per line, e.g. <code>/co-branding/</code> or <code>/</code> for home)</label>
			<textarea name="layouts[<?php echo esc_attr( $i ); ?>][autoplay_paths]" rows="2"><?php echo esc_textarea( $autoplay ); ?></textarea>
		</div>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 *  4. Build the front-end payload  ->  window.HARRY_PATHS
 * ---------------------------------------------------------------------- */
function rrt_harry_paths_payload() {

	$payload = array(
		'sprites' => array(
			'harry' => RRT_LABEL_URL . 'assets/images/harry-logo.png',
		),
		'layouts' => array(),
	);

	$sprites = get_option( 'rrt_harry_sprites', array() );
	if ( is_array( $sprites ) ) {
		foreach ( $sprites as $row ) {
			$key = isset( $row['key'] ) ? sanitize_key( $row['key'] ) : '';
			$img = isset( $row['image'] ) ? (int) $row['image'] : 0;
			if ( '' === $key || ! $img ) {
				continue;
			}
			$url = wp_get_attachment_image_url( $img, 'full' );
			if ( $url ) {
				$payload['sprites'][ $key ] = $url;
			}
		}
	}

	$layouts = get_option( 'rrt_harry_layouts', array() );
	if ( is_array( $layouts ) ) {
		foreach ( $layouts as $row ) {
			$key = isset( $row['key'] ) ? sanitize_key( $row['key'] ) : '';
			if ( '' === $key ) {
				continue;
			}
			$path = json_decode( (string) ( $row['path_json'] ?? '' ), true );
			if ( ! is_array( $path ) ) {
				$path = null;
			}
			$path_mobile = json_decode( (string) ( $row['path_json_mobile'] ?? '' ), true );
			if ( ! is_array( $path_mobile ) ) {
				$path_mobile = null;
			}
			$autoplay = array();
			foreach ( preg_split( '/[\r\n]+/', (string) ( $row['autoplay_paths'] ?? '' ) ) as $line ) {
				$line = trim( $line );
				if ( '' !== $line ) {
					$autoplay[] = '/' . trim( wp_parse_url( $line, PHP_URL_PATH ) ?: $line, '/' );
				}
			}

			$payload['layouts'][ $key ] = array(
				'sprite'        => ! empty( $row['sprite'] ) ? $row['sprite'] : 'harry',
				'active'        => ! empty( $row['active'] ),
				'start'         => ! empty( $row['start'] ) ? str_replace( '-', '', $row['start'] ) : '',
				'end'           => ! empty( $row['end'] ) ? str_replace( '-', '', $row['end'] ) : '',
				'path'          => $path,
				'pathMobile'    => $path_mobile,
				'autoplayPaths' => $autoplay,
			);
		}
	}

	return apply_filters( 'rrt_harry_paths_payload', $payload );
}

/* -------------------------------------------------------------------------
 *  5. Enqueue engine + data  (site-wide; tiny)
 * ---------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'rrt-harry-path',
		RRT_LABEL_URL . 'assets/harry-path.css',
		array(),
		RRT_LABEL_VERSION
	);

	wp_enqueue_script(
		'rrt-harry-path',
		RRT_LABEL_URL . 'assets/harry-path.js',
		array(),
		RRT_LABEL_VERSION,
		true
	);

	wp_localize_script( 'rrt-harry-path', 'HARRY_PATHS', rrt_harry_paths_payload() );
} );

/* -------------------------------------------------------------------------
 *  6. [harry_test] — a dev-only page to prove it fires on a real page
 * ---------------------------------------------------------------------- */
add_shortcode( 'harry_test', function () {
	$payload = rrt_harry_paths_payload();
	$layouts = $payload['layouts'];

	ob_start();
	?>
	<div style="position:sticky;top:0;z-index:50;margin:0 0 20px;padding:16px 20px;border:2px dashed #c9bfae;border-radius:0 0 14px 14px;background:#faf6eeee;backdrop-filter:blur(3px);font-family:system-ui,sans-serif">
		<p style="margin:0 0 10px;font-weight:800;letter-spacing:.04em;color:#6f645d">HARRY PATH — TEST &nbsp;<span style="font-weight:600">(scroll room below so tall paths can be followed)</span></p>
		<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
			<button type="button" data-harry-fire="__demo" style="cursor:pointer;border:0;border-radius:10px;padding:.6rem 1.2rem;font-weight:800;color:#fff;background:#991e21">Fire built-in demo path</button>
			<?php foreach ( $layouts as $lk => $lv ) : ?>
				<button type="button" data-harry-fire="<?php echo esc_attr( $lk ); ?>"
					style="cursor:pointer;border:0;border-radius:10px;padding:.6rem 1.2rem;font-weight:800;color:#fff;background:<?php echo $lv['active'] ? '#7a181b' : '#b9b0a4'; ?>">
					<?php echo esc_html( $lk ); ?><?php echo $lv['active'] ? '' : ' (inactive)'; ?>
				</button>
			<?php endforeach; ?>
		</div>
		<?php if ( empty( $layouts ) ) : ?>
			<p style="margin:10px 0 0;color:#6f645d;font-size:.9rem">No saved layouts yet. Add one under <strong>Harry → Layouts</strong> (wp-admin) and it shows up here as a button.</p>
		<?php endif; ?>
	</div>
	<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
		<div style="height:100vh;border-bottom:1px solid #e2d9c8;display:flex;align-items:center;justify-content:center;font:800 3rem/1 system-ui,sans-serif;color:#ece3d2;user-select:none">screen <?php echo $i; ?></div>
	<?php endfor; ?>
	<script>
	document.addEventListener('click',function(e){
		var b=e.target.closest('[data-harry-fire]'); if(!b) return;
		var k=b.getAttribute('data-harry-fire');
		if(typeof window.playHarryTransition!=='function'){ alert('harry-path.js not loaded'); return; }
		window.playHarryTransition(k==='__demo'?null:k,{force:true});
	});
	</script>
	<?php
	return ob_get_clean();
} );

/* -------------------------------------------------------------------------
 *  7. Auto-play — a layout bound to the current page path plays itself
 *     ~0.4s after load. Admins can force a replay with ?harryplay=1
 *     (or ?harryplay=<layout-key>).
 * ---------------------------------------------------------------------- */
add_action( 'wp_footer', function () {
	if ( is_admin() ) {
		return;
	}

	$layouts = rrt_harry_paths_payload()['layouts'];
	if ( empty( $layouts ) ) {
		return;
	}

	$req  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
	$here = '/' . trim( (string) wp_parse_url( $req, PHP_URL_PATH ), '/' );

	$fire_key = '';
	$forced   = false;

	$manual = isset( $_GET['harryplay'] ) ? sanitize_text_field( wp_unslash( $_GET['harryplay'] ) ) : '';
	if ( '' !== $manual && current_user_can( 'edit_posts' ) ) {
		if ( '1' === $manual ) {
			foreach ( $layouts as $lk => $lv ) {
				if ( in_array( $here, (array) $lv['autoplayPaths'], true ) ) {
					$fire_key = $lk;
					break;
				}
			}
			if ( '' === $fire_key ) {
				$fire_key = array_key_first( $layouts );
			}
		} elseif ( isset( $layouts[ $manual ] ) ) {
			$fire_key = $manual;
		}
		$forced = true;
	} else {
		foreach ( $layouts as $lk => $lv ) {
			if ( $lv['active'] && ! empty( $lv['autoplayPaths'] ) && in_array( $here, (array) $lv['autoplayPaths'], true ) ) {
				$fire_key = $lk;
				break;
			}
		}
	}

	if ( '' === $fire_key ) {
		return;
	}
	?>
	<script>
	(function () {
		function go() {
			if (typeof window.playHarryTransition !== 'function') return;
			setTimeout(function () {
				window.playHarryTransition(<?php echo wp_json_encode( $fire_key ); ?><?php echo $forced ? ', {force:true}' : ''; ?>);
			}, 550);
		}
		if (document.readyState === 'complete') {
			go();
		} else {
			window.addEventListener('load', go);
		}
	})();
	</script>
	<?php
} );
