define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    const POSTCODE_LENGTH = 8;

    /**
     * Keep only the digits of a value.
     *
     * @param {String} value
     * @returns {String}
     */
    const onlyDigits = (value) => String(value).replace(/\D/g, '');

    /**
     * Format digits as a Brazilian postcode (CEP): 00000-000.
     *
     * @param {String} value
     * @returns {String}
     */
    const maskPostcode = (value) => {
        const digits = onlyDigits(value).slice(0, POSTCODE_LENGTH);

        return digits.length > 5 ? digits.slice(0, 5) + '-' + digits.slice(5) : digits;
    };

    /**
     * Fineweb_SimulateProductShipping widget: estimates the shipping methods for the product being viewed.
     *
     * @param {Object} config
     * @param {String} config.actionUrl
     * @param {HTMLElement} element
     */
    return function (config, element) {
        const $container = $(element),
            $postcode = $container.find('input[name="simulate[postcode]"]'),
            $button = $container.find('#btn-simulate'),
            $results = $container.find('#shipping-estimate-results');

        /**
         * Replace the results with a single message.
         *
         * @param {String} message
         */
        const showError = (message) => {
            $results.empty().append($('<li class="message error"></li>').text(message)).slideDown();
        };

        /**
         * Render every carrier with its methods. All data is inserted as text, never as markup.
         *
         * @param {Object} carriers - Methods grouped by carrier title
         */
        const showCarriers = (carriers) => {
            $results.empty();

            $.each(carriers, (carrierTitle, methods) => {
                const $methods = $('<ul></ul>');

                $.each(methods, (index, method) => {
                    const $item = $('<li></li>')
                        .append($('<span class="label"></span>').text(method.title + ' - '))
                        .append(document.createTextNode(method.price));

                    if (method.message) {
                        $item.append(document.createTextNode(' - ' + method.message));
                    }

                    $methods.append($item);
                });

                $results.append(
                    $('<li></li>').append($('<span class="title"></span>').text(carrierTitle)).append($methods)
                );
            });

            $results.slideDown();
        };

        /**
         * Ask the server for the shipping methods of the product form and the typed postcode.
         */
        const estimate = () => {
            const digits = onlyDigits($postcode.val());

            $results.slideUp();

            if (digits.length !== POSTCODE_LENGTH) {
                $postcode.addClass('has-error').trigger('focus');
                showError($t('Please enter a valid postcode (8 digits).'));

                return;
            }

            $postcode.removeClass('has-error');

            const formData = $('#product_addtocart_form').serializeArray()
                .filter((field) => field.name !== 'simulate[postcode]');

            formData.push({name: 'simulate[postcode]', value: digits});

            $.ajax({
                type: 'post',
                url: config.actionUrl,
                data: $.param(formData),
                dataType: 'json',
                showLoader: true
            }).done((response) => {
                if (response.error) {
                    showError(response.error.message);
                } else {
                    showCarriers(response);
                }
            }).fail((xhr) => {
                const error = xhr.responseJSON && xhr.responseJSON.error;

                showError(error ? error.message : $t('We could not estimate shipping right now. Please try again.'));
            });
        };

        $postcode.on('input', function () {
            $(this).val(maskPostcode($(this).val()));
        });

        // The field lives inside the add-to-cart form: Enter must estimate shipping, not add the product
        $postcode.on('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                estimate();
            }
        });

        $button.on('click', estimate);
    };
});
