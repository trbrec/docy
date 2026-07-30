; (function ($) {
    "use strict";

    // Reading progress bar
    let scrollTick = false;
    $(window).scroll(function () {
        if (!scrollTick) {
            window.requestAnimationFrame(function () {
                let w =
                    ((document.body.scrollTop || document.documentElement.scrollTop) /
                        (document.documentElement.scrollHeight -
                            document.documentElement.clientHeight)) *
                    100;
                $("#reading-progress-fill").css({ width: w + "%", display: "block" });
                scrollTick = false;
            });
            scrollTick = true;
        }
    });

    // Header Navbar Search Form - class-based CSS transition with ESC key support
    $(".right-nav .search-icon").on("click", function () {
        const $searchForm = $(".search-input.toggle");
        const $this = $(this);

        if ($searchForm.hasClass("show")) {
            $searchForm.removeClass("show");
            $this.removeClass("show-close")
                .attr("aria-expanded", "false")
                .focus();
        } else {
            $searchForm.addClass("show");
            $this.addClass("show-close")
                .attr("aria-expanded", "true");
            // Defer focus until the form has become visible; focusing while it
            // is still `visibility: hidden` mid-transition is silently ignored.
            const searchInput = $searchForm.find("input").get(0);
            if (searchInput) {
                window.requestAnimationFrame(function () {
                    window.requestAnimationFrame(function () {
                        searchInput.focus();
                    });
                });
            }
        }
    });

    // Keyboard support for Header Navbar Search Form
    $(".right-nav .search-icon").on("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            $(this).trigger("click");
        }
    });

    // ESC key to close search form
    $(document).on("keydown", function(e) {
        if (e.key === "Escape" && $(".search-input.toggle").hasClass("show")) {
            $(".search-input.toggle").removeClass("show");
            $(".right-nav .search-icon").removeClass("show-close")
                .attr("aria-expanded", "false")
                .focus();
        }
    });

    $(document).on(
        "click",
        "#docy-search-result .searchbar-tabs .tab-item",
        function (e) {
            $(".searchbar-tabs .tab-item").removeClass("active");
            $(this).addClass("active");
        }
    );

        $(document).on("click", ".search-result-item[data-result-url]", function (e) {
            if ($(e.target).closest("a, button, input, textarea, select, label").length) {
                return;
            }

            var resultUrl = $(this).data("result-url");

            if (resultUrl) {
                window.location.href = resultUrl;
            }
        });

        $(document).on("keydown", ".search-result-item[data-result-url]", function (e) {
            if (e.key !== "Enter" && e.key !== " ") {
                return;
            }

            if ($(e.target).closest("a, button, input, textarea, select, label").length) {
                return;
            }

            e.preventDefault();

            var resultUrl = $(this).data("result-url");

            if (resultUrl) {
                window.location.href = resultUrl;
            }
        });

    // Sanitize heading IDs to ensure they don't start with a number
    function sanitizeHeadingIds() {
        let scope = document.querySelector('.anchor-enabled') || document.body;
        if (!scope) return;

        let headings = scope.querySelectorAll('h1[id], h2[id], h3[id], h4[id], h5[id], h6[id]');

        headings.forEach(function (heading) {
            let id = heading.id;
            if (!id) return;

            if (/^[0-9]/.test(id)) {
                let newId = 'toc-' + id;
                heading.id = newId;
                document.querySelectorAll('a[href="#' + id + '"]')
                    .forEach(function (a) {
                        a.setAttribute('href', '#' + newId);
                    }
                    );
            }
        });
    }

    $(document).ready(function () {

        // wide container header on course pages
        if ($(".single-courses").length) {
            $(".single-courses").addClass("wide-container");
        }

        if ($(".single-lesson").length) {
            $(".single-lesson").addClass("wide-container");
        }

        /**
         * Make the Titles clickable
         * If no selector is provided, it falls back to a default selector of:
         * 'h2, h3, h4, h5, h6'
         */
        if (typeof anchors !== "undefined") {
            anchors.add(".anchor-enabled :is(h1, h2, h3, h4, h5, h6)");
            // Sanitize IDs immediately after anchor.js generates them
            sanitizeHeadingIds();
        }

        /**
         * Disable enter key press on Forum Topics Filter search input field
         */
        $(".post-header .category-menu .cate-search-form").keypress(function (
            event
        ) {
            if (event.which == "13") {
                event.preventDefault();
            }
        });

        $(".onepage-doc .nav-sidebar .nav-item:first-child").addClass("active");

        $("#searchInput").on("input", function (e) {
            if ("" == this.value) {
                $("#docy-search-result").removeClass("ajax-search");
            }
        });

        //*=============menu sticky js =============*//
        var $window = $(window);
        var didScroll,
            lastScrollTop = 0,
            delta = 5,
            $mainNav = $("#sticky"),
            $mainNavHeight = $mainNav.outerHeight(),
            scrollTop;

        $window.on("scroll", function () {
            didScroll = true;
            scrollTop = $(this).scrollTop();
        });

        setInterval(function () {
            if (didScroll && $(".navbar button.navbar-toggler.collapsed").length) {
                hasScrolled();
                didScroll = false;
            }
        }, 200);

        function hasScrolled() {
            if (Math.abs(lastScrollTop - scrollTop) <= delta) {
                return;
            }
            if (scrollTop > lastScrollTop && scrollTop > $mainNavHeight) {
                $mainNav
                    .removeClass("fadeInDown")
                    .addClass("fadeInUp")
                    .css("top", -$mainNavHeight);
                $("body").removeClass("navbar-shown").addClass("navbar-hidden");
            } else {
                if (scrollTop + $(window).height() < $(document).height()) {
                    $mainNav.removeClass("fadeInUp").addClass("fadeInDown").css("top", 0);
                    $("body").removeClass("navbar-hidden").addClass("navbar-shown");
                }
            }
            lastScrollTop = scrollTop;
        }

        function navbarFixed() {
            if ($("#sticky").length) {
                $(window).scroll(function () {
                    var scroll = $(window).scrollTop();
                    if (scroll) {
                        $("#sticky").addClass("navbar_fixed");
                        $(".sticky-nav-doc .body_fixed").addClass("body_navbar_fixed");
                    } else {
                        $("#sticky").removeClass("navbar_fixed");
                        $(".sticky-nav-doc .body_fixed").removeClass("body_navbar_fixed");
                    }
                });
            }
        }

        navbarFixed();

        function navbarFixedTwo() {
            if ($("#stickyTwo").length) {
                $(window).scroll(function () {
                    var scroll = $(window).scrollTop();
                    if (scroll) {
                        $("#stickyTwo").addClass("navbar_fixed");
                    } else {
                        $("#stickyTwo").removeClass("navbar_fixed");
                    }
                });
            }
        }

        navbarFixedTwo();

        function mobileNavbarFixed() {
            if ($("#mobile-sticky").length) {
                $(window).scroll(function () {
                    var scroll = $(window).scrollTop();
                    if (scroll) {
                        $("#mobile-sticky").addClass("navbar_fixed");
                    } else {
                        $("#mobile-sticky").removeClass("navbar_fixed");
                    }
                });
            }
        }

        mobileNavbarFixed();

        function mobileNavbarFixedTwo() {
            if ($("#mobile-stickyTwo").length) {
                $(window).scroll(function () {
                    var scroll = $(window).scrollTop();
                    if (scroll) {
                        $("#mobile-stickyTwo").addClass("navbar_fixed");
                    } else {
                        $("#mobile-stickyTwo").removeClass("navbar_fixed");
                    }
                });
            }
        }

        mobileNavbarFixedTwo();

        //*=============menu sticky js =============*//

        //  page scroll
        function bodyFixed() {
            var windowWidth = $(window).width();
            if ($("#sticky_doc").length) {
                if (windowWidth > 576) {
                    var tops = $("#sticky_doc");
                    var leftOffset = tops.offset().top;

                    $(window).on("scroll", function () {
                        var scroll = $(window).scrollTop();
                        if (scroll >= leftOffset) {
                            tops.addClass("body_fixed");
                        } else {
                            tops.removeClass("body_fixed");
                        }
                    });
                }
            }
        }

        bodyFixed();

        // Left sidebar TOC area get sticky
        function bodyFixed2() {
            var windowWidth = $(window).width();

            if ($("#toc_stick").length) {
                if (windowWidth > 576) {
                    let tops = $("#toc_stick");
                    let topOffset = tops.offset().top;
                    if ($('.blog_comment_box').length) {
                        let blogForm = $('.blog_comment_box');
                        let blogFormTop = blogForm.offset().top - 300;
                    }

                    $(window).on("scroll", function () {
                        var scrolls = $(window).scrollTop();
                        if (scrolls >= topOffset) {
                            tops.addClass("stick");
                        } else {
                            tops.removeClass("stick");
                        }
                    });

                    $('a[href="#hackers"]').click(function () {
                        $("#hackers").css("padding-top", "100px");

                        $(window).on("scroll", function () {
                            var hackersOffset = $("#hackers").offset().top;
                            var scrolls = $(window).scrollTop();
                            if (scrolls < hackersOffset) {
                                $("#hackers").css("padding-top", "0px");
                            }
                        });
                    });
                }
            }
        }

        bodyFixed2();

        function mobileTable() {
            let tocVisible = false; // Track visibility of TOC
            let shareModalVisible = false; // Track visibility of Share modal

            // Toggle the visibility of the Table of Contents when the button is clicked.
            $('.table_content').on('click', function () {
                let toc = $(this).next('aside.bottom_table_content'); // Select the adjacent TOC
                let container = $(this).closest('.docy-mobile-toc'); // Select the .docy-mobile-toc container
                let overlay = $('#toc-overlay'); // Select the overlay

                if (tocVisible) {
                    toc.slideUp(300, function () {
                        container.css('border-radius', '10px 10px 0 0'); // Reset border-radius after TOC is hidden
                    });
                    overlay.fadeOut(300); // Hide the overlay
                    tocVisible = false;
                } else {
                    toc.css({
                        'background-color': '#171a22', // Background color
                        'color': '#fff', // Text color
                        'padding': '24px 24px 0 24px', // Padding for better appearance
                        'border-radius': '10px 10px 0 0', // Rounded corners
                        'box-shadow': '0px 4px 8px rgba(0, 0, 0, 0.1)' // Optional shadow for better look
                    }).slideDown(300); // Show with smooth slide-down effect

                    container.css('border-radius', '0'); // Remove border-radius when TOC is visible
                    overlay.fadeIn(300); // Show the overlay
                    tocVisible = true;
                }
            });

            // Close TOC when the close button is clicked
            $('.close-toc').on('click', function () {
                let toc = $(this).closest('aside.bottom_table_content'); // Select the parent TOC
                let container = toc.closest('.docy-mobile-toc'); // Select the .docy-mobile-toc container
                let overlay = $('#toc-overlay'); // Select the overlay

                toc.slideUp(300, function () {
                    container.css('border-radius', '10px 10px 0 0'); // Reset border-radius after TOC is hidden
                });
                overlay.fadeOut(300); // Hide the overlay
                tocVisible = false; // Update the visibility status
            });

            $('.docy-mobile-toc .mobile-toc .bottom_table_content nav').on('click', '.nav-link', function () {
                let toc = $('aside.bottom_table_content'); // Find the adjacent TOC for the clicked item
                let container = toc.closest('.docy-mobile-toc'); // Select the .docy-mobile-toc container
                let overlay = $('#toc-overlay'); // Select the overlay

                // Hide the TOC smoothly
                toc.slideUp(300, function () {
                    container.css('border-radius', '10px 10px 0 0'); // Reset border-radius after TOC is hidden
                });
                overlay.fadeOut(300); // Hide the overlay
                tocVisible = false; // Update the visibility status
            });
            // Close TOC when .nav-link is clicked


            // Toggle the visibility of the Share modal when the share button is clicked.
            $('.table_share_btn').on('click', function () {
                let shareModal = $('#share-modal'); // Select the share modal
                let container = $(this).closest('.docy-mobile-toc'); // Select the .docy-mobile-toc container
                let overlay = $('#toc-overlay'); // Use the same overlay for simplicity

                if (shareModalVisible) {
                    shareModal.slideUp(300, function () {
                        container.css('border-radius', '10px 10px 0 0'); // Reset border-radius after modal is hidden
                    });
                    overlay.fadeOut(300); // Hide the overlay
                    shareModalVisible = false;
                } else {
                    shareModal.css({
                        'background-color': '#171a22', // Background color
                        'color': '#fff', // Text color
                        'padding': '24px 24px 12px 24px', // Padding for better appearance
                        'border-radius': '10px 10px 0 0', // Rounded corners
                        'box-shadow': '0px 4px 8px rgba(0, 0, 0, 0.1)' // Optional shadow for better look
                    }).slideDown(300); // Show with smooth slide-down effect

                    container.css('border-radius', '0'); // Remove border-radius when modal is visible
                    overlay.fadeIn(300); // Show the overlay
                    shareModalVisible = true;
                }
            });

            // Close Share modal when the close button is clicked
            $('.docy-close').on('click', function () {
                let shareModal = $(this).closest('#share-modal'); // Select the parent modal
                let overlay = $('#toc-overlay'); // Select the overlay
                let container = shareModal.closest('.docy-mobile-toc'); // Select the .docy-mobile-toc container

                shareModal.slideUp(300, function () {
                    container.css('border-radius', '10px 10px 0 0'); // Reset border-radius after modal is hidden
                });
                overlay.fadeOut(300); // Hide the overlay
                shareModalVisible = false; // Update the visibility status
            });

            // Close TOC and Share modal when clicking outside (on the overlay)
            $('#toc-overlay').on('click', function () {
                let toc = $('aside.bottom_table_content'); // Select the TOC element
                let shareModal = $('#share-modal'); // Select the Share modal
                let container = toc.closest('.docy-mobile-toc'); // Select the container

                toc.slideUp(300, function () {
                    container.css('border-radius', '10px 10px 0 0'); // Reset border-radius after TOC is hidden
                });
                shareModal.slideUp(300); // Hide the share modal
                $(this).fadeOut(300); // Hide the overlay
                tocVisible = false; // Update the TOC visibility status
                shareModalVisible = false; // Update the Share modal visibility status
            });

            // Copy link functionality with popup notification
            $('.share-this-docs img').on('click', function () {
                let input = $(this).siblings('input'); // Select the input with the link
                input.select();
                document.execCommand("copy");

                // Show popup with checkmark
                let popup = $('<div class="copy-popup"><span>✓</span> URL copied to clipboard</div>');
                $('body').append(popup);
                popup.css({
                    width: 'max-content',
                    position: 'fixed',
                    top: '10%',
                    left: '50%',
                    transform: 'translateX(-50%)',
                    background: '#171a22',
                    color: '#fff',
                    padding: '8px 18px',
                    'border-radius': '5px',
                    'z-index': '9999',
                    'box-shadow': '0px 4px 8px rgba(0, 0, 0, 0.1)'
                });

                // Fade out popup after 2 seconds
                setTimeout(function () {
                    popup.fadeOut(300, function () {
                        popup.remove(); // Remove popup after fade out
                    });
                }, 2000);
            });
        }

        mobileTable();


        /*  Menu Click js  */
        function Menu_js() {
            if ($(".submenu").length) {
                $(".submenu > .dropdown-toggle").click(function () {
                    var location = $(this).attr("href");
                    window.location.href = location;
                    return false;
                });
            }
        }

        Menu_js();

        if ($(".mobile_menu").length > 0) {
            var switchs = true;
            $(".mobile_btn").on("click", function (e) {
                if (switchs) {
                    $(".mobile_menu").addClass("open");
                }
            });
        }

        /*--------------- parallaxie js--------*/
        function parallax() {
            if ($(".parallaxie").length) {
                $(".parallaxie").parallaxie({
                    speed: 0.5,
                });
            }
        }

        parallax();

        if ($(".tooltips_one").length) {
            $(".tooltips_one").data("tooltip-custom-class", "tooltip_blue").tooltip();
        }
        if ($(".tooltips_two").length) {
            $(".tooltips_two")
                .data("tooltip-custom-class", "tooltip_danger")
                .tooltip();
        }

        /*--------------- mobile dropdown js--------*/
        function menu_dropdown() {

            $(".side_menu .menu .dropdown-menu").slideUp(700);
            $(".side_menu .menu > li .mobile_dropdown_icon").on("click", function (event) {
                $(this).parent().parent().find(".dropdown-menu").first().slideToggle(700);
                $(this).parent().parent().siblings().find(".dropdown-menu").slideUp(700);
                // ToggleClass open added in the direct parent (not all parent)
                $(this).parent().parent().toggleClass("opened");
                $(this).parent().parent().siblings().removeClass("opened");
            });
        }

        menu_dropdown();

        /*--------------- niceSelect js--------*/

        function select() {
            if (typeof $.fn.niceSelect !== 'function') {
                return; // Exit if nice-select library is not loaded
            }
            let niceSelect = $('.custom-select, .nice-select, .bbp-topic-form .bbp_dropdown, .search_result_dropdown .dropdown_select');
            if (niceSelect.length > 0) {
                niceSelect.niceSelect();
            }
            if ($("#mySelect").length) {
                $("#mySelect").selectpicker();
            }
        }

        select();

        /*--------------- counterUp js--------*/
        function counterUp() {
            if ($(".counter").length) {
                $(".counter").counterUp({
                    delay: 1,
                    time: 250,
                });
            }
        }

        counterUp();

        /*--------------- popup-js--------*/
        function popupGallery() {
            if ($(".img_popup").length) {
                $(".img_popup").each(function () {
                    $(".img_popup").magnificPopup({
                        type: "image",
                        closeOnContentClick: true,
                        closeBtnInside: false,
                        fixedContentPos: true,
                        removalDelay: 300,
                        mainClass: "mfp-no-margins mfp-with-zoom",
                        image: {
                            enabled: true,
                            navigateByImgClick: true,
                            preload: [0, 1], // Will preload 0 - before current, and 1 after the current image,
                        },
                    });
                });
            }
        }

        popupGallery();

        /*--------------- video js--------*/
        function video() {
            if ($("#inline-popups").length) {
                $("#inline-popups").magnificPopup({
                    delegate: "a",
                    removalDelay: 500, //delay removal by X to allow out-animation
                    mainClass: "mfp-no-margins mfp-with-zoom",
                    preloader: false,
                    midClick: true,
                });
            }
        }

        video();

        /*--------- WOW js-----------*/
        function bodyScrollAnimation() {
            var scrollAnimate = $("body").data("scroll-animation");
            if (scrollAnimate === true) {
                new WOW({}).init();
            }
        }

        bodyScrollAnimation();

        // Global mobile menu with improved accessibility, focus trap and ESC support
        var $sideMenu = $(".side_menu");
        var $menuBtn = $(".mobile_menu_btn");
        var focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

        // Centralized open/close so all triggers stay in sync (button, close, overlay, ESC, link).
        function openSideMenu() {
            if ($sideMenu.hasClass("menu-opened")) {
                return;
            }
            $sideMenu.addClass("menu-opened").attr("aria-hidden", "false");
            $("body").removeClass("menu-is-closed").addClass("menu-is-opened").css("overflow", "hidden");
            $menuBtn.attr("aria-expanded", "true");

            // Move focus into the panel once it is visible.
            setTimeout(function () {
                $(".close_nav").focus();
            }, 100);
        }

        function closeSideMenu(returnFocus) {
            if (!$sideMenu.hasClass("menu-opened")) {
                return;
            }
            $sideMenu.removeClass("menu-opened").attr("aria-hidden", "true");
            $("body").removeClass("menu-is-opened").addClass("menu-is-closed").css("overflow", "");
            $menuBtn.attr("aria-expanded", "false");

            if (returnFocus) {
                $menuBtn.focus();
            }
        }

        // Keep keyboard focus inside the dialog while it is open.
        function trapFocus(e) {
            if (!$sideMenu.hasClass("menu-opened")) {
                return;
            }
            var $focusable = $sideMenu.find(focusableSelector).filter(":visible");
            if (!$focusable.length) {
                return;
            }
            var first = $focusable.first()[0];
            var last = $focusable.last()[0];

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }

        $menuBtn.on("click", function () {
            if ($sideMenu.hasClass("menu-opened")) {
                closeSideMenu(true);
            } else {
                openSideMenu();
            }
        });

        $(".close_nav").on("click", function () {
            closeSideMenu(true);
        });

        $(".click_capture").on("click", function () {
            closeSideMenu(false);
        });

        // ESC closes the menu; Tab is trapped within it.
        $(document).on("keydown", function (e) {
            if (e.key === "Escape") {
                closeSideMenu(true);
            } else if (e.key === "Tab") {
                trapFocus(e);
            }
        });

        // Auto-close side menu on anchor/link click (ignore dropdown toggles).
        $(".side_menu .menu a, .side_menu .mobile_nav_bottom a").on("click", function () {
            var $link = $(this);
            if (!$link.hasClass("dropdown-toggle") && !$link.siblings(".dropdown-menu").length && !$link.hasClass("mobile_dropdown_icon")) {
                closeSideMenu(false);
            }
        });

        /*--------------- Tab button js--------*/
        $(".next").on("click", function () {
            $(".v_menu .nav-item > .active")
                .parent()
                .next("li")
                .find("a")
                .trigger("click");
        });

        $(".previous").on("click", function () {
            $(".v_menu .nav-item > .active")
                .parent()
                .prev("li")
                .find("a")
                .trigger("click");
        });

        function Click_menu_hover() {
            if ($(".tab-demo").length) {
                $.fn.tab = function (options) {
                    var opts = $.extend({}, $.fn.tab.defaults, options);
                    return this.each(function () {
                        var obj = $(this);

                        $(obj)
                            .find(".tabHeader li")
                            .on(opts.trigger_event_type, function () {
                                $(obj).find(".tabHeader li").removeClass("active");
                                $(this).addClass("active");

                                $(obj).find(".tabContent .tab-pane").removeClass("active show");
                                $(obj)
                                    .find(".tabContent .tab-pane")
                                    .eq($(this).index())
                                    .addClass("active show");
                            });
                    });
                };
                $.fn.tab.defaults = {
                    trigger_event_type: "click", //mouseover | click é»˜è®¤æ˜¯click
                };
            }
        }

        Click_menu_hover();

        function Tab_menu_activator() {
            if ($(".tab-demo").length) {
                $(".tab-demo").tab({
                    trigger_event_type: "mouseover",
                });
            }
        }

        Tab_menu_activator();

        function fAqactive() {
            $(".doc_faq_info .card").on("click", function () {
                $(".doc_faq_info .card").removeClass("active");
                $(this).addClass("active");
            });
        }

        fAqactive();

        function general() {
            $(".short-by a").click(function () {
                $(this)
                    .toggleClass("active-short")
                    .siblings()
                    .removeClass("active-short");
            });
        }

        general();

        /*-------------------------------------
            Intersection Observer
            -------------------------------------*/
        if (!!window.IntersectionObserver) {
            let observer = new IntersectionObserver(
                (entries, observer) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add("active-animation");
                            //entry.target.src = entry.target.dataset.src;
                            observer.unobserve(entry.target);
                        }
                    });
                },
                {
                    rootMargin: "0px 0px -100px 0px",
                }
            );
            document.querySelectorAll(".has-animation").forEach((block) => {
                observer.observe(block);
            });
        } else {
            document.querySelectorAll(".has-animation").forEach((block) => {
                block.classList.remove("has-animation");
            });
        }

        // === Search ("/" to focus)
        if (
            docy_local_object.is_focus_by_slash === "1" &&
            $(".sbnr-global").length
        ) {
            $(document).on("keydown", function (e) {
                if (e.keyCode === 191 && !$("input, textarea").is(":focus")) {
                    e.preventDefault();
                    $(".sbnr-global form input[type=search]").focus();
                    return;
                }
            });
        }

        // === Back to Top Button
        var back_top_btn = $("#back-to-top");
        $(window).scroll(function () {
            if ($(window).scrollTop() > 300) {
                back_top_btn.addClass("show");
            } else {
                back_top_btn.removeClass("show");
            }
        });
        back_top_btn.on("click", function (e) {
            e.preventDefault();
            $("html, body").animate({ scrollTop: 0 }, "300");
        });

        if ($(".cheatsheet_item").length) {
            $(".shadow-sm.cheatsheet_item").hover(
                function () {
                    $(this).removeClass("shadow-sm");
                    $(this).addClass("shadow-lg");
                },
                function () {
                    $(this).removeClass("shadow-lg");
                    $(this).addClass("shadow-sm");
                }
            );
        }

        if ($(".popup-youtube").length) {
            $(".popup-youtube").magnificPopup({
                type: "iframe",
            });
        }

        //================  Mega Menu ====================//
        $(".has-docy-mega-menu").click(function () {
            $(this).toggleClass("megamenu-display");
        });

        $(".has-docy-mega-menu > a").click(function () {
            $(this).parent(".has-docy-mega-menu").toggleClass("megamenu-display");
            return false;
        });

        $(".arrow_carrot-right.mobile_dropdown_icon").click(function () {
            $(this).parent().parent(".has-docy-mega-menu").toggleClass("megamenu-display");
        });

        //================ Top Header ====================//
        function docy_top_header() {
            if ($('.top_header').length > 0) {
                $('body').addClass('docy_top_header');
            }
        }

        docy_top_header();

        //================ Multi Logo Dropdown ====================//
        function multiLogoDropdown() {
            var $toggle = $('.multi-logo-toggle');
            var $dropdown = $('.multi-logo-dropdown');

            if (!$toggle.length || !$dropdown.length) {
                return;
            }

            // Toggle dropdown on button click
            $toggle.on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var isOpen = $dropdown.hasClass('is-open');

                if (isOpen) {
                    closeMultiLogoDropdown();
                } else {
                    openMultiLogoDropdown();
                }
            });

            // Close dropdown when clicking outside
            $(document).on('click', function (e) {
                if (!$(e.target).closest('.docy-logo-wrapper').length) {
                    closeMultiLogoDropdown();
                }
            });

            // Close dropdown on Escape key
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && $dropdown.hasClass('is-open')) {
                    closeMultiLogoDropdown();
                    $toggle.focus();
                }
            });

            // Keyboard navigation within dropdown
            $dropdown.on('keydown', '.site-link', function (e) {
                var $links = $dropdown.find('.site-link');
                var currentIndex = $links.index(this);

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    var nextIndex = (currentIndex + 1) % $links.length;
                    $links.eq(nextIndex).focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    var prevIndex = (currentIndex - 1 + $links.length) % $links.length;
                    $links.eq(prevIndex).focus();
                }
            });

            function openMultiLogoDropdown() {
                $dropdown.addClass('is-open');
                $toggle.attr('aria-expanded', 'true');
                // Focus first link for keyboard users
                setTimeout(function () {
                    $dropdown.find('.site-link').first().focus();
                }, 100);
            }

            function closeMultiLogoDropdown() {
                $dropdown.removeClass('is-open');
                $toggle.attr('aria-expanded', 'false');
            }
        }

        multiLogoDropdown();

        $.fn.ezd_social_popup = function (
            e,
            intWidth,
            intHeight,
            strResize,
            blnResize
        ) {
            // Prevent default anchor event
            e.preventDefault();

            // Set values for window
            intWidth = intWidth || "500";
            intHeight = intHeight || "400";
            strResize = blnResize ? "yes" : "no";

            // Set title and open popup with focus on it
            var strTitle =
                typeof this.attr("title") !== "undefined"
                    ? this.attr("title")
                    : "Social Share",
                strParam =
                    "width=" +
                    intWidth +
                    ",height=" +
                    intHeight +
                    ",resizable=" +
                    strResize,
                objWindow = window.open(this.attr("href"), strTitle, strParam).focus();
        };
        $(".social-links a:not(:first)").on("click", function (e) {
            $(this).ezd_social_popup(e);
        });

        // Select the #docy-toc and .doc_footer_area elements


        function docy_masonry_column() {

            let $grid = $('.masonry-grid');

            if ($grid.length) {
                $grid.imagesLoaded(function () {
                    $grid.masonry({
                        itemSelector: '.masonry-item',
                        percentPosition: true,
                    });
                });
            }

        }

        docy_masonry_column();
    });

    document.addEventListener("DOMContentLoaded", function () {

        // Add Body Class When active video banner selector found
        let WrapBannerVideo = document.getElementsByClassName("banner-video-container");
        if (WrapBannerVideo.length > 0) {
            document.body.classList.add('banner-video-wrap');
        }
    })

})(jQuery);


