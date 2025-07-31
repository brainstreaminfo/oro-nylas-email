// Nylas Bundle - Simple JavaScript (Standalone Version)
// This script works without RequireJS and can be loaded directly

(function(window) {
    'use strict';

    /**
     * Nylas Bundle JavaScript
     * This script handles all Nylas-related functionality
     */
    var NylasSimple = {

        /**
         * Initialize the script
         */
        init: function () {
            console.log('Nylas Bundle: JavaScript loaded successfully!');

            // Wait for jQuery to be available
            this.waitForJQuery();
        },

        /**
         * Wait for jQuery to be available before initializing
         */
        waitForJQuery: function() {
            var self = this;
            if (typeof $ !== 'undefined' || typeof jQuery !== 'undefined') {
                var $ = window.$ || window.jQuery;

                console.log('Nylas Bundle: jQuery available:', typeof $ !== 'undefined');
                console.log('Nylas Bundle: jQuery version:', $.fn.jquery);

                // Initialize all components
                self.initFolderListHandlers();
                self.initStatusToggleHandlers();
                self.initDropdownHandlers();
                self.onDOMReady();
            } else {
                // Retry after 100ms if jQuery not ready
                setTimeout(function() {
                    self.waitForJQuery();
                }, 100);
            }
        },

        /**
         * Initialize folder list handlers (converted from vanilla JS)
         */
        initFolderListHandlers: function () {
            var $ = window.$ || window.jQuery;

            $(document).ready(function() {
                // Email origin select handler
                var $select = $('#emailOriginSelect');
                if ($select.length) {
                    $select.off('change').on('change', function() {
                        var newId = $(this).val();
                        var newUrl = '/nylas/folderList/' + newId + '?_t=' + new Date().getTime();
                        window.location.href = newUrl;
                    });
                }

                // Handle new account parameter
                var urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('new_account') === '1') {
                    $('input[id^="folder-"]').each(function() {
                        $(this).prop('checked', false);
                        console.log('Unchecked checkbox for folder ID: ', $(this).val());
                    });
                }
            });
        },

        /**
         * Initialize status toggle handlers (converted from vanilla JS)
         */
        initStatusToggleHandlers: function () {
            var $ = window.$ || window.jQuery;

            $(document).ready(function() {
                function showStatusMessage(message, type) {
                    var $container = $('#status-message');
                    $container.html('<div class="alert alert-' + type + ' flash-message">' + message + '</div>');
                    $container.html('');
                    window.location.reload();
                }

                $('.status-toggle').off('change').on('change', function() {
                    var id = $(this).data('id');
                    var newStatus = $(this).is(':checked') ? 'true' : 'false';

                    $.ajax({
                        url: '/nylas/setAccountStatus/' + id + '/' + newStatus,
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/json'
                        },
                        success: function() {
                            showStatusMessage('Status updated successfully!', 'success');
                        },
                        error: function() {
                            showStatusMessage('Failed to update status.', 'danger');
                        }
                    });
                });
            });
        },

        /**
         * Initialize dropdown handlers (converted from vanilla JS)
         */
        initDropdownHandlers: function () {
            var $ = window.$ || window.jQuery;

            $(document).ready(function() {
                var $dropdown = $('.nylas-dropdown-new[data-dropdown="checkbox"]');
                if (!$dropdown.length) return;

                var $label = $dropdown.find('.nylas-dropdown-label');
                var $list = $dropdown.find('.nylas-dropdown-list');
                var $search = $dropdown.find('.folder-search-input');
                var $checkAllLink = $dropdown.find('.check-all-link');
                var $options = $dropdown.find('.nylas-dropdown-option:not(.check-all-option)');

                function getCheckboxes() {
                    return $dropdown.find('input[type="checkbox"]');
                }

                function getVisibleCheckboxes() {
                    return $options.filter(':visible').find('input[type="checkbox"]');
                }

                function updateLabel() {
                    var $checkboxes = getCheckboxes();
                    var $checked = $checkboxes.filter(':checked');

                    if ($checked.length === 0) {
                        $label.text('Select');
                    } else if ($checked.length === 1) {
                        $label.text($checked.closest('label').text().trim());
                    } else if ($checked.length === $checkboxes.length) {
                        $label.text('All Selected');
                    } else {
                        $label.text($checked.length + ' Selected');
                    }

                    // Update check all text
                    var $visible = getVisibleCheckboxes();
                    var $visibleChecked = $visible.filter(':checked');
                    $checkAllLink.text(($visible.length && $visible.length === $visibleChecked.length)
                        ? 'Unselect All' : 'Select All');
                }

                // Toggle open/close
                $label.off('click').on('click', function(e) {
                    e.stopPropagation();
                    $dropdown.toggleClass('open');
                    if ($dropdown.hasClass('open')) {
                        $search.focus();
                    }
                });

                $(document).off('click.nylas-dropdown-new').on('click.nylas-dropdown-new', function(e) {
                    if (!$dropdown.is(e.target) && $dropdown.has(e.target).length === 0) {
                        $dropdown.removeClass('open');
                    }
                });

                $list.off('click').on('click', function(e) {
                    e.stopPropagation();
                });

                // Checkbox change
                $dropdown.off('change').on('change', 'input[type="checkbox"]', function() {
                    updateLabel();
                });

                // Check/Uncheck All
                $checkAllLink.off('click').on('click', function(e) {
                    e.preventDefault();
                    var $visible = getVisibleCheckboxes();
                    var allChecked = $visible.length > 0 && $visible.filter(':checked').length === $visible.length;
                    $visible.prop('checked', !allChecked);
                    updateLabel();
                });

                // Search
                $search.off('input').on('input', function() {
                    var term = $(this).val().toLowerCase();
                    var anyVisible = false;

                    $options.each(function() {
                        var $option = $(this);
                        var label = $option.text().toLowerCase();
                        var match = label.indexOf(term) !== -1;
                        $option.toggle(match);
                        if (match) anyVisible = true;
                    });

                    // Show/hide "No results"
                    var $noResult = $list.find('.no-results');
                    if (!anyVisible && term !== '') {
                        if (!$noResult.length) {
                            $noResult = $('<div class="no-results">No results found</div>');
                            $list.append($noResult);
                        }
                    } else {
                        $noResult.remove();
                    }

                    updateLabel();
                });

                // Initialize
                updateLabel();
            });
        },

        /**
         * Handle DOM ready event
         */
        onDOMReady: function () {
            var $ = window.$ || window.jQuery;

            $(document).ready(function() {
                console.log('Nylas Bundle: DOM ready');

                // Add a class to body to indicate script is loaded
                $('body').addClass('nylas-simple-loaded');
            });
        }
    };

    // Make NylasSimple available globally
    window.NylasSimple = NylasSimple;

    // Auto-initialize when the script is loaded
    NylasSimple.init();

    console.log('Nylas Bundle: Simple script loaded and initialized');

})(window);