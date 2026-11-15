/**
 * WPFH Freemius Bridge Frontend Script
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		var activeCycle = 'annual';

		// Toggle Billing Cycle on 3-tier grid
		$('.wpfh-pricing-bridge .wpfh-cycle-btn').on('click', function() {
			var cycle = $(this).data('cycle');
			$('.wpfh-cycle-btn').removeClass('active');
			$(this).addClass('active');
			activeCycle = cycle;

			// Update 3-tier cards if present
			$('.wpfh-tier-card').each(function() {
				var card = $(this);
				var price = (activeCycle === 'annual') ? card.data('annual') : card.data('lifetime');
				var period = (activeCycle === 'annual') ? '/ year' : ' one-time';
				card.find('.wpfh-amount').text(price);
				card.find('.wpfh-period').text(period);
			});

			// Update 4-tier plan options if present
			$('.wpfh-plan-opt[data-plan-type="pro"]').each(function() {
				var opt = $(this);
				var price = (activeCycle === 'lifetime') ? opt.data('lifetime') : opt.data('annual');
				var suffix = (activeCycle === 'lifetime') ? '<span style="font-size:11px;color:#94a3b8;"> lifetime</span>' : '<span style="font-size:11px;color:#94a3b8;">/yr</span>';
				opt.find('.wpfh-price-val').html('$' + price + suffix);
			});

			$('#wpfh-instant-buy-btn').attr('data-cycle', cycle);
		});

		// 4-tier plan selection click
		$('.wpfh-plan-opt').on('click', function() {
			var opt = $(this);
			var planType = opt.data('plan-type');
			var buyBtn = $('#wpfh-instant-buy-btn');
			var guaranteeText = $('#wpfh-guarantee-text');

			$('.wpfh-plan-opt').removeClass('selected').css('border-color', '#334155');
			opt.addClass('selected');

			if (planType === 'free') {
				opt.css('border-color', '#10b981');
				buyBtn.attr('data-plan-type', 'free');
				buyBtn.find('.wpfh-btn-text').text('📥 Get Free Edition ($0 — Instant Download)');
				buyBtn.css({
					'background': 'linear-gradient(135deg, #10b981, #059669)',
					'box-shadow': '0 4px 14px 0 rgba(16, 185, 129, 0.4)'
				});
				guaranteeText.text('✓ 100% Free Forever • Instant Zip Download • Automatic Member Account');
			} else {
				opt.css('border-color', '#2563eb');
				var pricingId = opt.data('pricing-id');
				buyBtn.attr('data-plan-type', 'pro');
				buyBtn.attr('data-pricing-id', pricingId);
				buyBtn.find('.wpfh-btn-text').text('🔒 Secure Checkout via Freemius');
				buyBtn.css({
					'background': 'linear-gradient(135deg, #2563eb, #1d4ed8)',
					'box-shadow': '0 4px 14px 0 rgba(37, 99, 235, 0.4)'
				});
				guaranteeText.text('✓ 14-Day Money-Back Guarantee • Instant License Delivery • 256-Bit SSL Encryption');
			}
		});

		// Instant Buy Button Click (Product Page Bridge)
		$('#wpfh-instant-buy-btn').on('click', function(e) {
			e.preventDefault();
			var btn = $(this);
			var planType = btn.attr('data-plan-type') || 'pro';

			if (planType === 'free') {
				var freeId = btn.attr('data-free-id') || '44';
				btn.find('.wpfh-btn-text').text('Preparing Your Download...');
				window.location.href = '/checkout/?add-to-cart=' + freeId;
				return;
			}

			// Pro Freemius Checkout Modal
			var pluginId  = btn.attr('data-plugin-id') || '44985';
			var publicKey = btn.attr('data-public-key') || 'pk_862f10dd7623aebdb2cd2e4e4f840';
			var planId    = btn.attr('data-plan-id') || '79313';
			var pricingId = btn.attr('data-pricing-id') || '119784';
			var cycle     = btn.attr('data-cycle') || 'annual';

			if (typeof FS !== 'undefined' && FS.Checkout) {
				var handler = FS.Checkout.configure({
					plugin_id:     pluginId,
					plan_id:       planId,
					pricing_id:    pricingId,
					billing_cycle: cycle,
					public_key:    publicKey,
					image:         'https://wpforgehub.com/wp-content/themes/wpforgehub-theme/assets/images/wpfh-logo-192x192.png'
				});
				handler.open({
					name: 'WPForgeHub Pro License',
					success: function(response) {
						window.location.href = 'https://wpforgehub.com/my-account/downloads/';
					}
				});
			} else {
				var fallbackUrl = 'https://checkout.freemius.com/mode/dialog/plugin/' + pluginId + '/plan/' + planId + '/pricing/' + pricingId + '/?billing_cycle=' + cycle;
				window.open(fallbackUrl, '_blank');
			}
		});

		// 3-Tier Grid Buy Button
		$('.wpfh-buy-btn').on('click', function(e) {
			e.preventDefault();
			var btn = $(this);
			var card = btn.closest('.wpfh-tier-card');
			var container = btn.closest('.wpfh-pricing-bridge');

			var pluginId = container.data('plugin-id') || '44985';
			var publicKey = container.data('public-key') || 'pk_862f10dd7623aebdb2cd2e4e4f840';
			var planId = container.data('plan-id') || '79313';
			var licenses = btn.data('licenses');
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
						window.location.href = 'https://wpforgehub.com/my-account/downloads/';
					}
				});
			}
		});
	});
})(jQuery);
