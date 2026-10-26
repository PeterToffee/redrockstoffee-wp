					<div id="quote-module-card" class="config-card" style="display:none;">
						<div class="config-section text-section-bordered">
						<div style="border:2px solid #991e21;border-radius:10px;padding:0.6rem 0.75rem 0.5rem;margin-bottom:0.5rem;box-sizing:border-box;">
						<p id="text-guide-long" style="margin:0 0 0.5rem;font-size:0.8rem;font-weight:600;color:#991e21;font-family:'Poppins',sans-serif;letter-spacing:0.01em;line-height:1.35;">Start with your background elements — each new layer lands on top. Have fun!</p>
						<p id="text-guide-short" style="display:none;margin:0 0 0.4rem;font-size:0.72rem;font-weight:600;color:#991e21;font-family:'Poppins',sans-serif;letter-spacing:0.01em;line-height:1.3;">Building layers? Start with background elements first.</p>
							<div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:nowrap; width:100%;">
								<button type="button" id="tab-btn-logo" class="rrt-pill-tab" data-tab="logo" style="flex:1; min-width:0;">Art-Logo</button>
								<button type="button" id="tab-btn-quote" class="rrt-pill-tab" data-tab="quote" style="flex:1; min-width:0;">Add Text</button>
							</div>
							</div>

							<div id="text-layers-wrap" style="display:none;">

								<!-- BODY TEXT PANEL -->
								<div class="text-tab-panel" id="tab-panel-quote">

									<!-- Body text group selector row -->
									<!-- Pete, 2026-10-17: "shadow slider is now 2 lines, get it back to
									     one line" — the default .slider-step-btn size (34px, same as the
									     rest of this site's touch targets) made this row too wide for its
									     old flex-wrap:wrap, so it broke to a second line. Same nowrap +
									     overflow-x:auto safety net as the font/width/color row below, plus
									     compact 18px step buttons on the shadow slider to match. -->
									<!-- Pete, 2026-10-21: "remove the lettering 'text group' and make
									     the line look exactly like the line on theunframer upload
									     configurator" — label dropped, shadow on/off checkbox removed
									     (shadow-intensity-slider at 0 IS "off" now, same as Unframer's
									     version of this row — see renderTextGroup() in
									     label-configurator.js), Color moved up from the row below to
									     sit here too, all matching Unframer's current layout exactly. -->
									<div style="display:flex; align-items:center; gap:0.4rem; margin-bottom:0.5rem; flex-wrap:nowrap; overflow-x:auto; overflow-y:hidden;">
										<div id="quote-selector-btns" style="display:flex; gap:0.3rem; flex-wrap:nowrap;"></div>
										<button id="quote-add-btn" type="button" style="font-size:0.65rem; padding:0.2rem 0.45rem; background:#991e21; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:700; font-family:'Poppins',sans-serif; flex-shrink:0;">+ Add</button>
										<button id="quote-remove-btn" type="button" style="font-size:0.65rem; padding:0.2rem 0.45rem; background:#e53e3e; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:700; font-family:'Poppins',sans-serif; flex-shrink:0;">× Remove</button>
										<div style="flex-shrink:0;">
											<div class="slider-header">
												<span class="slider-name">Shadow</span>
											</div>
											<div class="slider-with-btns">
												<button class="slider-step-btn" data-for="shadow-intensity-slider" data-dir="-1" style="width:18px;height:18px;flex-shrink:0;">−</button>
												<input type="range" class="style-slider" id="shadow-intensity-slider" min="0" max="100" value="60" step="5" style="width:32px; flex-shrink:0;" title="Shadow intensity">
												<button class="slider-step-btn" data-for="shadow-intensity-slider" data-dir="1" style="width:18px;height:18px;flex-shrink:0;">+</button>
											</div>
										</div>
										<div id="text-color-row" style="display:flex; flex-direction:column; align-items:flex-start; flex-shrink:0;">
											<div class="slider-header">
												<span class="slider-name">Color</span>
											</div>
											<div class="color-row" id="text-color-swatches" style="display:none;">
												<div class="color-swatch" data-color="red" title="Red"></div>
												<div class="color-swatch" data-color="gold" title="Gold"></div>
												<div class="color-swatch" data-color="brown" title="Brown"></div>
												<div class="color-swatch" data-color="cream" title="Cream"></div>
												<div class="color-swatch" data-color="slate" title="Slate"></div>
											</div>
											<input type="color" id="text-color-custom" value="#991e21" title="Custom color">
										</div>
									</div>

									<div class="quote-textarea-wrapper" id="textarea-wrapper" style="display:none;">
										<textarea class="quote-textarea" id="quote-text" rows="1" placeholder="Add Words Here"></textarea>
									</div>

									<div class="quote-options" id="quote-options">

										<!-- Pete, 2026-10-17: "remove spacemaker slider under type/color
										     line" — overflow-y:hidden added alongside overflow-x:auto. Per the
										     CSS overflow spec, setting only overflow-x to a non-visible value
										     silently computes overflow-y to auto too, which was producing a
										     real (if subtle) native vertical scrollbar with up/down arrows
										     whenever the COLOR column's content was a hair taller than its
										     siblings — not something this single-line row should ever do. -->
										<div style="margin-top:0.25rem; display:flex; align-items:flex-end; gap:0.35rem; flex-wrap:nowrap; overflow-x:auto; overflow-y:hidden;">
											<div id="fs-style-wrap" style="flex:0 0 auto;">
												<div class="toggle-row" style="gap:0.25rem; margin-top:0;">
													<button class="toggle-btn" id="btn-bold" style="font-weight:700; font-family:Georgia,serif; flex:none; width:1.5rem; padding:0.35rem 0;">B</button>
													<button class="toggle-btn" id="btn-italic" style="font-style:italic; font-family:Georgia,serif; flex:none; width:1.5rem; padding:0.35rem 0;">I</button>
												</div>
											</div>
											<div id="fs-size-wrap" style="flex:0 0 auto;">
												<div class="slider-header">
													<span class="slider-name">Font</span>
													<span class="slider-current" id="fs-display" style="display:none;">Medium</span>
												</div>
												<div class="slider-with-btns">
													<button class="slider-step-btn" data-for="fs-slider" data-dir="-1" style="width:18px;height:18px;flex-shrink:0;">−</button>
													<!-- Pete, 2026-10-26: "wanting to make letters larger, and it
													     stalls before I get large enough" — same hardcoded-ceiling
													     issue as the logo-size-slider (raised 80->150 earlier).
													     fontSize is % of the ZONE'S WIDTH (see label-configurator.js),
													     not an arbitrary unit, so 50 isn't a guess: on the 8oz zone
													     (width 3.75in, height 2.5in) a single line at 50% works out
													     to ~1.875in tall text (line-height ~2.34in) — right at the
													     zone's own height, the real ceiling for one line to still
													     fit. No server-side cap exists (rrt_label_draw_text() uses
													     the same %-of-zone-width formula with no clamp), so this
													     flows straight through to the real print file too. -->
													<input type="range" class="style-slider" id="fs-slider" min="2" max="50" value="5" step="0.5" style="width:32px;flex-shrink:0;">
													<button class="slider-step-btn" data-for="fs-slider" data-dir="1" style="width:18px;height:18px;flex-shrink:0;">+</button>
												</div>
											</div>
											<div id="fs-width-wrap" style="flex:0 0 auto;">
												<div class="slider-header">
													<span class="slider-name">Text Width</span>
												</div>
												<div class="slider-with-btns">
													<button class="slider-step-btn" data-for="box-side-margin" data-dir="-1" style="width:18px;height:18px;flex-shrink:0;">+</button>
													<input type="range" class="style-slider" id="box-side-margin" min="2" max="30" value="2" step="2" style="width:32px;flex-shrink:0;">
													<button class="slider-step-btn" data-for="box-side-margin" data-dir="1" style="width:18px;height:18px;flex-shrink:0;">−</button>
												</div>
											</div>
											<div id="shadow-color-row" class="toggle-row" style="gap:0.25rem; flex-shrink:0;">
												<button class="toggle-btn" data-shadow-color="white" style="padding:0.25rem 0.35rem; font-size:0.6rem; white-space:normal; line-height:1.15; text-align:center;">WHITE<br>TYPE</button>
												<button class="toggle-btn" data-shadow-color="black" style="padding:0.25rem 0.35rem; font-size:0.6rem; white-space:normal; line-height:1.15; text-align:center;">BLACK<br>TYPE</button>
											</div>
											<!-- 2026-10-21: "move the colorpicker up to that line as well" —
											     #text-color-row relocated up to the Text Group/Add/Remove/Shadow
											     row above (same change already made on TheUnframer's own upload
											     configurator). -->
										</div>

										<div class="font-drum-row">
										<div class="font-drum">
											<label class="radio-card">
												<input type="radio" name="quote-font" value="oswald">
												<div class="radio-card-body"><span class="radio-card-label">Bold Modern</span></div>
												<span class="font-preview oswald">Life is beautiful</span>
											</label>
											<label class="radio-card">
												<input type="radio" name="quote-font" value="luckiest">
												<div class="radio-card-body"><span class="radio-card-label">Luckiest Guy</span></div>
												<span class="font-preview luckiest">Life is beautiful</span>
											</label>
											<label class="radio-card">
												<input type="radio" name="quote-font" value="comic">
												<div class="radio-card-body"><span class="radio-card-label">Comic Balloon</span></div>
												<span class="font-preview comic">Life is beautiful</span>
											</label>
											<label class="radio-card">
												<input type="radio" name="quote-font" value="satisfy">
												<div class="radio-card-body"><span class="radio-card-label">Bold Script</span></div>
												<span class="font-preview satisfy">Life is beautiful</span>
											</label>
											<label class="radio-card">
												<input type="radio" name="quote-font" value="lobster">
												<div class="radio-card-body"><span class="radio-card-label">Lobster</span></div>
												<span class="font-preview lobster">Life is beautiful</span>
											</label>
											<label class="radio-card">
												<input type="radio" name="quote-font" value="bangers">
												<div class="radio-card-body"><span class="radio-card-label">Bangers Bold</span></div>
												<span class="font-preview bangers">Life is beautiful</span>
											</label>
											<label class="radio-card">
												<input type="radio" name="quote-font" value="fredoka">
												<div class="radio-card-body"><span class="radio-card-label">Rounded &amp; Fun</span></div>
												<span class="font-preview fredoka">Life is beautiful</span>
											</label>
											<label class="radio-card">
												<input type="radio" name="quote-font" value="montserrat">
												<div class="radio-card-body"><span class="radio-card-label">Clean Modern</span></div>
												<span class="font-preview montserrat">Life is beautiful</span>
											</label>
											<label class="radio-card">
												<input type="radio" name="quote-font" value="abril">
												<div class="radio-card-body"><span class="radio-card-label">Abril Fatface</span></div>
												<span class="font-preview abril">Life is beautiful</span>
											</label>
											<label class="radio-card">
												<input type="radio" name="quote-font" value="greatvibes">
												<div class="radio-card-body"><span class="radio-card-label">Great Vibes</span></div>
												<span class="font-preview greatvibes">Life is beautiful</span>
											</label>
											<label class="radio-card">
												<input type="radio" name="quote-font" value="sniglet">
												<div class="radio-card-body"><span class="radio-card-label">Sniglet</span></div>
												<span class="font-preview sniglet">Life is beautiful</span>
											</label>
											<label class="radio-card">
												<input type="radio" name="quote-font" value="arial">
												<div class="radio-card-body"><span class="radio-card-label">Clean &amp; Simple</span></div>
												<span class="font-preview arial">Life is beautiful</span>
											</label>
											<label class="radio-card">
												<input type="radio" name="quote-font" value="playfair">
												<div class="radio-card-body"><span class="radio-card-label">Classic Serif</span></div>
												<span class="font-preview playfair">Life is beautiful</span>
											</label>
											<label class="radio-card">
												<input type="radio" name="quote-font" value="scripty">
												<div class="radio-card-body"><span class="radio-card-label">Scripty</span></div>
												<span class="font-preview scripty">Life is beautiful</span>
											</label>
											<label class="radio-card">
												<input type="radio" name="quote-font" value="pacifico">
												<div class="radio-card-body"><span class="radio-card-label">Casual Script</span></div>
												<span class="font-preview pacifico">Life is beautiful</span>
											</label>
										</div>
										<button type="button" class="font-drum-rotator" aria-label="Show more fonts">
											<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
										</button>
										</div><!-- /font-drum-row -->

										<!-- placement radios kept for JS wiring — visually hidden -->
										<input type="radio" name="quote-placement" value="dropshadow" style="display:none;" checked>
										<div id="box-cards-wrap" style="display:none;">
											<input type="radio" name="quote-placement" value="whitebox">
											<input type="radio" name="quote-placement" value="blackbox">
										</div>
										<div id="bh-row" style="display:none;">
											<input type="range" id="bh-slider" style="display:none;" min="0" max="80" value="0" step="5">
											<input type="range" id="bw-slider" style="display:none;" min="15" max="100" value="90" step="5">
										</div>
										<button id="center-text-btn" type="button" style="display:none; width:100%; margin-top:0.25rem; padding:0.6rem 0.75rem; background:linear-gradient(to right,#e03020,#e67e22 25%,#e67e22 75%,#e03020); color:#fff; border:none; border-radius:8px; font-size:0.78rem; font-weight:700; cursor:pointer; font-family:'Poppins',sans-serif; line-height:1.3; text-align:center; text-transform:uppercase; box-shadow:-4px 0 14px rgba(220,40,20,0.75),4px 0 14px rgba(220,40,20,0.75),0 0 8px rgba(220,40,20,0.4);">TEXT OFF CENTER — TAP TO RECENTER</button>

										<p class="hint" style="display:none;">Drag text on preview · ↑↓←→ keys</p>

									</div><!-- /quote-options -->
								</div><!-- /tab-panel-quote -->

								<!-- LOGO PANEL -->
								<div class="text-tab-panel" id="tab-panel-logo">
									<input type="file" id="logo-file-input" accept="image/*,application/pdf,.pdf,.tif,.tiff,.psd,.eps,.ai" style="display:none;">

									<!-- Pete, 2026-10-17: "a library of holiday/birthday/celebration
									     art icons we can use to click like adding logos... on a drop down
									     box on the add logo line simply a button that says ART, we'll have
									     room if we make the remove button less wide" -> "dropdown is WAY
									     too wide" -> "we need to see the drop down art, not a description".
									     A native <select>'s <option> can ONLY show plain text, in every
									     browser — no way to put a thumbnail in one — so a real native
									     select could never satisfy "see the art". Replaced with a small
									     custom popover instead: #art-picker-btn toggles #art-picker-panel,
									     a grid of actual art thumbnails; clicking one adds that art piece
									     as a new logo-type layer and closes the panel (see the art-picker
									     wiring in label-configurator.js). Shrank logo-remove-btn's label
									     to just "×" to make room, and added the same nowrap+scroll safety
									     net as the other rows in this card so a tight fit scrolls instead
									     of wrapping to a 2nd line. -->
									<div id="logo-selector-row" style="display:flex; align-items:center; gap:0.3rem; flex-wrap:nowrap; overflow-x:auto; overflow-y:hidden; margin-bottom:0.25rem;">
										<button id="logo-upload-btn" type="button" style="font-size:0.65rem; padding:0.2rem 0.55rem; background:#991e21; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:700; font-family:'Poppins',sans-serif; white-space:nowrap; flex-shrink:0;">YOUR FILE</button>
										<!-- Pete, 2026-10-17 (follow-up): "can't look at art" / "circle with
										     arrows, page goes up and down" — two real bugs, now fixed. (1) The
										     panel had BOTH display:none and display:grid in the same style
										     attribute (a leftover authoring mistake — only display:none should
										     have been static, JS toggles the rest); last-declared wins, so it
										     was effectively stuck visible/invisible depending on how that got
										     parsed. (2) #logo-selector-row has overflow-y:hidden (added
										     earlier to kill a different stray-scrollbar bug) which CLIPS any
										     absolutely-positioned child that pops outside the row's own
										     height — exactly what this panel does — making it invisible even
										     when "open", and the panel's phantom off-screen size was dragging
										     the row's own scrollable area around under the cursor (the
										     "circle with arrows... page scrolls" sensation). Fixed by having
										     the JS move this panel to be a direct child of <body> with
										     position:fixed, positioned under the button at open-time — the
										     standard way a popover escapes a clipped/scrolling ancestor. -->
										<div id="art-picker" style="position:relative; flex-shrink:0; display:inline-flex;">
											<button id="art-picker-btn" type="button" style="font-size:0.65rem; padding:0.2rem 0.55rem; background:#991e21; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:700; font-family:'Poppins',sans-serif; white-space:nowrap; flex-shrink:0;">ART</button>
											<!-- Pete, 2026-10-17: "are there more we can add?" -> "more" — the
										     library grew from 6 to 27 pieces, so the panel now needs to
										     scroll (max-height + overflow-y:auto) instead of just growing
										     tall enough to run off-screen; widened to 4 columns to use the
										     space better. -->
										<?php
										/**
										 * Pete, 2026-10-25: "would be better
										 * if it was biggerer, with sliders
										 * as it is [unchanged], but the
										 * icons larger too" — panel and
										 * item buttons both scaled up
										 * (36px -> 50px icons, panel width/
										 * height grown to match).
										 */
										?>
										<div id="art-picker-panel" style="display:none; position:fixed; z-index:9999; background:#fff; border:1.5px solid #991e21; border-radius:8px; padding:0.5rem; box-shadow:0 4px 14px rgba(0,0,0,.25); grid-template-columns:repeat(4, 1fr); gap:0.45rem; width:240px; max-height:340px; overflow-y:auto;">
												<?php foreach ( rrt_label_art_library() as $art_key => $art ) : ?>
													<button type="button" class="art-picker-item" data-art-key="<?php echo esc_attr( $art_key ); ?>" title="<?php echo esc_attr( $art['label'] ); ?>" style="border:1px solid #e3ddd2; border-radius:6px; padding:3px; background:#fff; cursor:pointer; width:50px; height:50px;">
														<img src="<?php echo esc_url( RRT_LABEL_URL . $art['file'] ); ?>" alt="<?php echo esc_attr( $art['label'] ); ?>" style="width:100%; height:100%; object-fit:contain; display:block; pointer-events:none;">
													</button>
												<?php endforeach; ?>
												<?php
												/**
												 * Pete, 2026-10-26: "donate their image for others to
												 * use... so it immediately goes to our library" —
												 * customer-donated art (rrt_label_community_art(),
												 * real Media Library attachments), merged into this
												 * same grid with a small section divider. Only
												 * rendered once at least one donated piece exists, so
												 * a fresh site never shows an empty "Community"
												 * heading. Same .art-picker-item markup/classes as the
												 * curated art above, so all existing click/drag/resize
												 * handling in label-configurator.js just works
												 * unchanged — it only ever looks at data-art-key.
												 */
												$rrt_community_art = rrt_label_community_art();
												if ( ! empty( $rrt_community_art ) ) :
													?>
													<div style="grid-column:1/-1; font-size:0.55rem; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; color:#991e21; border-top:1px solid #e3ddd2; padding-top:0.35rem; margin-top:0.1rem;">Community</div>
													<?php foreach ( $rrt_community_art as $art_key => $art ) : ?>
														<button type="button" class="art-picker-item" data-art-key="<?php echo esc_attr( $art_key ); ?>" title="<?php echo esc_attr( $art['label'] ); ?>" style="border:1px solid #e3ddd2; border-radius:6px; padding:3px; background:#fff; cursor:pointer; width:50px; height:50px;">
															<img src="<?php echo esc_url( $art['file'] ); ?>" alt="<?php echo esc_attr( $art['label'] ); ?>" style="width:100%; height:100%; object-fit:contain; display:block; pointer-events:none;">
														</button>
													<?php endforeach; ?>
												<?php endif; ?>
											</div>
										</div>
										<div id="logo-selector-btns" style="display:flex; gap:0.3rem; flex-wrap:nowrap;"></div>
										<button id="logo-add-btn" type="button" style="font-size:0.65rem; padding:0.2rem 0.45rem; background:#991e21; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:700; font-family:'Poppins',sans-serif; flex-shrink:0;">+ Add</button>
										<button id="logo-remove-btn" type="button" title="Remove" style="font-size:0.65rem; padding:0.2rem 0.4rem; background:#e53e3e; color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:700; font-family:'Poppins',sans-serif; flex-shrink:0;">×</button>
										<button class="slider-step-btn" data-for="logo-size-slider" data-dir="-1" style="width:22px;height:22px;flex-shrink:0;">−</button>
										<?php
									/**
									 * Pete, 2026-10-25: "art, or my files
									 * don't go quite as large as I'd
									 * like" — max raised from 80 to 150
									 * (% of zone width). No other cap
									 * exists anywhere in the chain: GD's
									 * dest_w = (scale/100)*zone_width in
									 * rrt_label_draw_logo() has no
									 * hardcoded ceiling, so this slider
									 * was the only thing actually limiting
									 * how big a logo/art piece could get.
									 */
									?>
									<input type="range" id="logo-size-slider" class="style-slider" min="5" max="150" value="20" step="5" style="width:44px; flex-shrink:0;">
										<button class="slider-step-btn" data-for="logo-size-slider" data-dir="1" style="width:22px;height:22px;flex-shrink:0;">+</button>
									</div>
									<span id="logo-filename" style="font-size:0.6rem; color:#888; font-style:italic; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:100%; display:block; margin-bottom:0.25rem;"></span>
									<!-- Pete, 2026-10-26: "donate their image for others to use... a
									     very stripped down copyright image thing" — only shown for a
									     real upload (JS hides it whenever the active logo is an ART-
									     library pick, see loadLogoIntoControls() in
									     label-configurator.js); unchecked by default, same opt-in
									     spirit as every other per-layer toggle in this file. -->
									<label id="donate-row" style="display:none; align-items:flex-start; gap:0.35rem; font-size:0.65rem; color:#555; font-family:'Poppins',sans-serif; line-height:1.35; margin:0.15rem 0 0.1rem; cursor:pointer;">
										<input type="checkbox" id="logo-donate-checkbox" style="margin-top:0.15rem; flex-shrink:0;">
										<span>Donate this image to our Community Art Library so other customers can use it too (no tip needed) — submitted anonymously. By checking this box, I confirm I own this image or have permission to share it. <a href="<?php echo esc_url( home_url( '/community-standards/' ) ); ?>" target="_blank" rel="noopener" style="color:#991e21; font-weight:600;">See our Community Standards &rarr;</a></span>
									</label>
									<!-- Pete, 2026-10-26: "keep art donators as anonymous as
									     possible, unless the user wants to be known, then let's
									     give them a signup link for theunframer.com" — donations
									     here stay fully anonymous (no name/email ever stored
									     against the file, see rrt_label_donate_to_community_library()
									     in rrt-label-configurator.php); anyone who DOES want credit
									     is pointed at TheUnframer's own real creator system instead
									     of building a second, half-finished identity/attribution
									     system here. Only shown once the donate box above is
									     checked (toggled in label-configurator.js). -->
									<p id="donate-credit-row" style="display:none; font-size:0.65rem; color:#555; font-family:'Poppins',sans-serif; line-height:1.35; margin:0 0 0.4rem;">Want credit for your work instead? <a href="https://theunframer.com/creator-apply/" target="_blank" rel="noopener" style="color:#991e21; font-weight:600;">Become a Curated-Pro&trade; creator on TheUnframer &rarr;</a></p>
									<p class="hint" style="display:none;">Drag logo on preview to position</p>
								</div><!-- /tab-panel-logo -->

							</div><!-- /text-layers-wrap -->
						</div><!-- /config-section -->
					</div><!-- /quote-module-card -->
