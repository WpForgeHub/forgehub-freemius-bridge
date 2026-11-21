/**
 * WPFH Freemius Bridge Frontend Script
 * 100% generic: reads all URLs, currency, logo, and variation IDs from DOM & wpfh_fb_config.
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		var cfg = (typeof wpfh_fb_config !== 'undefined') ? wpfh_fb_config : {};
		var checkoutUrl = cfg.checkout_url || '/checkout/';
		var downloadsUrl = cfg.downloads_url || '/my-account/downloads/';
		var checkoutLogo = cfg.checkout_logo || '';
		var siteName = cfg.site_name || '';
		var currency = $('#wpfh-pricing-bridge').data('currency') || cfg.currency_symbol || '$';
		var activeCycle = $('#wpfh-instant-buy-btn').attr('data-cycle') || 'annual';

		function buildCheckoutUrl(varId, extraParams) {
			var sep = (checkoutUrl.indexOf('?') === -1) ? '?' : '&';
			var url = checkoutUrl + sep + 'add-to-cart=' + encodeURIComponent(varId);
			if (extraParams) {
				$.each(extraParams, function(k, v) {
					if (v !== undefined && v !== null && String(v) !== '') {
						url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(v);
					}
				});
			}
			return url;
		}

		function syncSelectedProVariation() {
			var buyBtn = $('#wpfh-instant-buy-btn');
			var selectedOpt = $('.wpfh-plan-opt.selected');
			if (!selectedOpt.length || selectedOpt.data('plan-type') !== 'pro') {
				return;
			}
			var varId = (activeCycle === 'lifetime')
				? (selectedOpt.attr('data-var-lifetime') || selectedOpt.attr('data-var-annual') || selectedOpt.attr('data-var-id'))
				: (selectedOpt.attr('data-var-annual') || selectedOpt.attr('data-var-lifetime') || selectedOpt.attr('data-var-id'));
			var pricingId = (activeCycle === 'lifetime')
				? (selectedOpt.attr('data-lifetime-pricing') || selectedOpt.attr('data-pricing-id') || '')
				: (selectedOpt.attr('data-annual-pricing') || selectedOpt.attr('data-pricing-id') || '');
			var licenses = selectedOpt.attr('data-licenses') || '1';

			buyBtn.attr('data-pro-id', varId || '');
			buyBtn.attr('data-var-id', varId || '');
			buyBtn.attr('data-pricing-id', pricingId);
			buyBtn.attr('data-licenses', licenses);
		}

		// Toggle Billing Cycle on 4-tier grid and shortcode cards
		$('.wpfh-pricing-bridge .wpfh-cycle-btn').on('click', function() {
			var cycle = $(this).data('cycle');
			var wrap = $(this).closest('.wpfh-pricing-bridge');
			wrap.find('.wpfh-cycle-btn').removeClass('active').css({ 'background': 'transparent', 'color': '#94a3b8' });
			$(this).addClass('active').css({ 'background': '#2563eb', 'color': '#fff' });
			activeCycle = cycle;

			// Update WooCommerce product pricing cards
			wrap.find('.wpfh-plan-opt[data-plan-type="pro"]').each(function() {
				var opt = $(this);
				var price = (activeCycle === 'lifetime')
					? (opt.attr('data-lifetime') || opt.attr('data-annual'))
					: (opt.attr('data-annual') || opt.attr('data-lifetime'));
				var suffix = (activeCycle === 'lifetime')
					? '<span style="font-size:11px;color:#94a3b8;"> lifetime</span>'
					: '<span style="font-size:11px;color:#94a3b8;">/yr</span>';
				opt.find('.wpfh-price-val').html(currency + price + suffix);
			});

			// Update shortcode tier cards if present
			wrap.find('.wpfh-tier-card').each(function() {
				var card = $(this);
				var price = (activeCycle === 'lifetime') ? card.attr('data-lifetime') : card.attr('data-annual');
				if (price) {
					card.find('.wpfh-amount').text(price);
					card.find('.wpfh-period').text(activeCycle === 'lifetime' ? 'lifetime' : '/ year');
				}
			});

			$('#wpfh-instant-buy-btn').attr('data-cycle', cycle);
			syncSelectedProVariation();
			updateBuyButtonText();
		});

		function updateBuyButtonText() {
			var buyBtn = $('#wpfh-instant-buy-btn');
			if (!buyBtn.length) {
				return;
			}
			var planType = buyBtn.attr('data-plan-type') || 'pro';
			var cycle = buyBtn.attr('data-cycle') || 'annual';
			var selectedOpt = $('.wpfh-plan-opt.selected');

			if (planType === 'free') {
				buyBtn.find('.wpfh-btn-text').text('Get Free Edition (' + currency + '0 — Instant Order)');
				buyBtn.css({
					'background': 'linear-gradient(135deg, #10b981, #059669)',
					'box-shadow': '0 4px 14px 0 rgba(16, 185, 129, 0.4)'
				});
				$('#wpfh-guarantee-text').text('100% Free Forever • Instant Account Provisioning • Direct Access in Member Panel');
			} else {
				var price = (cycle === 'lifetime')
					? (selectedOpt.attr('data-lifetime') || selectedOpt.attr('data-annual') || '')
					: (selectedOpt.attr('data-annual') || selectedOpt.attr('data-lifetime') || '');
				var period = (cycle === 'lifetime') ? 'lifetime' : 'yr';
				var label = 'Proceed to Pro Checkout (' + currency + price + '/' + period + ')';
				buyBtn.find('.wpfh-btn-text').text(label);
				buyBtn.css({
					'background': 'linear-gradient(135deg, #2563eb, #1d4ed8)',
					'box-shadow': '0 4px 14px 0 rgba(37, 99, 235, 0.4)'
				});
				$('#wpfh-guarantee-text').text('14-Day Money-Back Guarantee • Instant License Delivery • 256-Bit SSL Encryption');
			}
		}

		// Plan card selection click
		$('.wpfh-plan-opt').on('click', function() {
			var opt = $(this);
			var planType = opt.data('plan-type');
			var buyBtn = $('#wpfh-instant-buy-btn');

			$('.wpfh-plan-opt').removeClass('selected').css({ 'border-color': '#334155', 'box-shadow': 'none' });
			opt.addClass('selected');

			if (planType === 'free') {
				opt.css({ 'border-color': '#10b981', 'box-shadow': '0 0 0 1px #10b981' });
				buyBtn.attr('data-plan-type', 'free');
				buyBtn.attr('data-var-id', opt.attr('data-var-id') || buyBtn.attr('data-free-id') || '');
			} else {
				opt.css({ 'border-color': '#2563eb', 'box-shadow': '0 0 0 1px #2563eb' });
				buyBtn.attr('data-plan-type', 'pro');
				syncSelectedProVariation();
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
				if (!freeId) {
					return;
				}
				btn.find('.wpfh-btn-text').text('Preparing Your Order...');
				window.location.href = buildCheckoutUrl(freeId);
				return;
			}

			// Pro Flow
			var proId       = btn.attr('data-pro-id') || btn.attr('data-var-id');
			var pluginId    = btn.attr('data-plugin-id') || '';
			var publicKey   = btn.attr('data-public-key') || '';
			var planId      = btn.attr('data-plan-id') || '';
			var pricingId   = btn.attr('data-pricing-id') || '';
			var cycle       = btn.attr('data-cycle') || 'annual';
			var licenses    = btn.attr('data-licenses') || '1';
			var productName = btn.attr('data-product-name') || siteName || 'Pro License';

			// If user is already logged in on site, open Freemius checkout modal directly with prefilled user data.
			if (cfg.is_user_logged && cfg.user_email && pluginId && typeof FS !== 'undefined' && FS.Checkout) {
				var fsConfig = {
					plugin_id:      pluginId,
					plan_id:        planId,
					billing_cycle:  cycle,
					public_key:     publicKey,
					user_email:     cfg.user_email,
					user_firstname: cfg.user_firstname || cfg.user_name || '',
					user_lastname:  cfg.user_lastname || '',
					user_country:   cfg.user_country || ''
				};
				if (pricingId) {
					fsConfig.pricing_id = pricingId;
				}
				if (checkoutLogo) {
					fsConfig.image = checkoutLogo;
				}
				if (cfg.modal_params && typeof cfg.modal_params === 'object') {
					$.extend(fsConfig, cfg.modal_params);
				}
				var handler = FS.Checkout.configure(fsConfig);
				handler.open({
					name: productName,
					licenses: licenses,
					success: function() {
						var sep = (downloadsUrl.indexOf('?') === -1) ? '?' : '&';
						window.location.href = downloadsUrl + sep + 'welcome=1';
					}
				});
				return;
			}

			// If not logged in, add the exact WooCommerce variation to cart and go to checkout.
			if (!proId) {
				return;
			}
			btn.find('.wpfh-btn-text').text('Redirecting to Checkout...');
			window.location.href = buildCheckoutUrl(proId, {
				billing_cycle: cycle,
				pricing_id: pricingId,
				licenses: licenses
			});
		});

		// Shortcode Buy Button
		$('.wpfh-buy-btn').on('click', function(e) {
			e.preventDefault();
			var btn = $(this);
			var card = btn.closest('.wpfh-tier-card');
			var container = btn.closest('.wpfh-pricing-bridge');

			var pluginId    = container.attr('data-plugin-id') || '';
			var publicKey   = container.attr('data-public-key') || '';
			var planId      = container.attr('data-plan-id') || '';
			var productName = container.attr('data-product-name') || siteName || 'Pro License';
			var licenses    = btn.attr('data-licenses') || '1';
			var pricingId   = (activeCycle === 'annual') ? (card.attr('data-annual-pricing') || '') : (card.attr('data-lifetime-pricing') || '');

			if (pluginId && typeof FS !== 'undefined' && FS.Checkout) {
				var fsConfig = {
					plugin_id:     pluginId,
					public_key:    publicKey,
					plan_id:       planId,
					licenses:      licenses,
					billing_cycle: activeCycle
				};
				if (pricingId) {
					fsConfig.pricing_id = pricingId;
				}
				if (checkoutLogo) {
					fsConfig.image = checkoutLogo;
				}
				if (cfg.modal_params && typeof cfg.modal_params === 'object') {
					$.extend(fsConfig, cfg.modal_params);
				}
				var handler = FS.Checkout.configure(fsConfig);
				handler.open({
					name: productName,
					licenses: licenses,
					success: function() {
						var sep = (downloadsUrl.indexOf('?') === -1) ? '?' : '&';
						window.location.href = downloadsUrl + sep + 'welcome=1';
					}
				});
			}
		});
	});
})(jQuery);
