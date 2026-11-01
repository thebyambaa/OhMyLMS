// Define the response handler for Accept.js




jQuery(document).ready(function ($) {

	'use strict';

    // Check if localized parameters are available
    if (typeof ohmylms_authnet_params === 'undefined') {
        console.error('Authorize.Net Error: Localization object (ohmylms_authnet_params) not found.');
        return;
    }

    // Cache jQuery objects for form elements
    var $form = $('form.checkout, form#order_review, form#add_payment_method'); // Common checkout form selectors
    var $paymentForm = $('#authorize-net-payment-form');
    var $cardNumberEl = $('#anet-card-number'); // Should be <input>
    var $expirationDateEl = $('#anet-expiration-date'); // Should be <input> (format MM/YY or MM/YYYY)
    var $cvvEl = $('#anet-cvv'); // Should be <input>
    var $opaqueDataValueInput = $('#authorize_net_opaque_data_value');
    var $opaqueDataDescriptorInput = $('#authorize_net_opaque_data_descriptor');
    var $errorsContainer = $('#authorize-net-payment-errors');

    // Ensure essential form elements are present
    if (!$form.length || !$paymentForm.length || !$cardNumberEl.length || !$expirationDateEl.length || !$cvvEl.length || !$opaqueDataValueInput.length || !$opaqueDataDescriptorInput.length || !$errorsContainer.length) {
        // console.log('Authorize.Net Info: Not on a page with the Authorize.Net payment form or essential elements are missing.');
        return;
    }

    // Find the submit button (this might need adjustment based on specific LMS/theme)
    var $submitButton = $form.find('button[type="submit"], input[type="submit"]');
    
    // Flag to prevent double submission
    var isProcessing = false;
    
    // Flag to track if we've already intercepted this submission
    var hasIntercepted = false;

    function disableSubmitButton(message) {
        $submitButton.prop('disabled', true);
        if (message) {
            $submitButton.data('');
            $submitButton.text('');
            $submitButton.val('');
            // You might want to store original button text and restore it
            $submitButton.data('original-text', $submitButton.text() || $submitButton.val());
            $submitButton.text(message);
            $submitButton.val(message);
        }
    }

    function enableSubmitButton() {
        isProcessing = false;
        hasIntercepted = false;
        // Clear opaque data to ensure fresh tokenization on next attempt
        $opaqueDataDescriptorInput.val('');
        $opaqueDataValueInput.val('');
        $submitButton.prop('disabled', false);
        if ($submitButton.data('original-text')) {
            // set blank first
            $submitButton.text('');
            $submitButton.val('');
            $submitButton.text($submitButton.data('original-text'));
            $submitButton.val($submitButton.data('original-text'));
        }else{
            $submitButton.text('Complete Checkout');
            $submitButton.val('Complete Checkout');
        }
    }

    // Accept.js response handler
    function responseHandler(response) {
        console.log('Authorize.Net: Accept.js response received', response);
        
        if (response.messages.resultCode === "Error") {
            var errorMessages = '';
            for (var i = 0; i < response.messages.message.length; i++) {
                errorMessages += response.messages.message[i].text + '<br />';
            }
            console.error('Authorize.Net: Accept.js returned errors', response.messages.message);
            $errorsContainer.html(ohmylms_authnet_params.error_prefix + errorMessages);
            enableSubmitButton();
        } else if (response.messages.resultCode === "Ok") {
            console.log('Authorize.Net: Accept.js successful, setting opaque data', {
                dataDescriptor: response.opaqueData.dataDescriptor,
                dataValueLength: response.opaqueData.dataValue ? response.opaqueData.dataValue.length : 0
            });
            
            $opaqueDataDescriptorInput.val(response.opaqueData.dataDescriptor);
            $opaqueDataValueInput.val(response.opaqueData.dataValue);

            // Prepare data for AJAX submission
            var formData = $form.serialize();
            formData += '&security=' + ohmylms_authnet_params.checkout_nonce;
            if (formData.indexOf('action=ohmylms_checkout') === -1) {
                formData += '&action=ohmylms_checkout';
            }

            $.ajax({
                type: 'POST',
                url: ohmylms_authnet_params.ajax_url,
                data: formData,
                dataType: 'json',
                success: function(ajaxResponse) {
                    if (ajaxResponse.result === 'success') {
                        if (ajaxResponse.redirect) {
                            window.location.href = ajaxResponse.redirect;
                        } else {
                             // Handle success without redirect if necessary, though checkout usually redirects
                            $errorsContainer.html('Payment successful, but no redirect URL provided.'); // Should not happen
                        }
                    } else {
                        var errorMessage = ajaxResponse.messages || ajaxResponse.message || ohmylms_authnet_params.msg_opaque_data_error;
                        // WC usually returns messages as HTML string. If not, join if it's an array.
                        if (Array.isArray(errorMessage)) {
                            errorMessage = errorMessage.join('<br/>');
                        }
                        $errorsContainer.html(errorMessage);
                        enableSubmitButton();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $errorsContainer.html(ohmylms_authnet_params.error_prefix + ohmylms_authnet_params.msg_opaque_data_error + ' (AJAX: ' + textStatus + ' - ' + errorThrown + ')');
                    enableSubmitButton();
                }
            });
        } else {
            // Unexpected response from Accept.js
            console.error("Authorize.Net Error: Unexpected response from Accept.js", response);
            $errorsContainer.html(ohmylms_authnet_params.error_prefix + ohmylms_authnet_params.msg_opaque_data_error);
            enableSubmitButton();
        }
    }

    // Attach event handler to form submission with high priority (before other handlers)
    // Using jQuery.on with early binding to ensure this runs first
    $form.on('submit.authorizeNet', function(event) {
        // Check if Authorize.Net is the selected payment method
        // This selector might need to be adjusted based on how payment methods are presented (e.g., input name)
		var selectedPaymentMethod = $('input[name="payment_method"]:checked').val();
        if (selectedPaymentMethod !== 'authorize_net') {
            return; // Allow normal submission for other payment methods
		}
		
		// For Authorize.Net, always prevent default form submission
		event.preventDefault();
		event.stopImmediatePropagation(); // Prevent other handlers from running
		
		// Prevent double submission
		if (isProcessing) {
			console.log('Authorize.Net: Already processing payment, preventing duplicate submission');
			return false;
		}

        isProcessing = true;
        disableSubmitButton('Processing...'); // Consider a more i18n friendly message or spinner
        $errorsContainer.empty(); // Clear previous errors

        // Get card data from input fields
        var cardNumber = $cardNumberEl.val().replace(/\s+/g, '');
        var expiration = $expirationDateEl.val().split('/');
        var month = expiration[0] ? expiration[0].trim() : '';
        var year = expiration[1] ? expiration[1].trim() : '';
        if (year.length === 2) {
            year = '20' + year;
        }
        var cardCode = $cvvEl.val();

        // Validate required data before submitting
        if (!cardNumber || cardNumber.length < 13) {
            $errorsContainer.html(ohmylms_authnet_params.msg_card_number_empty);
            enableSubmitButton();
            return;
        }
        if (!month || !year || month.length !== 2 || year.length !== 4) {
            $errorsContainer.html(ohmylms_authnet_params.msg_expiration_date_empty);
            enableSubmitButton();
            return;
        }
        if (!cardCode || cardCode.length < 3) {
            $errorsContainer.html(ohmylms_authnet_params.msg_cvv_empty);
            enableSubmitButton();
            return;
        }

        // Validate credentials are present
        if (!ohmylms_authnet_params.clientKey || !ohmylms_authnet_params.apiLoginId) {
            console.error('Authorize.Net Error: Missing API credentials');
            $errorsContainer.html(ohmylms_authnet_params.error_prefix + 'Payment gateway not properly configured. Please contact support.');
            enableSubmitButton();
            return;
        }

        // Prepare secureData for Accept.js
        var secureData = {
            cardData: {
                cardNumber: cardNumber,
                month: month,
                year: year,
                cardCode: cardCode
            },
            authData: {
                clientKey: ohmylms_authnet_params.clientKey,
                apiLoginID: ohmylms_authnet_params.apiLoginId
            }
        };

        console.log('Authorize.Net: Submitting payment data to Accept.js', {
            cardNumberMasked: cardNumber.replace(/\d(?=\d{4})/g, '*'),
            month: month,
            year: year,
            hasClientKey: !!ohmylms_authnet_params.clientKey,
            hasApiLoginId: !!ohmylms_authnet_params.apiLoginId,
            testmode: ohmylms_authnet_params.testmode,
            acceptJsUrl: ohmylms_authnet_params.acceptJsUrl
        });

        if (typeof Accept !== 'undefined' && typeof Accept.dispatchData === 'function') {
            Accept.dispatchData(secureData, responseHandler);
        } else {
            console.error('Authorize.Net Error: Accept.js or Accept.dispatchData function is not available.');
            $errorsContainer.html(ohmylms_authnet_params.error_prefix + 'Payment gateway script not loaded correctly. Please try again or contact support.');
            enableSubmitButton();
        }
    });

    // Card number formatting (simple, not a full mask)
    $cardNumberEl.on('input', function() {
        let value = $(this).val().replace(/\D/g, '').substring(0, 16);
        let formatted = value.replace(/(.{4})/g, '$1 ').trim();
        $(this).val(formatted);
    });

    // Expiration date formatting (MM/YY)
    $expirationDateEl.on('input', function() {
        let value = $(this).val().replace(/\D/g, '').substring(0, 4);
        if (value.length > 2) {
            value = value.substring(0,2) + '/' + value.substring(2,4);
        }
        $(this).val(value);
    });

    // Simple validation on blur
    $cardNumberEl.on('blur', function() {
        if ($(this).val().replace(/\s/g, '').length < 13) {
            $errorsContainer.html(ohmylms_authnet_params.msg_card_number_empty);
            $(this).addClass('input-error');
        } else {
            $(this).removeClass('input-error');
        }
    });
    $expirationDateEl.on('blur', function() {
        if (!/^\d{2}\/\d{2,4}$/.test($(this).val())) {
            $errorsContainer.html(ohmylms_authnet_params.msg_expiration_date_empty);
            $(this).addClass('input-error');
        } else {
            $(this).removeClass('input-error');
        }
    });
    $cvvEl.on('blur', function() {
        if ($(this).val().length < 3) {
            $errorsContainer.html(ohmylms_authnet_params.msg_cvv_empty);
            $(this).addClass('input-error');
        } else {
            $(this).removeClass('input-error');
        }
    });

    // Inject professional styles for Authorize.Net payment fields if not already present
    if (!document.getElementById('ohmylms-authorize-net-styles')) {
        const style = document.createElement('style');
        style.id = 'ohmylms-authorize-net-styles';
        style.innerHTML = `
        #authorize-net-payment-form {
            max-width: 400px;
            margin: 24px auto 0 auto;
            padding: 24px 24px 12px 24px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            font-family: inherit;
        }
        #authorize-net-payment-form fieldset {
            border: none;
            padding: 0;
            margin: 0 0 12px 0;
        }
        #authorize-net-payment-form .form-row {
            margin-bottom: 18px;
        }
        #authorize-net-payment-form label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #222;
        }
        #authorize-net-payment-form input.authorize-net-field {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 16px;
            background: #f9fafb;
            transition: border-color 0.2s;
        }
        #authorize-net-payment-form input.authorize-net-field:focus {
            border-color: #2563eb;
            outline: none;
            background: #fff;
        }
        #authorize-net-payment-form .input-error {
            border-color: var(--oml-danger, #dc2626) !important;
            background: var(--oml-danger-soft, #fef2f2) !important;
        }
        #authorize-net-payment-errors {
            margin: 10px 0 0 0;
            color: var(--oml-danger, #dc2626);
            font-size: 15px;
            min-height: 20px;
        }
        @media (max-width: 500px) {
            #authorize-net-payment-form {
                padding: 16px 6px 8px 6px;
            }
        }
        `;
        document.head.appendChild(style);
    }
});
