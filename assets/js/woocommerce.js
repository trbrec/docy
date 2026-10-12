/**
 * WooCommerce Functionality
 * All WooCommerce-related JavaScript consolidated in one place
 * 
 * This file is loaded conditionally only when WooCommerce plugin is active
 * 
 * Features included:
 * - Buy Now button functionality (simple and variable products)
 * - Cart quantity increment/decrement buttons
 * - Product single page interactions
 * 
 * @package Docy
 * @since 1.0.0
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        
        /**
         * Add to Cart Button Loader
         * Shows a loader when adding products to cart via AJAX
         */
        $(document).on('click', '.ajax_add_to_cart', function() {
            var $button = $(this);
            // Add loading class to show loader
            $button.addClass('loading');
        });
        
        // Remove loading class when product is added
        $(document).on('added_to_cart', function() {
            $('.ajax_add_to_cart.loading').removeClass('loading');
        });
        
        /**
         * Buy Now Button Functionality
         * Adds product to cart and redirects to checkout
         */

        function buyNow($button, data) {
            if ($button.data('buy-now-pending')) return;
            $button.data('buy-now-pending', true).prop('disabled', true).addClass('loading');
            function failed(response) {
                $button.data('buy-now-pending', false).prop('disabled', false).removeClass('loading');
                var message = response && response.data && response.data.message;
                alert(typeof message === 'string' && message ? message : 'Impossibile aggiungere il prodotto al carrello. Riprova.');
            }
            $.ajax({
                url: docy_buy_now_params.ajax_url,
                type: 'POST',
                dataType: 'json',
                timeout: 20000,
                data: data,
                success: function(response) {
                    if (response && response.success === true && response.data && typeof response.data.message === 'string') {
                        window.location.href = docy_buy_now_params.checkout_url;
                    } else {
                        failed(response);
                    }
                },
                error: function(xhr) { failed(xhr && xhr.responseJSON); }
            });
        }
        
        // Handle Buy Now for Simple Products
        $('.buy_now_btn:not(.buy_now_variable)').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var productId = $button.data('product-id');
            var $form = $button.closest('form.cart');
            var quantity = $form.find('input.qty').val() || 1;
            
            buyNow($button, {
                action: 'docy_buy_now_add_to_cart',
                product_id: productId,
                quantity: quantity,
                nonce: docy_buy_now_params.nonce
            });
        });
        
        // Handle Buy Now for Variable Products
        $('.buy_now_variable').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            if ($button.data('buy-now-pending')) return;
            var $form = $button.closest('form.variations_form');
            var productId = $form.find('input[name="product_id"]').val();
            var variationId = $form.find('input[name="variation_id"]').val();
            var quantity = $form.find('input.qty').val() || 1;
            
            // Check if variation is selected
            if (!variationId || variationId === '0') {
                alert('Seleziona le opzioni del prodotto prima di acquistare.');
                return;
            }
            
            // Get variation data
            var variationData = {};
            $form.find('select[name^="attribute_"]').each(function() {
                var attributeName = $(this).attr('name');
                variationData[attributeName] = $(this).val();
            });
            
            // Add to cart via AJAX
            buyNow($button, {
                action: 'docy_buy_now_add_to_cart',
                product_id: productId,
                quantity: quantity,
                variation_id: variationId,
                variation: variationData,
                nonce: docy_buy_now_params.nonce
            });
        });
        
        
        /**
         * Cart Quantity Update Buttons
         * Increment and decrement quantity in product single page and cart page
         */
        
        $(document).on('click', '.ar_top, .ar_down', function () {
            var input = $(this).closest('.product-qty').find('input.qty')[0];
            if (!input || input.disabled || input.readOnly || input.type === 'hidden') return;
            var previous = input.value;
            var increasing = $(this).hasClass('ar_top');
            if (input.type === 'number' && input.step !== 'any') {
                try { increasing ? input.stepUp() : input.stepDown(); } catch (error) { return; }
            } else {
                var value = Number(input.value);
                var step = Number(input.step) || 1;
                if (!Number.isFinite(value) || step <= 0) return;
                var next = Number((value + (increasing ? step : -step)).toFixed(10));
                if (input.min !== '') next = Math.max(next, Number(input.min));
                if (input.max !== '') next = Math.min(next, Number(input.max));
                input.value = String(next);
            }
            if (input.value !== previous) $(input).trigger('input').trigger('change');
        });
        
    });
    
})(jQuery);
