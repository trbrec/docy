;(function ($) {
    "use strict";

    /**
     * Registration Form
     */
    if (jQuery(".registerform").length) {
        jQuery(".registerform").on("submit", function (e) {
            e.preventDefault();
            let ajax_url = docy_local_object.ajaxurl;
            jQuery.post(
                ajax_url,
                {
                    data: jQuery(this).serialize(),
                    action: "dt_custom_registration_form",
                },
                function (res) {
                    jQuery("#reg-form-validation-messages").html(res.data.message);
                }
            );
            return false;
        });
    }

    // Search widget
    var post_type_modified  = docy_local_object.post_type_modified;
    let activePostType      = 'all';
    let searchTimeout;
    let currentSearchTerm   = '';
    let currentRequest      = null; // Tracks the in-flight request so newer keystrokes can cancel it.

    function docySearchTabs() {
        let tabsHtml = `<div data-type="all" class="tab-item active">All</div>`;
        for (const [type, label] of Object.entries(post_type_modified)) {
            const cleanedLabel = label.replace(/[-_]+/g, ' ').replace(/[^a-zA-Z0-9\s]/g, '').trim();
            const postTypeName = cleanedLabel.charAt(0).toUpperCase() + cleanedLabel.slice(1);
            tabsHtml += `<div data-type="${type}" class="tab-item">${postTypeName}</div>`;
        }
        $('#search-tabs').html(tabsHtml);
    }
    
    function displayResults(postType, searchTerm) {
        $('#search-preloader').show();
        const $input = $('#searchInput');
        const $form = $input.closest('form');
        const $spinner = $form.find('.search-spinner');

        // Abort any request still in flight so a slower, older response can't
        // overwrite results for the term the user is currently typing.
        if (currentRequest) {
            currentRequest.abort();
        }

        currentRequest = $.ajax({
            url: docy_local_object.ajaxurl,
            method: 'POST',
            data: {
                action: 'ajax_search',
                post_type: postType !== 'all' ? postType : docy_local_object.post_types,
                keyword: searchTerm,
                security: docy_local_object.ajax_nonce
            },
            beforeSend: function () {
                $form.addClass('searching');
                $spinner.show();
                $(".spinner").css("display", "block");
                $('#search-results').attr('aria-busy', 'true');
                $('#search-results').append('<div id="search-preloader"></div>');
            },
            success: function (response) {
                $('#docy-search-result').addClass('ajax-search');
                $('#search-results').html(response ? response : `<h5 class="error">${$('#docy-search-result').data('noresult')}</h5>`);
                $form.removeClass('searching');
                $spinner.hide();
                $(".spinner").hide();
                $('#search-results').attr('aria-busy', 'false');
            },
            error: function (jqXHR, textStatus) {
                // A request we aborted on purpose is not a real error.
                if (textStatus === 'abort') {
                    return;
                }
                $form.removeClass('searching');
                $spinner.hide();
                $(".spinner").hide();
                $('#search-results').attr('aria-busy', 'false');
            },
            complete: function () {
                currentRequest = null;
            }
        });
    }

    $('div.tab-item').on('click', function(e) {
        e.preventDefault();
        var postType = $(this).data('post-type'); // Assuming your tabs have a data attribute for the post type
        $('#search-results').removeClass(function(index, className) {
            return (className.match(/(^|\s)post-type-\S+/g) || []).join(' '); // Remove previous post type classes
        }).addClass(postType); // Add the new post type class
    });

    // Throttled search on keyup for improved performance
    function handleSearch(searchTerm) {
        clearTimeout(searchTimeout);
        currentSearchTerm = searchTerm; // Update the current search term

        if (currentSearchTerm) {
            searchTimeout = setTimeout(() => {
                displayResults(activePostType, currentSearchTerm);
            }, 300);
        } else {
            // Clear results and reset the view if no search term
            $("#docy-search-result").removeClass("ajax-search").html("");
            $(".spinner").hide();
        }
    }

    // Event listener for keyup on search input
    $('#searchInput').on('keyup', function () {
        $(this).focus();
        $('.header_search_form').css('z-index', '99');
        $('#search-preloader').hide();
        
        const searchTerm = $(this).val(); // Get current search term
        if (searchTerm) {
            handleSearch(searchTerm); // Call handleSearch with the current term
        } else {
            // If search term is empty, clear results and hide spinner
            $('#search-results').html('');
            $('#docy-search-result').removeClass('ajax-search');
            $(".spinner").hide();
        }
    });

    // Event listener for search keyword click
    $(".header_search_keyword ul li a").on("click", function (e) {
        e.preventDefault();
        const searchTerm = $(this).text();
        $("#searchInput").val(searchTerm).focus();
        handleSearch(searchTerm);
        $('.header_search_form').css( 'z-index', '99' );
    });

    // Update results when switching tabs if search term exists
    $('#search-tabs, #search_post_type').on('click change', function (e) {
        if (e.type === 'keydown' && e.key != 'Enter') {
            e.preventDefault();
        }
        // Determine the source and active post type
        if ($(e.target).hasClass('tab-item')) {
            activePostType = $(e.target).data('type');
            $('.tab-item').removeClass('active');
            $(e.target).addClass('active');
        } else if ($(e.target).is('#search_post_type')) {
            activePostType = $(e.target).val();
            $(e.target).addClass('active');
        }

        // Hide all from #search-results except #search-preloader
        $('#search-results').children().not('#search-preloader').hide();
        $('#search-preloader').show();

        // Only display results for the selected tab if there's a search term
        if (currentSearchTerm) {
            displayResults(activePostType, currentSearchTerm);
        }
    });

    // Add a body class when interacting with form, tabs, or results
    function addActiveClass() {
        $('body').addClass('search-focused');
    }

    function removeActiveClass() {
        $('body').removeClass('search-focused');
    }

    $('#ajax-search-form, .banner_search_form, .header_search_form, #search-tabs, #search-results').on('click', addActiveClass);

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#ajax-search-form, .banner_search_form, .header_search_form, .focused-form, #search-tabs, #search-results').length) {
            removeActiveClass();
        }
    });

    $(document).on('click', function (e) {
        if ($(e.target).closest('.header_search_form').length) {
            $('.header_search_form, .banner_search_form').css('z-index', '999');
        } else {
            $('.header_search_form, .banner_search_form').css('z-index', '2');
        }
    });


    docySearchTabs();

})(jQuery);