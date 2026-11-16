/**
 * WPFH Freemius Bridge Frontend Script
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		var activeCycle = 'annual';

		// Toggle Billing Cycle on 3-tier and 4-tier grid
		$('.wpfh-pricing-bridge .wpfh-cycle-btn').on('click', function() {
			var cycle = $(this).data('cycle');
			$('.wpfh-cycle-btn').removeClass('active').css({ 'background': 'transparent', 'color': '#94a3b8' });
			$(this).addClass('active').css({ 'background': '#2563eb', 'color': '#fff' });
			activeCycle = cycle;

			// Update 4-tier plan options if present
			$('.wpfh-plan-opt[data-plan-type="pro"]').each(function() {
				var opt = $(this);
				var price = (activeCycle === 'lifetime') ? opt.data('lifetime') : opt.data('annual');
				var suffix = (activeCycle === 'lifetime') ? '<span style="font-size:11px;color:#94a3b8;"> lifetime</span>' : '<span style="font-size:11px;color:#94a3b8;">/yr</span>';
				opt.find('.wpfh-price-val').html('$' + price + suffix);
			});

			$('#wpfh-instant-buy-btn').attr('data-cycle', cycle);
			updateBuyButtonText();
		});

		function updateBuyButtonText() {
			var buyBtn = $('#wpfh-instant-buy-btn');
			var planType = buyBtn.attr('data-plan-type') || 'pro';
			var cycle = buyBtn.attr('data-cycle') || 'annual';
			var selectedOpt = $('.wpfh-plan-opt.selected');

			if (planType === 'free') {
				buyBtn.find('.wpfh-btn-text').text('📥 Get Free Edition ($0 — Instant Order)');
				buyBtn.css({
					'background': 'linear-gradient(135deg, #10b981, #059669)',
					'box-shadow': '0 4px 14px 0 rgba(16, 185, 129, 0.4)'
				});
				$('#wpfh-guarantee-text').text('✓ 100% Free Forever • Instant Account Provisioning • Direct Access in Member Panel');
			} else {
				var price = (cycle === 'lifetime') ? selectedOpt.data('lifetime') : selectedOpt.data('annual');
				var period = (cycle === 'lifetime') ? 'lifetime' : 'yr';
				var licenses = selectedOpt.data('licenses') || '1';
				var label = '🔒 Proceed to Pro Checkout ($' + (price || '19') + '/' + period + ')';
				buyBtn.find('.wpfh-btn-text').text(label);
				buyBtn.css({
					'background': 'linear-gradient(135deg, #2563eb, #1d4ed8)',
					'box-shadow': '0 4px 14px 0 rgba(37, 99, 235, 0.4)'
				});
				$('#wpfh-guarantee-text').text('✓ 14-Day Money-Back Guarantee • Instant License Delivery • 256-Bit SSL Encryption');
			}
		}

		// 4-tier plan selection click
		$('.wpfh-plan-opt').on('click', function() {
			var opt = $(this);
			var planType = opt.data('plan-type');
			var buyBtn = $('#wpfh-instant-buy-btn');

			$('.wpfh-plan-opt').removeClass('selected').css({ 'border-color': '#334155', 'box-shadow': 'none' });
			opt.addClass('selected');

			if (planType === 'free') {
				opt.css({ 'border-color': '#10b981', 'box-shadow': '0 0 0 1px #10b981' });
				buyBtn.attr('data-plan-type', 'free');
			} else {
				opt.css({ 'border-color': '#2563eb', 'box-shadow': '0 0 0 1px #2563eb' });
				var pricingId = opt.data('pricing-id');
				var licenses = opt.data('licenses') || '1';
				buyBtn.attr('data-plan-type', 'pro');
				buyBtn.attr('data-pricing-id', pricingId);
				buyBtn.attr('data-licenses', licenses);
			}

			updateBuyButtonText();
		});

		// Instant Buy Button Click (Product Page Bridge)
		$('#wpfh-instant-buy-btn').on('click', function(e) {
			e.preventDefault();
			var btn = $(this);
			var planType = btn.attr('data-plan-type') || 'pro';

			if (planType === 'free') {
				var freeId = btn.attr('data-free-id') || btn.attr('data-var-id');
				btn.find('.wpfh-btn-text').text('Preparing Your Order...');
				window.location.href = '/checkout/?add-to-cart=' + encodeURIComponent(freeId);
				return;
			}

			// Pro Flow
			var proId     = btn.attr('data-pro-id') || btn.attr('data-var-id');
			var pluginId  = btn.attr('data-plugin-id') || '44985';
			var publicKey = btn.attr('data-public-key') || 'pk_862f10dd7623aebdb2cd2e4e4f840';
			var planId    = btn.attr('data-plan-id') || '79313';
			var pricingId = btn.attr('data-pricing-id') || '119784';
			var cycle     = btn.attr('data-cycle') || 'annual';
			var licenses  = btn.attr('data-licenses') || '1';

			// If user is already logged in on site, open Freemius checkout modal directly with prefilled user data!
			if (typeof wpfh_fb_config !== 'undefined' && wpfh_fb_config.is_user_logged && wpfh_fb_config.user_email) {
				if (typeof FS !== 'undefined' && FS.Checkout) {
					var handler = FS.Checkout.configure({
						plugin_id:     pluginId,
						plan_id:       planId,
						pricing_id:    pricingId,
						billing_cycle: cycle,
						public_key:    publicKey,
						user_email:    wpfh_fb_config.user_email,
						user_firstname: wpfh_fb_config.user_firstname || wpfh_fb_config.user_name,
						user_lastname:  wpfh_fb_config.user_lastname || '',
						user_country:   wpfh_fb_config.user_country || '',
						image:         'https://wpforgehub.com/wp-content/themes/wpforgehub-theme/assets/images/wpfh-logo-192x192.png'
					});
					handler.open({
						name: 'WPForgeHub Pro License',
						licenses: licenses,
						success: function(response) {
							window.location.href = 'https://wpforgehub.com/my-account/downloads/?welcome=1';
						}
					});
					return;
				}
			}

			// If not logged in, take customer through frictionless checkout to collect Name, Email, Country and create order!
			btn.find('.wpfh-btn-text').text('Redirecting to Checkout...');
			window.location.href = '/checkout/?add-to-cart=' + encodeURIComponent(proId) + '&billing_cycle=' + encodeURIComponent(cycle) + '&pricing_id=' + encodeURIComponent(pricingId);
		});

		// 3-Tier Grid Buy Button (Fallback shortcode)
		$('.wpfh-buy-btn').on('click', function(e) {
			e.preventDefault();
			var btn = $(this);
			var card = btn.closest('.wpfh-tier-card');
			var container = btn.closest('.wpfh-pricing-bridge');

			var pluginId = container.data('plugin-id') || '44985';
			var publicKey = container.data('public-key') || 'pk_862f10dd7623aebdb2cd2e4e4f840';
			var planId = container.data('plan-id') || '79313';
			var licenses = btn.data('licenses') || 1;
			var pricingId = (activeCycle === 'annual') ? card.data('annual-pricing') : card.data('lifetime-pricing');

			if (typeof FS !== 'undefined' && FS.Checkout) {
				var handler = FS.Checkout.configure({
					plugin_id: pluginId,
					public_key: publicKey,
					plan_id: planId,
					pricing_id: pricingId,
					licenses: licenses,
					billing_cycle: activeCycle,
					image: 'https://wpforgehub.com/wp-content/themes/wpforgehub-theme/assets/images/wpfh-logo-mark.png'
				});
				handler.open({
					name: 'WPForgeHub Pro License',
					licenses: licenses,
					success: function(response) {
						window.location.href = 'https://wpforgehub.com/my-account/downloads/?welcome=1';
					}
				});
			}
		});
	});
})(jQuery);
