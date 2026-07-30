/**
 * AJAX commenting and frontend inline comment editing.
 *
 * - Submits the blog/docs comment form via AJAX and injects the new comment
 *   (or a moderation notice) without a full page reload.
 * - Lets a comment's author (or a moderator) edit their comment inline.
 *
 * Depends on the localized `docy_comments_params` object.
 *
 * @package docy
 */

(function ($) {
	'use strict';

	var params = window.docy_comments_params || {};
	var i18n = params.i18n || {};

	var DocyComments = {
		/**
		 * Bootstrap event bindings.
		 */
		init: function () {
			this.$form = $('#commentform');
			this.$response = $('.docy-comment-response');
			this.$list = $('#comments .comment_box').first();
			this.$count = $('#comments .c_head').first();
			this.$empty = $('#comments .comment_empty').first();
			this.$wrappers = $('#comments, .blog_comment_box');
			this.dismissTimer = null;

			this.bindSubmit();
			this.bindEditing();
		},

		/**
		 * Show a transient status/error message near the form.
		 *
		 * Errors are announced assertively and stay put; success messages are
		 * announced politely and auto-dismiss after a few seconds.
		 *
		 * @param {string}  message Text to display.
		 * @param {boolean} isError Whether this is an error message.
		 */
		notify: function (message, isError) {
			var self = this;

			if (!this.$response.length || !message) {
				return;
			}

			if (this.dismissTimer) {
				window.clearTimeout(this.dismissTimer);
				this.dismissTimer = null;
			}

			this.$response
				.removeClass('is-error is-success')
				.addClass(isError ? 'is-error' : 'is-success')
				.attr('role', isError ? 'alert' : 'status')
				.attr('aria-live', isError ? 'assertive' : 'polite')
				.text(message)
				.prop('hidden', false);

			if (!isError) {
				this.dismissTimer = window.setTimeout(function () {
					self.$response.prop('hidden', true).text('');
					self.dismissTimer = null;
				}, 5000);
			}
		},

		/**
		 * Refresh the comment-count heading and empty-state after a post.
		 *
		 * @param {Object} data AJAX success payload.
		 */
		updateCount: function (data) {
			// Only approved comments change the public count; a pending comment
			// leaves it untouched, so guard on the returned value.
			if (typeof data.count === 'undefined') {
				return;
			}

			if (data.count > 0) {
				if (this.$count.length && data.count_text) {
					this.$count.text(' ' + data.count_text + ' ').prop('hidden', false);
				}
				this.$wrappers.removeClass('no_comments').addClass('have_comments');
			}
		},

		/**
		 * Remove the "no comments yet" placeholder once any comment is shown,
		 * including one still awaiting moderation.
		 */
		clearEmptyState: function () {
			if (this.$empty.length) {
				this.$empty.remove();
				this.$empty = $();
			}
		},

		/**
		 * Intercept the comment form submit and post it over AJAX.
		 */
		bindSubmit: function () {
			var self = this;

			if (!this.$form.length) {
				return;
			}

			this.$form.on('submit', function (event) {
				event.preventDefault();

				var $form = $(this);
				var $submit = $form.find('#submit, [type="submit"]').first();
				var $comment = $form.find('#comment');

				if ($.trim($comment.val()) === '') {
					self.notify(i18n.empty_comment, true);
					$comment.trigger('focus');
					return;
				}

				// Prevent double submissions.
				if ($submit.prop('disabled')) {
					return;
				}

				var originalLabel = $submit.is('input') ? $submit.val() : $submit.text();

				$submit.prop('disabled', true).addClass('is-loading');
				if ($submit.is('input')) {
					$submit.val(i18n.posting || originalLabel);
				} else {
					$submit.text(i18n.posting || originalLabel);
				}

				var formData = $form.serializeArray();
				formData.push({ name: 'action', value: 'docy_post_comment' });

				$.ajax({
					url: params.ajax_url,
					type: 'POST',
					data: $.param(formData),
					dataType: 'json'
				})
					.done(function (response) {
						if (response && response.success && response.data) {
							self.handlePosted(response.data, $form);
						} else {
							var msg = response && response.data && response.data.message
								? response.data.message
								: i18n.generic_error;
							self.notify(msg, true);
						}
					})
					.fail(function (xhr) {
						var msg = i18n.generic_error;
						if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
							msg = xhr.responseJSON.data.message;
						}
						self.notify(msg, true);
					})
					.always(function () {
						$submit.prop('disabled', false).removeClass('is-loading');
						if ($submit.is('input')) {
							$submit.val(originalLabel);
						} else {
							$submit.text(originalLabel);
						}
					});
			});
		},

		/**
		 * Insert a freshly posted comment into the DOM.
		 *
		 * @param {Object} data  AJAX success payload.
		 * @param {jQuery} $form The comment form.
		 */
		handlePosted: function (data, $form) {
			var $node = $(data.comment_html);

			if (data.parent && data.parent > 0) {
				var $parent = $('#comment-' + data.parent);

				if ($parent.length) {
					var $children = $parent.children('ul.children').first();
					if (!$children.length) {
						$children = $('<ul class="children list-unstyled"></ul>');
						$parent.append($children);
					}
					$children.append($node);
				} else if (this.$list.length) {
					this.$list.append($node);
				}
			} else if (this.$list.length) {
				this.$list.append($node);
			}

			// Reset the form and return it to its default position if it was a reply.
			$form[0].reset();
			var $cancel = $('#cancel-comment-reply-link');
			if ($cancel.length && $cancel.is(':visible')) {
				$cancel.trigger('click');
			}

			// Keep the count heading and empty-state in sync with the new comment.
			this.clearEmptyState();
			this.updateCount(data);

			this.notify(data.message, false);

			// Bring the new comment into view, clearing any fixed admin bar / header.
			if ($node.length && $node.offset()) {
				var offset = 24;
				var $adminBar = $('#wpadminbar');
				if ($adminBar.length) {
					offset += $adminBar.outerHeight();
				}
				$('html, body').animate({ scrollTop: $node.offset().top - offset }, 400);
			}
		},

		/**
		 * Wire up inline editing (event-delegated for AJAX-added comments).
		 */
		bindEditing: function () {
			var self = this;

			if (!params.editing_enabled || !params.is_logged_in) {
				return;
			}

			var $scope = $('#comments');
			if (!$scope.length) {
				return;
			}

			// Open the editor.
			$scope.on('click', '.docy-comment-edit-link', function (event) {
				event.preventDefault();
				self.openEditor($(this).closest('.post_comment'));
			});

			// Cancel editing.
			$scope.on('click', '.docy-comment-edit-cancel', function (event) {
				event.preventDefault();
				self.closeEditor($(this).closest('.post_comment'));
			});

			// Save edits.
			$scope.on('click', '.docy-comment-edit-save', function (event) {
				event.preventDefault();
				self.saveEditor($(this).closest('.post_comment'));
			});
		},

		/**
		 * Replace a comment's text with an editing textarea.
		 *
		 * @param {jQuery} $comment The comment list item.
		 */
		openEditor: function ($comment) {
			if (!$comment.length || $comment.hasClass('is-editing')) {
				return;
			}

			var $txt = $comment.find('.comment-txt').first();
			var source = $comment.find('.docy-comment-edit-source').first().val() || '';

			// Preserve the rendered markup so we can restore it on cancel.
			$comment.data('docy-original', $txt.html());

			var $editor = $(
				'<div class="docy-comment-editor">' +
				'<textarea class="docy-comment-edit-field form-control"></textarea>' +
				'<div class="docy-comment-edit-actions">' +
				'<button type="button" class="docy-comment-edit-save fill-brand">' + this.escape(i18n.save || 'Save') + '</button>' +
				'<button type="button" class="docy-comment-edit-cancel">' + this.escape(i18n.cancel || 'Cancel') + '</button>' +
				'</div></div>'
			);

			$editor.find('.docy-comment-edit-field').val(source);
			$txt.hide().after($editor);

			// Hide this comment's own Reply/Edit actions while editing so they
			// don't compete with the editor's Save/Cancel controls. `.first()`
			// keeps nested child-comment actions untouched.
			$comment.find('.comment_actions').first().hide();

			$comment.addClass('is-editing');
			$editor.find('.docy-comment-edit-field').trigger('focus');
		},

		/**
		 * Remove the editor and restore the original comment text.
		 *
		 * @param {jQuery} $comment The comment list item.
		 */
		closeEditor: function ($comment) {
			$comment.find('.docy-comment-editor').remove();
			$comment.find('.comment-txt').first().show();

			// Restore the Reply/Edit actions that were hidden while editing.
			$comment.find('.comment_actions').first().show();

			$comment.removeClass('is-editing');

			// Return focus to the Edit trigger so keyboard users aren't dropped
			// to the top of the document.
			$comment.find('.docy-comment-edit-link').first().trigger('focus');
		},

		/**
		 * Persist an edited comment over AJAX.
		 *
		 * @param {jQuery} $comment The comment list item.
		 */
		saveEditor: function ($comment) {
			var self = this;
			var commentId = $comment.data('comment-id');
			var $editor = $comment.find('.docy-comment-editor');
			var $field = $editor.find('.docy-comment-edit-field');
			var $save = $editor.find('.docy-comment-edit-save');
			var content = $.trim($field.val());

			if (content === '') {
				self.notify(i18n.empty_comment, true);
				$field.trigger('focus');
				return;
			}

			if ($save.prop('disabled')) {
				return;
			}

			$save.prop('disabled', true).text(i18n.saving || 'Saving…');

			$.ajax({
				url: params.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'docy_edit_comment',
					nonce: params.edit_nonce,
					comment_id: commentId,
					comment_content: content
				}
			})
				.done(function (response) {
					if (response && response.success && response.data) {
						var $txt = $comment.find('.comment-txt').first();
						$txt.html(response.data.content_html);
						$comment.find('.docy-comment-edit-source').first().val(response.data.source);
						self.closeEditor($comment);
						self.notify(response.data.message, false);
					} else {
						var msg = response && response.data && response.data.message
							? response.data.message
							: i18n.generic_error;
						self.notify(msg, true);
					}
				})
				.fail(function (xhr) {
					var msg = i18n.generic_error;
					if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						msg = xhr.responseJSON.data.message;
					}
					self.notify(msg, true);
				})
				.always(function () {
					$save.prop('disabled', false).text(i18n.save || 'Save');
				});
		},

		/**
		 * Escape a string for safe insertion as text.
		 *
		 * @param {string} str Input string.
		 * @return {string} Escaped string.
		 */
		escape: function (str) {
			return $('<div>').text(str || '').html();
		}
	};

	$(function () {
		DocyComments.init();
	});
})(jQuery);
