					<style>
						/* Hide ADD TEXT checkbox until quote is activated; show via CSS :checked so user can uncheck to remove layer */
						#quote-toggle { display:none; }
						#quote-toggle:checked { display:inline-block; }
						/* Winner: Option 4 + shimmer — scoped to unchecked (trigger) state only */
						@keyframes qtBounce {
							0%   { transform: scale(0.55); opacity: 0; }
							55%  { transform: scale(1.12); opacity: 1; }
							75%  { transform: scale(0.95); }
							100% { transform: scale(1);    opacity: 1; }
						}
						@keyframes qtShimmer {
							0%   { left: -70%; }
							100% { left: 120%; }
						}
						/* Trigger button (unchecked) */
						#quote-toggle:not(:checked) ~ #quote-toggle-label {
							font-size: 0.95rem !important;
							padding: 0.45rem 1.1rem !important;
							text-align: center;
							position: relative;
							overflow: hidden;
							animation: qtBounce 0.6s cubic-bezier(.36,.07,.19,.97) both;
						}
						#quote-toggle:not(:checked) ~ #quote-toggle-label::before { content: '✨ '; }
						#quote-toggle:not(:checked) ~ #quote-toggle-label::after {
							content: '';
							position: absolute;
							top: 0; left: -70%;
							width: 55%; height: 100%;
							background: linear-gradient(90deg, transparent, rgba(255,255,255,0.28), transparent);
							animation: qtShimmer 2.6s ease-in-out infinite;
							pointer-events: none;
						}
						/* Sub-toggles active: keep quote pill small + show its checkbox */
						label.sub-on #quote-toggle { display: inline-block !important; }
						label.sub-on #quote-toggle:not(:checked) ~ #quote-toggle-label {
							font-size: 0.85rem !important;
							padding: 0.3rem 0.85rem !important;
							animation: none !important;
							overflow: visible;
						}
						label.sub-on #quote-toggle:not(:checked) ~ #quote-toggle-label::before,
						label.sub-on #quote-toggle:not(:checked) ~ #quote-toggle-label::after { content: none; animation: none; }
						/* Active pill (checked) — matches CARTOON BALLOON / IMAGE FILE */
						#quote-toggle:checked ~ #quote-toggle-label {
							font-size: 0.85rem !important;
							padding: 0.3rem 0.85rem !important;
							text-align: center;
							overflow: visible;
							animation: none !important;
						}
						#quote-toggle:checked ~ #quote-toggle-label::before,
						#quote-toggle:checked ~ #quote-toggle-label::after { content: none; animation: none; }
						/* Font drum — snap-scroll reel */
						.font-drum-row {
							position: relative;
						}
						.font-drum {
							display: grid;
							grid-template-columns: 1fr;
							gap: 0.25rem;
							max-height: 4.8rem;
							overflow-y: auto;
							scroll-snap-type: y mandatory;
							scrollbar-width: none;
							margin-top: 0.5rem;
							border: 1.5px solid #d8a0d8;
							border-radius: 7px;
							padding: 0.2rem 2.8rem 0.2rem 0.2rem;
							box-shadow: inset 0 -14px 10px -10px rgba(142,0,121,0.18);
						}
						.font-drum .radio-card {
							scroll-snap-align: start;
							padding: 0.25rem 0.4rem;
							flex-direction: row;
							align-items: center;
							flex-wrap: nowrap;
							gap: 0.25rem;
						}
						.font-drum .radio-card-body { flex-shrink: 0; }
						.font-drum .radio-card-label { font-size: 0.85rem; font-weight: 800; white-space: nowrap; color: #991e21; }
						/* Rotator — big, obvious "click to see more fonts" affordance,
						   replacing the thin native scrollbar it overlays. Advances the
						   drum one row per click and wraps at the end (matches the
						   wheel/swipe behavior already wired in quote-module.js). */
						.font-drum-rotator {
							position: absolute;
							top: 50%;
							right: 6px;
							transform: translateY(-50%);
							width: 40px;
							height: 40px;
							display: flex;
							align-items: center;
							justify-content: center;
							background: #fff;
							border: 1.5px solid #991e21;
							border-radius: 8px;
							cursor: pointer;
							box-shadow: 0 1px 4px rgba(0,0,0,0.18);
							transition: background 0.15s, border-color 0.15s;
							z-index: 2;
						}
						.font-drum-rotator:hover { background: #f56820; border-color: #f56820; }
						.font-drum-rotator svg { width: 22px; height: 22px; color: #991e21; transition: color 0.15s; }
						.font-drum-rotator:hover svg { color: #fff; }
						@media (max-width: 768px) {
							.font-drum {
								display: flex;
								flex-direction: row;
								overflow-x: auto;
								overflow-y: hidden;
								scroll-snap-type: x mandatory;
								max-height: none;
								gap: 0.3rem;
								padding: 0.2rem 0.2rem 0.2rem;
							}
							.font-drum .radio-card {
								min-width: 52%;
								flex-shrink: 0;
								scroll-snap-align: start;
							}
							.font-drum .font-preview {
								padding-left: 0;
								text-align: left;
							}
							.font-drum-rotator { display: none; }
						}
						.font-drum::-webkit-scrollbar { display: none; }
						.font-drum .font-preview {
							display: block;
							flex: 1;
							min-width: 0;
							white-space: nowrap;
							overflow: hidden;
							text-overflow: ellipsis;
							font-size: 1.15rem !important;
							font-weight: 700;
							line-height: 1;
							color: #333;
							padding-left: 0.5rem;
							text-align: center;
						}
					</style>
