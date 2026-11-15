/**
 * WPFH Freemius Bridge Frontend Script
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		var activeCycle = 'annual';

		// Toggle Billing Cycle
		$('.wpfh-cycle-btn').on('click', function() {
			$('.wpfh-cycle-btn').removeClass('active');
			$(this).addClass('active');
			activeCycle = $(this).data('cycle');

			$('.wpfh-tier-card').each(function() {
				var card = $(this);
				var price = (activeCycle === 'annual') ? card.data('annual') : card.data('lifetime');
				var period = (activeCycle === 'annual') ? '/ year' : ' one-time';

				card.find('.wpfh-amount').text(price);
				card.find('.wpfh-period').text(period);
			});
		});

		// Launch Freemius Checkout Modal
		$('.wpfh-buy-btn').on('click', function(e) {
			e.preventDefault();
			var btn = $(this);
			var card = btn.closest('.wpfh-tier-card');
			var container = btn.closest('.wpfh-pricing-bridge');

			var pluginId = container.data('plugin-id') || '44985';
			var publicKey = container.data('public-key') || 'pk_f9a888c3be313c0bbfbf4a627ad2b';
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
					user_email: (typeof wpfh_fb_config !== 'undefined') ? wpfh_fb_config.user_email : '',
					user_name: (typeof wpfh_fb_config !== 'undefined') ? wpfh_fb_config.user_name : '',
					success: function(response) {
						window.location.href = 'https://wpforgehub.com/my-account/downloads/';
					}
				});
			} else {
				console.warn('Freemius Checkout SDK not loaded.');
			}
		});
	});
})(jQuery);
