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

/**
 * Checkbox dropdowns used for matter team assignments and similar multi-selects.
 * The native checkbox inputs remain in the form, so keyboard and server-side
 * validation continue to work even when JavaScript is unavailable.
 */
(function () {
    'use strict';

    function setupDropdown(dropdown) {
        if (dropdown.dataset.checkBound === '1') {
            return;
        }

        dropdown.dataset.checkBound = '1';
        var toggle = dropdown.querySelector('[data-check-dropdown-toggle]');
        var summary = dropdown.querySelector('[data-check-summary]');
        var selectAll = dropdown.querySelector('[data-check-all]');
        var search = dropdown.querySelector('[data-check-search]');
        var options = Array.prototype.slice.call(dropdown.querySelectorAll('[data-check-option]'));
        var optionRows = Array.prototype.slice.call(dropdown.querySelectorAll('[data-check-option-label]'));

        function updateSummary() {
            var checked = options.filter(function (option) { return option.checked; });
            var names = checked.map(function (option) {
                var name = option.closest('label').querySelector('strong');
                return name ? name.textContent.trim() : option.value;
            });

            if (!summary) {
                return;
            }

            if (names.length === 0) {
                summary.textContent = dropdown.dataset.placeholder || 'Select';
            } else if (names.length <= 2) {
                summary.textContent = names.join(', ');
            } else {
                summary.textContent = names.slice(0, 2).join(', ') + ' +' + (names.length - 2) + ' more';
            }

            if (selectAll) {
                selectAll.checked = options.length > 0 && checked.length === options.length;
                selectAll.indeterminate = checked.length > 0 && checked.length < options.length;
            }
        }

        function filterOptions() {
            var query = search ? search.value.trim().toLowerCase() : '';
            optionRows.forEach(function (row) {
                row.hidden = query !== '' && (row.dataset.searchText || '').indexOf(query) === -1;
            });
        }

        if (toggle) {
            toggle.addEventListener('click', function (event) {
                event.stopPropagation();
                var open = !dropdown.classList.contains('is-open');
                document.querySelectorAll('[data-check-dropdown].is-open').forEach(function (other) {
                    if (other !== dropdown) {
                        other.classList.remove('is-open');
                        var otherToggle = other.querySelector('[data-check-dropdown-toggle]');
                        if (otherToggle) {
                            otherToggle.setAttribute('aria-expanded', 'false');
                        }
                    }
                });
                dropdown.classList.toggle('is-open', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open && search) {
                    search.focus();
                }
            });
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                options.forEach(function (option) { option.checked = selectAll.checked; });
                updateSummary();
            });
        }

        options.forEach(function (option) {
            option.addEventListener('change', updateSummary);
        });

        if (search) {
            search.addEventListener('input', filterOptions);
        }

        updateSummary();
        filterOptions();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-check-dropdown]').forEach(setupDropdown);

        document.addEventListener('click', function (event) {
            document.querySelectorAll('[data-check-dropdown].is-open').forEach(function (dropdown) {
                if (!dropdown.contains(event.target)) {
                    dropdown.classList.remove('is-open');
                    var toggle = dropdown.querySelector('[data-check-dropdown-toggle]');
                    if (toggle) {
                        toggle.setAttribute('aria-expanded', 'false');
                    }
                }
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('[data-check-dropdown].is-open').forEach(function (dropdown) {
                    dropdown.classList.remove('is-open');
                    var toggle = dropdown.querySelector('[data-check-dropdown-toggle]');
                    if (toggle) {
                        toggle.setAttribute('aria-expanded', 'false');
                    }
                });
            }
        });
    });
})();
