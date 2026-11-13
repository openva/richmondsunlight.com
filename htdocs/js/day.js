/*
 * A day's page (/day/YYYY-MM-DD/)
 *
 * Shows both chambers, or just the House or the Senate, as chosen with the buttons at the top of
 * the page, or by the URL's fragment (#house or #senate), which choosing updates. Anything that
 * isn't either chamber's (a joint meeting, say) is always shown.
 *
 * Also opens the minutes (or anything else that's collapsed) when the URL's fragment points into
 * them, as /day/2026-01-26/#minutes-house does.
 */
(function () {
    'use strict';

    var filter = document.querySelector('.day-filter');
    var chambers = ['house', 'senate'];

    function show(chamber) {
        document.querySelectorAll('[data-chamber]').forEach(function (element) {
            element.hidden = chamber !== '' && element.getAttribute('data-chamber') !== chamber;
        });

        // A section with nothing left to show is hidden too.
        document.querySelectorAll('.day-section').forEach(function (section) {
            var parts = section.querySelectorAll('[data-chamber]');
            if (parts.length === 0) {
                return;
            }
            section.hidden = Array.prototype.every.call(parts, function (part) {
                return part.hidden;
            }) && section.querySelectorAll(':scope > .day-meeting:not([data-chamber]), :scope > ul > li:not([data-chamber])').length === 0;
        });

        if (filter) {
            filter.querySelectorAll('button').forEach(function (button) {
                button.setAttribute('aria-pressed', button.getAttribute('data-show') === chamber ? 'true' : 'false');
            });
        }
    }

    // Open whatever collapsed section the fragment points into.
    function reveal(id) {
        var target = id ? document.getElementById(id) : null;
        if (!target) {
            return;
        }
        for (var element = target; element; element = element.parentElement) {
            if (element.tagName === 'DETAILS') {
                element.open = true;
            }
        }
        target.scrollIntoView();
    }

    function fromFragment() {
        var fragment = decodeURIComponent(window.location.hash.replace(/^#/, '')).toLowerCase();
        if (chambers.indexOf(fragment) !== -1) {
            show(fragment);
        } else {
            reveal(fragment);
        }
    }

    if (filter) {
        filter.hidden = false;
        filter.addEventListener('click', function (event) {
            var button = event.target.closest('button[data-show]');
            if (!button) {
                return;
            }
            var chamber = button.getAttribute('data-show');
            show(chamber);
            history.replaceState(null, '', chamber ? '#' + chamber : window.location.pathname + window.location.search);
        });
    }

    window.addEventListener('hashchange', fromFragment);
    fromFragment();
})();