// Banner featured video interactions.
document.addEventListener("DOMContentLoaded", function () {
    var videoContainers = document.querySelectorAll(".banner-video-container");

    if (!videoContainers.length) {
        return;
    }

    videoContainers.forEach(function (container) {
        var playOverlay = container.querySelector(".play-overlay");
        var iframe = container.querySelector(".banner-video-iframe");
        var thumbnail = container.querySelector(".video-thumbnail");
        var copyButton = container.querySelector(".copy-link-btn");

        if (playOverlay && iframe) {
            playOverlay.addEventListener("click", function () {
                if (iframe.src.indexOf("autoplay=1") === -1) {
                    iframe.src = iframe.src + "&autoplay=1";
                }

                playOverlay.style.display = "none";
                iframe.style.display = "block";

                if (thumbnail) {
                    thumbnail.style.display = "none";
                }
            });
        }

        if (copyButton) {
            copyButton.addEventListener("click", function () {
                var link = copyButton.dataset.videoLink || "";
                var defaultLabel = copyButton.dataset.labelDefault || "Copy link";
                var copiedLabel = copyButton.dataset.labelCopied || "Link copied to clipboard!";

                if (!link) {
                    return;
                }

                docyCopyTextToClipboard(link).then(function () {
                    copyButton.textContent = copiedLabel;
                    copyButton.classList.add("copied");

                    window.setTimeout(function () {
                        copyButton.textContent = defaultLabel;
                        copyButton.classList.remove("copied");
                    }, 3000);
                }).catch(function () {
                    copyButton.textContent = defaultLabel;
                });
            });
        }
    });
});

function docyCopyTextToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    }

    return new Promise(function (resolve, reject) {
        var textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.setAttribute("readonly", "readonly");
        textArea.style.position = "fixed";
        textArea.style.opacity = "0";

        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            var isCopied = document.execCommand("copy");
            document.body.removeChild(textArea);

            if (isCopied) {
                resolve();
                return;
            }

            reject(new Error("Copy command was unsuccessful."));
        } catch (error) {
            document.body.removeChild(textArea);
            reject(error);
        }
    });
}