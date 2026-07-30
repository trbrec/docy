/**
 * Article Rating Feature for Blog Single Pages
 *
 * Handles the star rating interaction, AJAX submission,
 * state management, and accessibility for the "Rate the article" feature.
 *
 * Accessibility features:
 * - Keyboard navigation (arrow keys, Enter, Space)
 * - ARIA role="radiogroup" with proper aria-checked states
 * - Focus management
 * - Screen reader announcements via aria-live
 *
 * @package docy
 */

(function ($) {
	'use strict';

	/**
	 * Article Rating Handler
	 */
	var ArticleRating = {
		/**
		 * Initialize the rating feature.
		 */
		init: function () {
			this.container = $('#docy-article-rating');

			// Exit if container doesn't exist.
			if (!this.container.length) {
				return;
			}

			this.postId = this.container.data('post-id');
			this.stars = this.container.find('.docy-rating-star');
			this.starsContainer = this.container.find('.docy-rating-stars');
			this.storageKey = 'docy_article_rated_' + this.postId;
			this.currentFocusIndex = 0;

			// Check if already rated via localStorage.
			if (this.hasRated()) {
				this.showThankYou();
				return;
			}

			this.bindEvents();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function () {
			var self = this;

			// Star hover effect - fill stars up to hovered one.
			this.stars.on('mouseenter', function () {
				var rating = $(this).data('rating');
				self.highlightStars(rating);
			});

			// Reset stars on mouse leave.
			this.starsContainer.on('mouseleave', function () {
				self.resetStars();
			});

			// Star click - submit rating.
			this.stars.on('click', function (e) {
				e.preventDefault();
				var rating = $(this).data('rating');
				self.submitRating(rating);
			});

			// Keyboard navigation for accessibility.
			this.stars.on('keydown', function (e) {
				self.handleKeyboard(e, $(this));
			});

			// Focus management.
			this.stars.on('focus', function () {
				var index = self.stars.index($(this));
				self.currentFocusIndex = index;
				self.highlightStars(index + 1);
			});

			this.stars.on('blur', function () {
				self.resetStars();
			});
		},

		/**
		 * Handle keyboard navigation.
		 *
		 * @param {Event} e - The keyboard event.
		 * @param {jQuery} $star - The focused star element.
		 */
		handleKeyboard: function (e, $star) {
			var key = e.which || e.keyCode;
			var index = this.stars.index($star);
			var newIndex = index;

			switch (key) {
				case 37: // Left arrow
				case 38: // Up arrow
					e.preventDefault();
					newIndex = Math.max(0, index - 1);
					break;

				case 39: // Right arrow
				case 40: // Down arrow
					e.preventDefault();
					newIndex = Math.min(this.stars.length - 1, index + 1);
					break;

				case 13: // Enter
				case 32: // Space
					e.preventDefault();
					this.submitRating(index + 1);
					return;

				case 36: // Home
					e.preventDefault();
					newIndex = 0;
					break;

				case 35: // End
					e.preventDefault();
					newIndex = this.stars.length - 1;
					break;

				default:
					return;
			}

			// Move focus to new star.
			if (newIndex !== index) {
				this.stars.eq(newIndex).focus();
				this.currentFocusIndex = newIndex;
			}
		},

		/**
		 * Highlight stars up to the given rating.
		 *
		 * @param {number} rating - The rating to highlight up to.
		 */
		highlightStars: function (rating) {
			this.stars.each(function (index) {
				var $star = $(this);
				if (index < rating) {
					$star.addClass('hovered').attr('aria-checked', 'true');
				} else {
					$star.removeClass('hovered').attr('aria-checked', 'false');
				}
			});
		},

		/**
		 * Reset all stars to default state.
		 */
		resetStars: function () {
			this.stars.removeClass('hovered').attr('aria-checked', 'false');
		},

		/**
		 * Submit the rating via AJAX.
		 *
		 * @param {number} rating - The rating value (1-5).
		 */
		submitRating: function (rating) {
			var self = this;

			// Add loading state.
			this.container.addClass('is-loading');

			// Announce to screen readers.
			this.announceToScreenReader(docy_rating_params.submitting_text || 'Submitting your rating...');

			$.ajax({
				url: docy_rating_params.ajax_url,
				type: 'POST',
				data: {
					action: 'docy_submit_article_rating',
					post_id: this.postId,
					rating: rating,
					nonce: docy_rating_params.nonce
				},
				success: function (response) {
					if (response.success) {
						// Mark as rated in localStorage.
						self.setRated();

						// Announce success to screen readers.
						self.announceToScreenReader(response.data.message || docy_rating_params.thank_you_text);

						// Show thank you message.
						self.showThankYou();
					} else {
						// Show error message if any.
						console.error('Rating submission failed:', response.data);
						self.announceToScreenReader('Rating submission failed. Please try again.');
						self.container.removeClass('is-loading');
					}
				},
				error: function (xhr, status, error) {
					console.error('Rating AJAX error:', error);
					self.announceToScreenReader('An error occurred. Please try again.');
					self.container.removeClass('is-loading');
				}
			});
		},

		/**
		 * Announce message to screen readers via aria-live region.
		 *
		 * @param {string} message - The message to announce.
		 */
		announceToScreenReader: function (message) {
			var $liveRegion = $('#docy-rating-live-region');

			// Create live region if it doesn't exist.
			if (!$liveRegion.length) {
				$liveRegion = $('<div>', {
					id: 'docy-rating-live-region',
					class: 'screen-reader-text',
					'aria-live': 'polite',
					'aria-atomic': 'true'
				}).appendTo('body');
			}

			// Clear and set new message.
			$liveRegion.empty().text(message);
		},

		/**
		 * Check if user has already rated this article.
		 *
		 * @return {boolean} True if already rated.
		 */
		hasRated: function () {
			return localStorage.getItem(this.storageKey) === 'true';
		},

		/**
		 * Mark the article as rated in localStorage.
		 */
		setRated: function () {
			localStorage.setItem(this.storageKey, 'true');
		},

		/**
		 * Display the thank you message.
		 */
		showThankYou: function () {
			var thankYouHtml = '<div class="docy-rating-thankyou" role="status">' +
				'<div class="docy-rating-thankyou-icon" aria-hidden="true">' +
				'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="32" height="32">' +
				'<path d="M7.493 18.5c-.425 0-.82-.236-.975-.632A7.48 7.48 0 0 1 6 15.125c0-1.75.599-3.358 1.602-4.634.151-.192.373-.309.6-.397.473-.183.89-.514 1.212-.924a9.042 9.042 0 0 1 2.861-2.4c.723-.384 1.35-.956 1.653-1.715a4.498 4.498 0 0 0 .322-1.672V3a.75.75 0 0 1 .75-.75 2.25 2.25 0 0 1 2.25 2.25c0 1.152-.26 2.243-.723 3.218-.266.558.107 1.282.725 1.282h3.126c1.026 0 1.945.694 2.054 1.715.045.422.068.85.068 1.285a11.95 11.95 0 0 1-2.649 7.521c-.388.482-.987.729-1.605.729H14.23c-.483 0-.964-.078-1.423-.23l-3.114-1.04a4.501 4.501 0 0 0-1.423-.23h-.777ZM2.331 10.727a11.969 11.969 0 0 0-.831 4.398 12 12 0 0 0 .52 3.507c.26.85 1.084 1.368 1.973 1.368H4.9c.445 0 .72-.498.523-.898a8.963 8.963 0 0 1-.924-3.977c0-1.708.476-3.305 1.302-4.666.245-.403-.028-.959-.5-.959H4.25c-.832 0-1.612.453-1.918 1.227Z" />' +
				'</svg>' +
				'</div>' +
				'<p class="docy-rating-thankyou-text">' + docy_rating_params.thank_you_text + '</p>' +
				'</div>';

			this.container.removeClass('is-loading').html(thankYouHtml);
		}
	};

	// Initialize on document ready.
	$(document).ready(function () {
		ArticleRating.init();
	});

})(jQuery);
