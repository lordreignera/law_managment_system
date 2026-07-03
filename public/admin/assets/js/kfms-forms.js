/**
 * KFMS form helpers.
 *
 * Thousand separators for money/amount inputs across every admin form.
 * Any numeric input whose name looks like a money field (or that carries a
 * data-money attribute) gets grouped with commas for readability. Commas are
 * stripped automatically on submit so the server always receives a clean
 * number. Add data-no-money to opt a field out.
 */
(function () {
    'use strict';

    var MONEY_NAME = /(amount|fee|retainer|price|balance|total|cost|budget|salary|principal|interest|recovered|payment|charge|rate)/i;

    function isMoneyField(input) {
        if (input.hasAttribute('data-no-money')) {
            return false;
        }
        if (input.hasAttribute('data-money')) {
            return true;
        }
        return MONEY_NAME.test(input.getAttribute('name') || '');
    }

    function toPlainNumber(value) {
        return String(value == null ? '' : value).replace(/,/g, '');
    }

    function formatWithSeparators(value) {
        var raw = toPlainNumber(value).trim();

        if (raw === '') {
            return '';
        }

        var negative = raw.charAt(0) === '-';
        raw = raw.replace(/[^0-9.]/g, '');

        if (raw === '') {
            return '';
        }

        var parts = raw.split('.');
        var integerPart = parts[0].replace(/^0+(?=\d)/, '') || '0';
        var grouped = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

        var decimalPart = '';
        if (parts.length > 1) {
            decimalPart = '.' + parts.slice(1).join('').slice(0, 2);
        }

        return (negative ? '-' : '') + grouped + decimalPart;
    }

    function setCaretByDigits(input, formatted, digitsBefore) {
        var pos = 0;
        var seen = 0;
        while (pos < formatted.length && seen < digitsBefore) {
            var ch = formatted.charAt(pos);
            if (ch >= '0' && ch <= '9') {
                seen++;
            }
            pos++;
        }
        try {
            input.setSelectionRange(pos, pos);
        } catch (e) {
            /* input type may not support selection range */
        }
    }

    function enhance(input) {
        if (input.dataset.moneyBound === '1') {
            return;
        }
        input.dataset.moneyBound = '1';

        // Native number inputs cannot render commas, so switch to text.
        if (input.type === 'number') {
            input.type = 'text';
        }
        input.setAttribute('inputmode', 'decimal');
        input.setAttribute('autocomplete', 'off');
        input.value = formatWithSeparators(input.value);

        // Group with commas live, keeping the caret in a sensible spot.
        input.addEventListener('input', function () {
            var previous = input.value;
            var caret = input.selectionStart || 0;
            var digitsBefore = previous.slice(0, caret).replace(/[^0-9]/g, '').length;
            var formatted = formatWithSeparators(previous);

            if (formatted !== previous) {
                input.value = formatted;
                setCaretByDigits(input, formatted, digitsBefore);
            }
        });

        input.addEventListener('blur', function () {
            input.value = formatWithSeparators(input.value);
        });

        var form = input.form;
        if (form && form.dataset.moneyStrip !== '1') {
            form.dataset.moneyStrip = '1';
            form.addEventListener('submit', function () {
                form.querySelectorAll('input[data-money-bound="1"]').forEach(function (bound) {
                    bound.value = toPlainNumber(bound.value);
                });
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('input[type="number"], input[type="text"]').forEach(function (input) {
            if (isMoneyField(input)) {
                enhance(input);
            }
        });
    });
})();
