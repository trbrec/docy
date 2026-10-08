;(function($){
    'use strict';
    $(document).ready(function(){    
        $('.docy-notice .notice-dismiss').on('click', function(){ 
            $('.docy-notice').slideUp('fast');
        });
        
        // =====================================================================
        // NEW PAGE: License Verification Form (verify-docy-license-form)
        // =====================================================================
        $(document).on("submit", "#verify-docy-license-form", function (e) {
            e.preventDefault();

            const $form = $(this);
            const $button = $form.find('#toggle-license-button');
            const code  = $form.find('input[name="docy_purchase_code"]').val();
            const nonce = $form.find('input[name="nonce"]').val();
            
            // Only verify action from this form now - reset handled via modal
            const action = 'docy_verify_purchase';

            // Add loading state
            $button.addClass('loading').prop('disabled', true);
            $form.find("#show-result").removeClass('success error').html("<p>Verifying license, please wait...</p>").fadeIn(200);

            $.ajax({
                url: docy_admin_object.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: { action, code, nonce },
                success: function (res) {
                    $button.removeClass('loading').prop('disabled', false);
                    
                    if (res.success) {
                        $form.find("#show-result").addClass('success').html('<p>' + res.data.message + '</p>');
                        // Reload page after successful verification to show the verified state
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        $form.find("#show-result").addClass('error').html('<p>' + res.data.message + '</p>');
                    }
                },
                error: function () {
                    $button.removeClass('loading').prop('disabled', false);
                    $form.find("#show-result").addClass('error').html("<p>Connection failed! Please try again.</p>");
                }
            });
        });

        // =====================================================================
        // Platform tabs (ThemeForest purchase code vs spider-themes.net key)
        // =====================================================================
        $(document).on('click', '.docy-license-tab', function () {
            const $tab = $(this);
            const target = $tab.data('tab');

            $tab.addClass('active').attr('aria-selected', 'true')
                .siblings().removeClass('active').attr('aria-selected', 'false');

            const $wrapper = $tab.closest('.docy-license-form-wrapper');
            $wrapper.find('.docy-license-panel').each(function () {
                const isMatch = $(this).data('panel') === target;
                $(this).toggleClass('active', isMatch).prop('hidden', !isMatch);
            });
        });

        // =====================================================================
        // spider-themes.net License Key Activation (Freemius)
        // =====================================================================
        $(document).on("submit", "#activate-docy-fs-form", function (e) {
            e.preventDefault();

            const $form   = $(this);
            const $button = $form.find('#activate-fs-button');
            const license_key = $form.find('input[name="docy_license_key"]').val();
            const nonce   = $form.find('input[name="nonce"]').val();

            $button.addClass('loading').prop('disabled', true);
            $form.find("#fs-show-result").removeClass('success error').html("<p>Activating license, please wait...</p>").fadeIn(200);

            $.ajax({
                url: docy_admin_object.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: { action: 'docy_activate_fs_license', license_key, nonce },
                success: function (res) {
                    $button.removeClass('loading').prop('disabled', false);

                    if (res.success) {
                        $form.find("#fs-show-result").addClass('success').html('<p>' + res.data.message + '</p>');
                        // Reload to render the verified state from PHP.
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        $form.find("#fs-show-result").addClass('error').html('<p>' + res.data.message + '</p>');
                    }
                },
                error: function () {
                    $button.removeClass('loading').prop('disabled', false);
                    $form.find("#fs-show-result").addClass('error').html("<p>Connection failed! Please try again.</p>");
                }
            });
        });

        // =====================================================================
        // NEW PAGE: License Reset/Deactivation with Modal Confirmation
        // =====================================================================
        const $resetModal = $('#docy-reset-modal');
        
        // Open reset modal
        $(document).on('click', '#docy-reset-license-btn', function(e) {
            e.preventDefault();
            $resetModal.fadeIn(300);
        });

        // Close modal on overlay click or cancel button
        $(document).on('click', '.docy-modal-overlay, .docy-modal-cancel', function() {
            $resetModal.fadeOut(200);
        });

        // Confirm license reset
        $(document).on('click', '#docy-confirm-reset', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const nonce = $('#verify-docy-license-form input[name="nonce"]').val() || 
                          $('input[name="nonce"]').val();

            $button.addClass('loading').prop('disabled', true);

            $.ajax({
                url: docy_admin_object.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: { 
                    action: 'docy_reset_purchase', 
                    nonce: nonce 
                },
                success: function(res) {
                    $button.removeClass('loading').prop('disabled', false);
                    
                    if (res.success) {
                        // Reload page to show unverified state
                        window.location.reload();
                    } else {
                        alert(res.data.message || 'Failed to reset license.');
                        $resetModal.fadeOut(200);
                    }
                },
                error: function() {
                    $button.removeClass('loading').prop('disabled', false);
                    alert('Connection failed! Please try again.');
                    $resetModal.fadeOut(200);
                }
            });
        });

        // Close modal on Escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $resetModal.is(':visible')) {
                $resetModal.fadeOut(200);
            }
        });

        // =====================================================================
        // Copy to Clipboard Functionality
        // =====================================================================
        $(document).on('click', '.docy-copy-btn', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const textToCopy = $button.data('copy');
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textToCopy).then(function() {
                    $button.addClass('copied');
                    $button.find('.dashicons').removeClass('dashicons-clipboard').addClass('dashicons-yes');
                    
                    setTimeout(function() {
                        $button.removeClass('copied');
                        $button.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-clipboard');
                    }, 2000);
                });
            } else {
                // Fallback for older browsers
                const $temp = $('<input>');
                $('body').append($temp);
                $temp.val(textToCopy).select();
                document.execCommand('copy');
                $temp.remove();
                
                $button.addClass('copied');
                setTimeout(function() {
                    $button.removeClass('copied');
                }, 2000);
            }
        });

        // =====================================================================
        // LEGACY SUPPORT: Old page form handling (st-theme-register-form)
        // Keep for backwards compatibility with old dashboard.php template
        // =====================================================================
        $(document).on("submit", ".st-theme-register-form", function (e) {
            e.preventDefault();

            const $form = $(this);
            const code  = $form.find('input[name="docy_purchase_code"]').val();
            const nonce = $form.find('input[name="nonce"]').val();
            const action = ($("#toggle-license-button").text().toLowerCase().includes('reset')) 
                            ? 'docy_reset_purchase' : 'docy_verify_purchase';

            $("#show-result").stop(true, true).show().html("<p>Processing, please wait...</p>");
            $form.find(".docy-verify-preloader").fadeIn(200);

            $.ajax({
                url: docy_admin_object.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: { action, code, nonce },
                success: function (res) {
                    $form.find(".docy-verify-preloader").fadeOut(200);
                    $("#show-result").stop(true, true).show().html('<p>' + res.data.message + '</p>');
                    setTimeout(() => $("#show-result").fadeOut(), 3000);
                    
                    if (!res.success) {
                        $('.st-box-head').removeClass('success').addClass('failed').html('<h4>' + res.data.html + '</h4>');
                        return;
                    }

                    if (action === 'docy_verify_purchase') {
                        $("#toggle-license-button").html('Reset License <span class="docy-verify-preloader"></span>');
                        $('.st-box-head').removeClass('failed').addClass('success').html('<h4>' + res.data.html + '</h4>');
                    } else {
                        $("#toggle-license-button").html('Verify License <span class="docy-verify-preloader"></span>');
                        $('.st-box-head').removeClass('success failed').html('<h4>' + res.data.html + '</h4>');
                        $form.find('input[name="docy_purchase_code"]').val('');
                    }
                },
                error: function () {
                    $form.find(".docy-verify-preloader").fadeOut(200);
                    $("#show-result").show().html("<p>Connection failed! Try again.</p>");
                    $('.st-box-head').removeClass('success').addClass('failed').html('<h4>Connection Error</h4>');
                }
            });
        });
        
    });
})(jQuery);