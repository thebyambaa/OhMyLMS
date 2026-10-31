
(function($){
	"use strict";

	$(function() {
		// Toasts render in the page's ohmylms/ui live region (assets/interactivity/ui/chrome.js).
		function ohMyLMSshowToast( message, type, duration ) {
			document.dispatchEvent( new CustomEvent( 'ohmylms:toast', {
				bubbles: true,
				cancelable: true,
				detail: { message: message, type: type || 'success', duration: duration }
			} ) );
		}

		var OhMyLMS = {
			init: function() {},

			ohMyLMSshowToast: ohMyLMSshowToast,
		}

		const { __ } = wp.i18n;

		OhMyLMS.init();

		$(document).ready(function ($) {



			/**
			 * Initializes an accordion functionality for the specified selector.
			 *
			 * @param {string} selector - A CSS selector string to identify the accordion headers.
			 * @param {boolean} isMultipleOpen - A flag indicating whether multiple accordion items can be open simultaneously.
			 *
			 * This function binds a click event to the specified selector. When an accordion header
			 * is clicked, it toggles the "active" class and slides the associated accordion body open
			 * or closed. If `isMultipleOpen` is false, it will ensure that only one accordion item
			 * remains open at a time by closing all other siblings.
			 */
			function ohMyLMSAccordion(selector, isMultipleOpen = true) {
				$(document).on("click", `${selector} .ohmylms-accordion-head`, function() {
					const $accordionItem = $(this).closest(".ohmylms-accordion-item");

					// Toggle the active class for the clicked item
					$accordionItem.toggleClass("active");

					// Slide toggle the body of the clicked item
					$accordionItem.find(".ohmylms-accordion-body").slideToggle();

					// Change the aria-expanded attribute based on the active state
					const isActive = $accordionItem.hasClass("active");
					$(this).attr("aria-expanded", isActive);

					// If not allowing multiple open, close other items
					if (!isMultipleOpen) {
						$accordionItem.siblings().removeClass("active").find(".ohmylms-accordion-body").slideUp().end()
                		.find(".ohmylms-accordion-head").attr("aria-expanded", false);
					}
				});
			}

			/**
			 * Sets up dynamic tab behavior for a given set of tabs and corresponding content.
			 *
			 * @param {string} tabSelector - A CSS selector string to select the tab elements.
			 * @param {string} contentSelector - A CSS selector string to select the content elements.
			 *
			 * This function adds click event listeners to each tab element. When a tab is clicked,
			 * it removes the 'active' class from all tabs and content elements, then adds the 'active'
			 * class to the clicked tab and its corresponding content element. The corresponding content
			 * is determined by the 'data-target' attribute of the tab.
			 */
			function ohMyLMSTab(tabSelector, contentSelector) {
				const tabs = document.querySelectorAll(tabSelector);
				const contents = document.querySelectorAll(contentSelector);

				function clearActiveClasses() {
					tabs.forEach(t => t.classList.remove('active'));
					contents.forEach(c => c.classList.remove('active'));
				}

				function activateTab(tab) {
					const target = tab.getAttribute('data-target');
					const contentToShow = document.querySelector(target);
					if (contentToShow) {
						tab.classList.add('active');
						contentToShow.classList.add('active');
					}
				}

				// Add click event listeners for all tabs
				tabs.forEach(tab => {
					tab.addEventListener('click', function() {
						clearActiveClasses();
						activateTab(tab);
					});
				});

				if (tabs.length > 0) {
					clearActiveClasses();
					activateTab(tabs[0]);
				}

			}

			// -----creator lms table responsive toggle------
			if($(window).width() < 768) {
				$(document).on("click", ".ohmylms-table .ohmylms-td-handle", function() {
					$(this).parent('.ohmylms-tr').toggleClass('active');
					$(this).parent('.ohmylms-tr').find('.ohmylms-mobile-td').slideToggle();
				});
			}


			//-----Initialize single lesson sidebar tab-----
			ohMyLMSTab('.ohmylms-lesson-tab-nav li', '.ohmylms-lesson-tab-content .ohmylms-lesson-single-tab-content');

			/*
			* Initialize accordions for the single lesson sidebar
			*
			* Parameters:
			* (1) accordionWrapperClass: The CSS class for the accordion wrapper element.
			* (2) allowMultipleOpen: Boolean value indicating whether multiple accordion items can be open at the same time (true or false).
			*/
			ohMyLMSAccordion('.ohmylms-lesson-sidebar-accordion', false);


			// -----complete lesson-----
			$(document).on('click', '#ohmylms-completed-lesson', function(e) {
				$(this).prop('disabled', true);

				if (!$(this).is(':checked')) {
					return '';
				}

				var lessonID = $("#ohmylms-lesson-id").val();
				var studentID = $("#ohmylms-student-id").val();
				$.ajax({
					url: ohmylms_frontend_params.ajax_url,
					type: 'POST',
					data: {
						action	: 'ohmylms_lesson_completed',
						lesson_id	: lessonID,
						nonce	: ohmylms_frontend_params.lesson_completed_nonce
					},
					success: function(response) {
						if (response.success) {
							ohMyLMSshowToast(response.data.message);

							setTimeout(function() {
								if( response.data.next_content_link){
									window.location.href = response.data.next_content_link;
								}else{
									location.reload();
								}
							}, 500);

						} else {
							ohMyLMSshowToast(response.data.message, 'danger');
						}
					}
				});
			});

			// ----- Submit Assignment -----
			$(document).on('submit', '.ohmylms-assignment-form', function (e) {
				e.preventDefault(); // Prevent default form submission

				var form = $(this);
				var submitButton = form.find('button[type="submit"]');
				submitButton.prop('disabled', true);

				 // Create a new FormData object
				 var formData = new FormData();

				 // Append form fields (text, textarea, select) to FormData
				 form.find('input, textarea, select').each(function () {
					 var input = $(this);
					 var name = input.attr('name'); // Get the name attribute
					 var value = input.val(); // Get the value

					 if (name) {
						 if (input.attr('type') === 'file') {
							 var files = input[0].files; // Get the file(s)
							 if (files.length > 0) {
								 for (var i = 0; i < files.length; i++) {
									 formData.append(name, files[i]); // Append each file
								 }
							 }
						 } else {
							 formData.append(name, value); // For text inputs, append the value
						 }
					 }
				 });


				formData.append('action', 'ohmylms_save_assignment_submission_file');
				formData.append('nonce', ohmylms_frontend_params.assignment_submission_nonce);

				$.ajax({
					url: ohmylms_frontend_params.ajax_url,
					type: 'POST',
					data: formData,
					processData: false, // Do not process the data
					contentType: false, // Do not set content type, FormData will handle it
					success: function (response) {
						if (response.success) {
							ohMyLMSshowToast(response.data.message, 'success');
							$('.ohmylms-assignment-submit').hide();
							setTimeout(function () {
								if (response.data.redirect_url) {
									window.location.href = response.data.redirect_url;
								} else {
									location.reload();
								}
							}, 500);
						} else {
							$('.assignment-submit-alert span').text(response.data.message); // Update the error message
                			$('.assignment-submit-alert').css('display', 'flex'); // Show the error message
						}
						submitButton.prop('disabled', false);
					},
					error: function (xhr, status, error) {
						$('.assignment-submit-alert span').text('An error occurred. Please try again.'); // Update the error message
                		$('.assignment-submit-alert').css('display', 'flex'); // Show the error message
						submitButton.prop('disabled', false);
					},
				});

			});

			//------start assignment page attachment submition journey-----
			var isTimerStart = false,
				remainingQuizTime = 0;
			//------start assignment page attachment submition journey-----
			$(document).on('click','.ohmylms-lesson-navigation .start-submit-assignment',function (e) {
				//------show timer and call ohMyLmsCountdown() function for countdown-----

				isTimerStart = true;
				let remainingTime = $('input[name="ohmylms_assignment_deadline"]').val();
				let time =  remainingTime ? remainingTime : $('.submission-time-limit .timer-display').attr('data-timer');
				$('.ohmylms-timer .progress-inner').css('width', 'calc(100% - ' + 0 + '%)');
				ohMyLmsCountdown(time,'assignment');
				$(this).hide();
				$(this).siblings('a.skip').hide();

				$(".assignment-navigation .default-navigation").css('display','flex');
				$(".content-type-assignment .ohmylms-assignment-submit").show();

			})


			// ------assignment submission attachment(for first time) on lesson single page------
			$('#submission-file').on('change', function() {
				let file = this.files[0];

				let uploadLimit = $(this).attr('upload-limit');
				let fileSize = (file.size / (1024 * 1024)).toFixed(2);


				if (file) {
					var listItem = $('<li>');
					var resourceInfo = $('<div class="ohmylms-single-resource-info">');
					resourceInfo.append('<span class="resource-icon"><svg width="14" height="17" fill="none" viewBox="0 0 14 17" xmlns="http://www.w3.org/2000/svg"><path fill="#A1A1AA" fillRule="evenodd" d="M13.582 14.67a2.044 2.044 0 01-2.043 2.046H2.625A2.044 2.044 0 01.582 14.67V2.763A2.045 2.045 0 012.625.716h6.378c.345 0 .675.138.919.383l3.279 3.284a1.3 1.3 0 01.381.921v9.366zm-1.114 0V5.304a.19.19 0 00-.054-.132l-3.28-3.285a.186.186 0 00-.13-.054h-6.38a.93.93 0 00-.928.93V14.67a.932.932 0 00.929.93h8.914a.93.93 0 00.929-.93z" clipRule="evenodd"/><path fill="#A1A1AA" fillRule="evenodd" d="M8.75 1.647a.558.558 0 111.114 0V4.25c0 .103.083.186.186.186h2.6a.558.558 0 010 1.116h-2.6a1.3 1.3 0 01-1.3-1.302V1.647zM4.116 8.16a.558.558 0 010-1.117h5.943a.558.558 0 010 1.116H4.116zm0 2.603a.558.558 0 010-1.116h5.943a.558.558 0 010 1.116H4.116zm0 2.604a.558.558 0 010-1.116h3.343a.558.558 0 010 1.116H4.116z" clipRule="evenodd"/></svg></span>');
					resourceInfo.append('<span class="resource-name">' + file.name + '</span>');
					resourceInfo.append('<span class="resource-size">' + (file.size / 1024 / 1024).toFixed(2) + ' MB</span>');
					listItem.append(resourceInfo);
					listItem.append('<a href="#" class="resource-action remove-resource-action" title="Remove"><svg width="15" height="15" fill="none" viewBox="0 0 15 15" xmlns="http://www.w3.org/2000/svg"><path fill="#FF6F6F" stroke="#FF6F6F" stroke-width=".2" d="M13.504 4.313h-.053c-3.527-.32-7.048-.44-10.535-.12l-1.36.12c-.28.025-.527-.157-.554-.41-.026-.255.174-.472.447-.497l1.36-.12c3.548-.327 7.141-.2 10.742.12.273.025.473.248.447.496-.02.236-.24.411-.494.411z"/><path fill="#FF6F6F" stroke="#FF6F6F" stroke-width=".2" d="M5.169 3.703c-.027 0-.054 0-.087-.006-.267-.043-.454-.278-.407-.52l.147-.792C4.928 1.805 5.075 1 6.629 1h1.747c1.56 0 1.707.834 1.807 1.39l.146.787c.047.248-.14.483-.406.52-.274.042-.534-.127-.574-.37l-.147-.785c-.093-.526-.113-.629-.82-.629H6.635c-.706 0-.72.085-.82.623l-.153.786c-.04.224-.253.38-.493.38zM9.643 14H5.362c-2.327 0-2.42-1.167-2.494-2.11L2.435 5.8c-.02-.247.193-.465.467-.483.28-.012.513.175.533.423l.433 6.09c.074.918.1 1.263 1.494 1.263h4.28c1.4 0 1.428-.345 1.494-1.264l.434-6.089c.02-.248.26-.435.533-.423.273.018.487.23.467.484l-.434 6.088c-.073.944-.166 2.11-2.493 2.11z"/><path fill="#FF6F6F" stroke="#FF6F6F" stroke-width=".2" d="M8.61 10.675H6.388c-.274 0-.5-.206-.5-.454 0-.248.226-.453.5-.453h2.22c.273 0 .5.205.5.453 0 .248-.227.454-.5.454zm.56-2.419H5.836c-.273 0-.5-.206-.5-.454 0-.248.227-.453.5-.453H9.17c.273 0 .5.205.5.453 0 .248-.227.454-.5.454z"/></svg></a>');
					$('#ohmylms-submission-file-list').append(listItem);

					$('.ohmylms-form-group.submission-file').hide();
					$('.ohmylms-form-group.attached-resources').show();

					if ( Number(uploadLimit) < Number(fileSize) ) {
						$(".submission-max-file-limit-alert").show();
						$('.ohmylms-form-group.submission-submit .ohmylms-button').prop('disabled', true);
					}else {
						$(".submission-max-file-limit-alert").hide();
						$('.ohmylms-form-group.submission-submit .ohmylms-button').prop('disabled', false);
					}
				}
			});

			//------add attachment to assignment after adding 1 attachment------
			$(document).on('change', '#ohmylms-add-new-attachment', function() {
				let files = this.files;

				if (files) {
					let file = this.files[0];

					var listItem = $('<li>');
					var resourceInfo = $('<div class="ohmylms-single-resource-info">');
					resourceInfo.append('<span class="resource-icon"><svg width="14" height="17" fill="none" viewBox="0 0 14 17" xmlns="http://www.w3.org/2000/svg"><path fill="#A1A1AA" fillRule="evenodd" d="M13.582 14.67a2.044 2.044 0 01-2.043 2.046H2.625A2.044 2.044 0 01.582 14.67V2.763A2.045 2.045 0 012.625.716h6.378c.345 0 .675.138.919.383l3.279 3.284a1.3 1.3 0 01.381.921v9.366zm-1.114 0V5.304a.19.19 0 00-.054-.132l-3.28-3.285a.186.186 0 00-.13-.054h-6.38a.93.93 0 00-.928.93V14.67a.932.932 0 00.929.93h8.914a.93.93 0 00.929-.93z" clipRule="evenodd"/><path fill="#A1A1AA" fillRule="evenodd" d="M8.75 1.647a.558.558 0 111.114 0V4.25c0 .103.083.186.186.186h2.6a.558.558 0 010 1.116h-2.6a1.3 1.3 0 01-1.3-1.302V1.647zM4.116 8.16a.558.558 0 010-1.117h5.943a.558.558 0 010 1.116H4.116zm0 2.603a.558.558 0 010-1.116h5.943a.558.558 0 010 1.116H4.116zm0 2.604a.558.558 0 010-1.116h3.343a.558.558 0 010 1.116H4.116z" clipRule="evenodd"/></svg></span>');
					resourceInfo.append('<span class="resource-name">' + file.name + '</span>');
					resourceInfo.append('<span class="resource-size">' + (file.size / 1024 / 1024).toFixed(2) + ' MB</span>');
					listItem.append(resourceInfo);
					listItem.append('<a href="#" class="resource-action remove-resource-action" title="Remove"><svg width="15" height="15" fill="none" viewBox="0 0 15 15" xmlns="http://www.w3.org/2000/svg"><path fill="#FF6F6F" stroke="#FF6F6F" stroke-width=".2" d="M13.504 4.313h-.053c-3.527-.32-7.048-.44-10.535-.12l-1.36.12c-.28.025-.527-.157-.554-.41-.026-.255.174-.472.447-.497l1.36-.12c3.548-.327 7.141-.2 10.742.12.273.025.473.248.447.496-.02.236-.24.411-.494.411z"/><path fill="#FF6F6F" stroke="#FF6F6F" stroke-width=".2" d="M5.169 3.703c-.027 0-.054 0-.087-.006-.267-.043-.454-.278-.407-.52l.147-.792C4.928 1.805 5.075 1 6.629 1h1.747c1.56 0 1.707.834 1.807 1.39l.146.787c.047.248-.14.483-.406.52-.274.042-.534-.127-.574-.37l-.147-.785c-.093-.526-.113-.629-.82-.629H6.635c-.706 0-.72.085-.82.623l-.153.786c-.04.224-.253.38-.493.38zM9.643 14H5.362c-2.327 0-2.42-1.167-2.494-2.11L2.435 5.8c-.02-.247.193-.465.467-.483.28-.012.513.175.533.423l.433 6.09c.074.918.1 1.263 1.494 1.263h4.28c1.4 0 1.428-.345 1.494-1.264l.434-6.089c.02-.248.26-.435.533-.423.273.018.487.23.467.484l-.434 6.088c-.073.944-.166 2.11-2.493 2.11z"/><path fill="#FF6F6F" stroke="#FF6F6F" stroke-width=".2" d="M8.61 10.675H6.388c-.274 0-.5-.206-.5-.454 0-.248.226-.453.5-.453h2.22c.273 0 .5.205.5.453 0 .248-.227.454-.5.454zm.56-2.419H5.836c-.273 0-.5-.206-.5-.454 0-.248.227-.453.5-.453H9.17c.273 0 .5.205.5.453 0 .248-.227.454-.5.454z"/></svg></a>');

					$(this).parents(".ohmylms-form-group.attached-resources").find("#ohmylms-submission-file-list").append(listItem);
				}
			});

			//------delete attachment file on assignment page------
			$(document).on('click', '.remove-resource-action', function(e) {
				e.preventDefault();
				$(this).closest('li').remove();

				let attachedFileLength = $("#ohmylms-submission-file-list > li").length;

				if (attachedFileLength == 0) {
					$('.ohmylms-assignment-form input[type="file"]').val('');
					$('.ohmylms-form-group.submission-file').show();
					$('.ohmylms-form-group.attached-resources').hide();
					$('.ohmylms-form-group.submission-submit .ohmylms-button').prop('disabled', true);
				}
			});
			//------end assignment page attachment submition journey-----


			//----start quiz textarea auto resize----
			$('.textarea-auto-resize').on('input', function() {
				$(this).css('height', 'auto');
				$(this).css('height', this.scrollHeight + 'px');
			});


			// ------check character limit----
			$('.ohmylms-has-character-limit').on('input', function() {
				const maxChars = parseInt($(this).attr('data-limit'));
				const currentLength = $(this).val().length;

				$(this).siblings('.ohmylms-character-limit-hints > span').text(`${currentLength}`);

				// Check if the current length exceeds the limit
				if (currentLength > maxChars) {
					$(this).siblings('.ohmylms-character-limit-hints').addClass('limit-reached').text('You have reached the maximum character limit!');
					$(this).val($(this).val().substring(0, maxChars));
				} else {
					$(this)
					.siblings('.ohmylms-character-limit-hints')
					.removeClass('limit-reached')
					.html(`<span>${currentLength}</span>/${maxChars}`);

				}

			});

			function ohMyLmsCountdown( totalMinutes, type = 'quiz' ) {

				let totalTime = Math.round(parseFloat(totalMinutes) * 60); // Convert minutes to seconds
				let remainingTime = totalTime;

				if ('assignment' === type) {
					let contentId = $('input[name="ohmylms_assignment_id"]').val();
					saveRemainingTime(contentId, ohmylms_frontend_params.current_student_id, 'assignment', remainingTime);
				}

				// Update the timer every second
				let timerInterval = setInterval(function () {
					// Check if the remaining time has reached zero
					if (remainingTime <= 0) {
						remainingTime = 0;
						clearInterval(timerInterval);
						// $('.ohmylms-quiz .ohmylms-quiz-timeup-text').show();
						// $('.ohmylms-quiz-footer-right').remove();
						let quiz_id = $('.ohmylms_quiz_id').val();
						let attempt_id = $('.quiz_attempt_id').val();
						saveQuizExiSubmit(quiz_id, attempt_id);
					}

					// Calculate days, hours, minutes, and seconds
					let days = Math.floor(remainingTime / 86400); // 1 day = 86400 seconds
					let hours = Math.floor((remainingTime % 86400) / 3600);
					let minutes = Math.floor((remainingTime % 3600) / 60);
					let seconds = remainingTime % 60;

					// Format time dynamically
					let formattedTime = '';

					if (days > 0) {
						formattedTime += (days < 10 ? '0' : '') + days + 'd ';
					}
					if (hours > 0 || days > 0) {
						formattedTime += (hours < 10 ? '0' : '') + hours + 'h ';
					}
					formattedTime += (minutes < 10 ? '0' : '') + minutes + 'm ';
					formattedTime += (seconds < 10 ? '0' : '') + seconds + 's';

					$('.ohmylms-timer .timer-display').text(formattedTime);

					// Calculate the percentage of time elapsed for the progress bar
					let progressPercent = ((totalTime - remainingTime) / totalTime) * 100;
					$('.ohmylms-timer .progress-inner').css('width', 'calc(100% - ' + progressPercent + '%)');
					$('.submission-time-limit').show();
					// Decrease the remaining time by 1 second
					remainingTime--;
				}, 1000);
			}

            function saveQuizExiSubmit(contentId, attemptId) {
                const form = $('.ohmylms-quiz-form').closest('form');
                const data = form.serializeArray().filter(field => field.name !== 'action');
                data.push({name:'action', value:'ohmylms_quiz_exit_submission'},
                    {name:'content_id', value:contentId}, {name:'attempt_id', value:attemptId},
                    {name:'nonce', value:ohmylms_frontend_params.quiz_exit_submission});
                $.ajax({url:ohmylms_frontend_params.ajax_url, type:'POST', data:data,
                    success:function(response) { if (response.success && response.data.url) window.location.href=response.data.url; },
                    error:function() {
                        $('.ohmylms-quiz-timeup-text').text('Submission failed. Your answers remain on this page. Please press Submit to retry.').show();
                        form.find('.quiz-submit').show();
                    }
                });
            }

			function saveRemainingTime( contentId, studentId, type, remainingTime ){

				$.ajax({
					url: ohmylms_frontend_params.ajax_url,
					type: 'POST',
					data: {
						action	: 'ohmylms_save_remaining_time',
						content_id : contentId,
						remaining_time : remainingTime,
						type	    : type,
						nonce	: ohmylms_frontend_params.save_remaining_time
					}
				});
			}

			if( $('input[name="ohmylms_assignment_deadline"]').length > 0 ){

				let remainingTime = $('input[name="ohmylms_assignment_deadline"]').val();
				let time =  remainingTime ? remainingTime : $('.submission-time-limit .timer-display').attr('data-timer');

				let totalTime = Math.round(parseFloat(time) * 60); // Convert minutes to seconds

					// Calculate deadline date
				let deadline = new Date();
				deadline.setSeconds(deadline.getSeconds() + totalTime);
				// Display deadline for assignment
				let deadlineStr = deadline.toLocaleString('en-US', {
					year: 'numeric',
					month: 'long',
					day: 'numeric',
					hour: '2-digit',
					minute: '2-digit'
				});
				$('.deadline').text('Deadline: ' + deadlineStr);
			}



			// ----------Protect content from single lesson, quiz and assignment page------
			if (ohmylms_frontend_params.is_creator_page && ohmylms_frontend_params.content_protection === 'yes') {

				// Add a hidden overlay to interfere with screen recording
				$("body").append('<div class="ohmylms-screenshot-protection"></div>');

				// Blur screen when user switches tabs
				$(window).on("blur", function () {
					var activeElement = document.activeElement;
					if (activeElement && (activeElement.tagName === 'IFRAME' || activeElement.tagName === 'WEBVIEW') || activeElement.closest('iframe, webview') || activeElement.classList.contains('single-ohmylms-lesson')) {
						// Don't blur if focus moved to an iframe (likely external video)
						return;
					} 
					$("body").css("filter", "blur(20px)");
				}).on("focus", function () {
					$("body").css("filter", "none");
				});

				// Disable right-click
				$(document).on("contextmenu", function (e) {
					return false;
				});

				// Disable Copy & Cut
				$(document).on("copy cut", function (e) {
					e.preventDefault();
				});

				// Disable Developer Tools & Screenshot Keys - Cross Browser Compatible
				$(document).keydown(function (e) {
					// Get event object for cross-browser compatibility
					e = e || window.event;
					var keyCode = e.keyCode || e.which;
					var key = e.key || String.fromCharCode(keyCode);
					var ctrlKey = e.ctrlKey || e.metaKey; // metaKey for Mac Cmd
					var shiftKey = e.shiftKey;
					var altKey = e.altKey;

					// Helper function to prevent event and blur screen
					function preventAndBlur() {
						if (e.preventDefault) {
							e.preventDefault();
						} else {
							e.returnValue = false; // IE8 and below
						}
						if (e.stopPropagation) {
							e.stopPropagation();
						} else {
							e.cancelBubble = true; // IE8 and below
						}
						$("body").css({
							"filter": "blur(20px)",
							"-webkit-filter": "blur(20px)", // Safari
							"-moz-filter": "blur(20px)", // Firefox
							"-ms-filter": "blur(20px)" // IE
						});
						return false;
					}

					// Helper function to just prevent event
					function preventOnly() {
						if (e.preventDefault) {
							e.preventDefault();
						} else {
							e.returnValue = false; // IE8 and below
						}
						if (e.stopPropagation) {
							e.stopPropagation();
						} else {
							e.cancelBubble = true; // IE8 and below
						}
						return false;
					}

					// Check key combinations using both key property and keyCode for maximum compatibility

					// F12 Developer Tools (keyCode: 123)
					if (keyCode === 123) {
						return preventAndBlur();
					}

					// F1 Help (keyCode: 112) - can lead to dev tools
					if (keyCode === 112) {
						return preventOnly();
					}

					// F2 (keyCode: 113) - Sometimes used for renaming/editing
					if (keyCode === 113) {
						return preventOnly();
					}

					// F3 Find (keyCode: 114)
					if (keyCode === 114) {
						return preventOnly();
					}

					// F4 Address bar (keyCode: 115)
					if (keyCode === 115) {
						return preventOnly();
					}

					// F5 Refresh (keyCode: 116) - Allow but monitor
					// F6 Address bar focus (keyCode: 117)
					if (keyCode === 117) {
						return preventOnly();
					}

					// Ctrl/Cmd combinations
					if (ctrlKey || e.metaKey) {
						// View Source: Ctrl+U / Cmd+U (keyCode: 85)
						if (keyCode === 85 || key.toLowerCase() === 'u') {
							return preventAndBlur();
						}

						// Save As: Ctrl+S / Cmd+S (keyCode: 83)
						if (keyCode === 83 || key.toLowerCase() === 's') {
							return preventAndBlur();
						}

						// Find: Ctrl+F / Cmd+F (keyCode: 70) - Allow for user convenience
						// if (keyCode === 70 || key.toLowerCase() === 'f') {
						//     return preventOnly();
						// }

						// Print: Ctrl+P / Cmd+P (keyCode: 80)
						if (keyCode === 80 || key.toLowerCase() === 'p') {
							return preventAndBlur();
						}

						// Select All: Ctrl+A / Cmd+A (keyCode: 65) - Allow for user convenience
						// New Tab: Ctrl+T / Cmd+T (keyCode: 84) - Can't prevent in browser
						// New Window: Ctrl+N / Cmd+N (keyCode: 78) - Can't prevent in browser

						// With Shift key
						if (shiftKey) {
							// Inspector: Ctrl+Shift+I / Cmd+Shift+I (keyCode: 73)
							if (keyCode === 73 || key.toLowerCase() === 'i') {
								return preventAndBlur();
							}

							// Console: Ctrl+Shift+J / Cmd+Shift+J (keyCode: 74)
							if (keyCode === 74 || key.toLowerCase() === 'j') {
								return preventAndBlur();
							}

							// Element Selector: Ctrl+Shift+C / Cmd+Shift+C (keyCode: 67)
							if (keyCode === 67 || key.toLowerCase() === 'c') {
								return preventAndBlur();
							}

							// Firefox Console: Ctrl+Shift+K (keyCode: 75)
							if (keyCode === 75 || key.toLowerCase() === 'k') {
								return preventAndBlur();
							}

							// Network Tab: Ctrl+Shift+E (keyCode: 69)
							if (keyCode === 69 || key.toLowerCase() === 'e') {
								return preventAndBlur();
							}

							// Performance Tab: Ctrl+Shift+P (keyCode: 80)
							if (keyCode === 80 || key.toLowerCase() === 'p') {
								return preventAndBlur();
							}

							// Memory Tab: Ctrl+Shift+M (keyCode: 77)
							if (keyCode === 77 || key.toLowerCase() === 'm') {
								return preventAndBlur();
							}

							// Sources Tab: Ctrl+Shift+S (keyCode: 83)
							if (keyCode === 83 || key.toLowerCase() === 's') {
								return preventAndBlur();
							}

							// Delete: Ctrl+Shift+Delete (keyCode: 46)
							if (keyCode === 46) {
								return preventAndBlur();
							}
						}

						// With Alt key (Option on Mac)
						if (altKey || e.altKey) {
							// Mac Safari/Chrome Inspector: Cmd+Option+I
							if (keyCode === 73 || key.toLowerCase() === 'i') {
								return preventAndBlur();
							}

							// Mac Safari/Chrome Console: Cmd+Option+J
							if (keyCode === 74 || key.toLowerCase() === 'j') {
								return preventAndBlur();
							}

							// Mac Safari/Chrome Element Selector: Cmd+Option+C
							if (keyCode === 67 || key.toLowerCase() === 'c') {
								return preventAndBlur();
							}

							// Mac Activity Monitor: Cmd+Option+Esc
							if (keyCode === 27) {
								return preventOnly();
							}
						}
					}

					// Alt key combinations (for older browsers and different OS)
					if (altKey) {
						// Alt+F4 - Close window
						if (keyCode === 115) {
							return preventOnly();
						}

						// Alt+Tab - Switch applications (can't really prevent but try)
						if (keyCode === 9) {
							return preventOnly();
						}
					}

					// Screenshots and Print Screen
					// Print Screen (keyCode: 44)
					if (keyCode === 44 || key === 'PrintScreen') {
						return preventAndBlur();
					}

					// Mac Screenshots: Cmd+Shift+3, Cmd+Shift+4, Cmd+Shift+5
					if (e.metaKey && shiftKey) {
						if (keyCode === 51 || keyCode === 52 || keyCode === 53 ||
							key === '3' || key === '4' || key === '5') {
							return preventAndBlur();
						}
						// Any other Cmd+Shift combination on Mac (screenshots)
						return preventAndBlur();
					}

					// Windows Snipping Tool: Windows+Shift+S
					if (e.key === 'Meta' && shiftKey && (keyCode === 83 || key.toLowerCase() === 's')) {
						return preventAndBlur();
					}

					// Context Menu key (keyCode: 93)
					if (keyCode === 93) {
						return preventOnly();
					}

					// Escape key in some contexts
					if (keyCode === 27 && (ctrlKey || shiftKey)) {
						return preventOnly();
					}

					// Additional browser-specific shortcuts

					// Chrome specific
					// Ctrl+Shift+Delete (Clear browsing data)
					if (ctrlKey && shiftKey && keyCode === 46) {
						return preventAndBlur();
					}

					// Firefox specific
					// Ctrl+Shift+A (Add-ons)
					if (ctrlKey && shiftKey && (keyCode === 65 || key.toLowerCase() === 'a')) {
						return preventOnly();
					}

					// Safari specific (Mac)
					// Cmd+, (Preferences)
					if (e.metaKey && keyCode === 188) {
						return preventOnly();
					}

					// Edge/IE specific
					// F11 (Full screen toggle) - might be okay to allow
					// F7 (Caret browsing in IE)
					if (keyCode === 118) {
						return preventOnly();
					}
				});

				function isDevToolsOpen() {
					const threshold = 200; // difference when DevTools docked
					return (
						window.outerWidth - window.innerWidth > threshold ||
						window.outerHeight - window.innerHeight > threshold
					);
				}

				function handleDevToolsOpen() {
					alert("This content is protected and can't be viewed with Developer Tools.\nPlease close DevTools and visit again.");

					try {
						// First try to close the tab
						window.open('', '_self');
						window.close();
					} catch (e) {
						// fallback
						window.location.replace("about:blank");
					}

					// Extra safety: redirect anyway after short delay
					setTimeout(() => {
						if (!window.closed) {
						window.location.replace("about:blank");
						}
					}, 300);
				}

				if (isDevToolsOpen()) {
					handleDevToolsOpen();
				}



				function detectLoomExtension() {
					if ($("#loom-extension-mv3-id").length) {
						$("body").css("filter", "blur(20px)"); // Blur the screen
					}
				}
				// Run detection every second
				setInterval(detectLoomExtension, 1000);
			}

			//--------course lesson video play button------
			$(document).on("click", ".ohmylms-video-player-play", function(e) {
                e.preventDefault();
                $(this).closest(".ohmylms-video-player-cover").fadeOut();
                const video = $(this).closest(".ohmylms-video-player").find('.the-video')[0];
                if (video) video.play();
            });

			// ------lesson video accessibility------
			$(".ohmylms-video-player").on("focus keydown", function (event) {
				$(this).children(".ohmylms-video-player-play").trigger("focus");

				if (event.type === "click" || event.key === "Enter" || event.key === " ") {
					event.preventDefault();
					var video = $(this).children(".the-video")[0];

					video.play();
					video.setAttribute("aria-live", "polite");
					$(this).children(".ohmylms-video-player-cover").hide();

					setTimeout(() => {
						$(".the-video").trigger("focus");
					}, 300);
				}
			});

			$(".ohmylms-video-player-play").on("click keydown", function (event) {
				if (event.type === "click" || event.key === "Enter" || event.key === " ") {
					event.preventDefault();

					var video = $(this).parents(".ohmylms-video-player").find(".the-video")[0];

					video.play();
					video.setAttribute("aria-live", "polite");
					$(this).parent(".ohmylms-video-player-cover").hide();

					setTimeout(() => {
						$(".the-video").trigger("focus");
					}, 300);
				}
			});


			// ------lesson mark as completed checkbox accessibility------
			$(".ohmylms-lesson-navigation .ohmylms-checkbox").on("keydown", function (event) {
				if (event.key === "Enter") {
					event.preventDefault();
					var checkbox = $(this).children('input[type="checkbox"]');

					if (!checkbox.prop("disabled")) {
						checkbox.trigger("click");
						checkbox.prop("checked", !checkbox.prop("checked"));
					}

				}
			});


			// ------couse details assignment and sidebar lesson accordion accessibility------
			$(".ohmylms-lesson-sidebar-accordion .ohmylms-accordion-head, .ohmylms-assignment-accordion .ohmylms-accordion-head").on("keydown", function (event) {
				if (event.key === "Enter" || event.key === " ") {
					event.preventDefault();
					var $this = $(this);
					var expanded = $this.attr("aria-expanded") === "true";

					$(".ohmylms-accordion-item").removeClass("active");
					$this.parent(".ohmylms-accordion-item").addClass("active");

					// Close all accordions
					$(".ohmylms-accordion-head").attr("aria-expanded", "false");
					$(".ohmylms-accordion-body").slideUp().attr("aria-hidden", "true");


					// Toggle the clicked one
					if (!expanded) {
						$this.parent(".ohmylms-accordion-item").addClass("active");
						$this.attr("aria-expanded", "true");
						$this.next(".ohmylms-accordion-body").slideDown().attr("aria-hidden", "false");
					}else {
						$(".ohmylms-accordion-item").removeClass("active");
					}
				}
			});

			// ------assignment attachment accessibility------
			$(".ohmylms-assignment-submit .ohmylms-file-upload").on("keydown", function (event) {
				if (event.key === "Enter" || event.key === " ") {
					event.preventDefault();
					$(".ohmylms-assignment-submit #submission-file").trigger("click");
				}
			});

			$(".ohmylms-assignment-submit #submission-file").on("change", function () {
				let fileName = $(this).val().split("\\").pop();
				if (fileName) {
					$(".ohmylms-assignment-submit #submission-max-file-limit").text("Selected file: " + fileName);
				}
			});

			// -----start wrapAndStyleImages image formatter------
			/**
			 * Wrap and style images inside `.ohmylms-wysiwyg-content`.
			 * - Wraps <img> with a <div class="image-wrapper">
			 * - Sets image width from `data-width` (0%–100%)
			 * - Aligns image using `data-align` (left, right, center)
			 * - Applies margin-based alignment (no float)
			 */
			function wrapAndStyleImages() {
				const $container = $('.ohmylms-wysiwyg-content')

				$container.find('img[data-width][data-align]').each(function () {
					const $img = $(this)

					// Skip if already wrapped
					if ($img.parent().hasClass('image-wrapper')) return

					const width = $img.attr('data-width') || '100%'
					const align = $img.attr('data-align') || 'center'

					// Create wrapper div
					const $wrapper = $('<div class="image-wrapper"></div>').attr('data-align', align)

					// Set image CSS styles based on alignment
					$img.css({
						width: `${width}`,
						display: 'block',
						'margin-left': align === 'right' ? 'auto' : align === 'center' ? 'auto' : '0',
						'margin-right': align === 'left' ? 'auto' : align === 'center' ? 'auto' : '0',
					})

					// Apply !important manually to specific style(s)
					$img[0].style.setProperty('width', width, 'important')


					// Wrap the image
					$img.wrap($wrapper)
				})
			}
			wrapAndStyleImages()
			// -----end wrapAndStyleImages image formatter------


			// -----start lesson prerequisite access modal------

			$(document).on('click', '.ohmylms-access-denied-inner, .ohmylms-access-denied-close', function() {
				$('.ohmylms-access-denied-modal').fadeOut().attr('aria-hidden', 'true');
			});

			$(document).on('click', '.ohmylms-access-denied-modal-content', function(e) {
				e.stopPropagation();
			});
			// -----end lesson prerequisite access modal------

		});
		// ---end doument ready---


	});

})(jQuery);
