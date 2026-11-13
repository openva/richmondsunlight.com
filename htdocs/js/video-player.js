/*
 * Video player
 *
 * Connects a video (on a video page) to its guide: the bills taken up, the legislators who spoke,
 * and the transcript. Each is a tab; any timestamp or transcript line plays the video from that
 * point; and, as the video plays, the current bill, speaker, and transcript line are highlighted
 * (and kept in view), and summarized beneath the video.
 *
 * ?t=SECONDS in the URL (or #t=SECONDS, or #t=H:MM:SS) starts the video at that time. As the video
 * plays, ?t= in the address bar is kept up to date, so that a copied URL starts where the viewer
 * is. Links to #transcript, #video-bills, or #video-speakers open that tab.
 *
 * A video that's only on YouTube is embedded instead. Timestamps still start it at that time, but
 * nothing is highlighted as it plays.
 *
 * Expects the markup produced by Video::page_player_html().
 */
(function () {
    'use strict';

    var root = document.getElementById('video');
    if (!root || !root.classList.contains('video-player')) {
        return;
    }

    var video = root.querySelector('video');
    var youtube = root.querySelector('iframe.video-player-youtube');
    var now = root.querySelector('.video-player-now');
    var tablist = root.querySelector('.video-player-tabs');
    var tabs = Array.prototype.slice.call(root.querySelectorAll('[role="tab"]'));
    var panels = Array.prototype.slice.call(root.querySelectorAll('[role="tabpanel"]'));

    if (!video && !youtube) {
        return;
    }

    root.classList.add('is-enhanced');
    if (video) {
        preloadAfterLoad(video);
    }

    var bills = Array.prototype.map.call(root.querySelectorAll('#video-bills li'), function (el) {
        var link = el.querySelector('.video-player-item');
        return {
            el: el,
            start: parseFloat(el.dataset.start),
            end: parseFloat(el.dataset.end),
            label: link ? link.textContent.trim() : ''
        };
    });

    // Every appearance of every speaker, in order.
    var appearances = [];
    Array.prototype.forEach.call(root.querySelectorAll('#video-speakers li'), function (el) {
        var name = el.querySelector('.video-player-item').textContent.trim();
        Array.prototype.forEach.call(el.querySelectorAll('[data-seek]'), function (button) {
            appearances.push({ el: el, button: button, start: parseFloat(button.dataset.seek), name: name });
        });
    });
    appearances.sort(function (a, b) { return a.start - b.start; });

    var lines = Array.prototype.map.call(root.querySelectorAll('#transcript span[data-seek]'), function (el) {
        return { el: el, start: parseFloat(el.dataset.seek) };
    });

    var current = { bill: null, speaker: null, line: null };
    var lastManualScroll = 0;

    /*
     * The video is sent with preload="none", because browsers hold the page's load event until a
     * video's metadata arrives, and video hosts can be slow. Once the page has loaded, ask for the
     * metadata, so that the video shows its first frame.
     */
    function preloadAfterLoad(video) {
        var preload = function () {
            if (video.preload === 'none' && video.readyState === 0 && video.paused) {
                video.preload = 'metadata';
                video.load();
            }
        };
        if (document.readyState === 'complete') {
            preload();
        } else {
            window.addEventListener('load', preload, { once: true });
        }
    }

    /*
     * Tabs
     */
    function selectTab(tab, focus) {
        tabs.forEach(function (t) {
            var selected = t === tab;
            t.setAttribute('aria-selected', selected ? 'true' : 'false');
            t.tabIndex = selected ? 0 : -1;
        });
        panels.forEach(function (panel) {
            panel.hidden = panel.id !== tab.getAttribute('aria-controls');
        });
        if (focus) {
            tab.focus();
        }
        // Bring the current item into view in the newly shown panel.
        lastManualScroll = 0;
        update(true);
    }

    if (tabs.length > 0) {
        tablist.hidden = false;
        tabs.forEach(function (tab, i) {
            tab.addEventListener('click', function () { selectTab(tab); });
            tab.addEventListener('keydown', function (event) {
                var next = null;
                if (event.key === 'ArrowRight') {
                    next = tabs[(i + 1) % tabs.length];
                } else if (event.key === 'ArrowLeft') {
                    next = tabs[(i - 1 + tabs.length) % tabs.length];
                } else if (event.key === 'Home') {
                    next = tabs[0];
                } else if (event.key === 'End') {
                    next = tabs[tabs.length - 1];
                }
                if (next) {
                    event.preventDefault();
                    selectTab(next, true);
                }
            });
        });
    }

    /*
     * Playback
     */

    // Go to a time, and play from there if asked to.
    function cue(time, play) {
        if (youtube) {
            if (play || time > 0) {
                youtube.src = youtube.dataset.youtube + '?start=' + Math.floor(time) + (play ? '&autoplay=1' : '');
            }
            return;
        }
        if (video.readyState >= 1) {
            video.currentTime = time;
        } else {
            video.addEventListener('loadedmetadata', function () { video.currentTime = time; }, { once: true });
            if (play) {
                video.load();
            }
        }
        if (play) {
            var playing = video.play();
            if (playing && playing.catch) {
                playing.catch(function () {});
            }
        }
    }

    function scrollIntoPanel(el) {
        var box = el.closest('.video-player-scroll');
        if (!box || box.closest('[hidden]') || Date.now() - lastManualScroll < 4000) {
            return;
        }
        var top = el.offsetTop;
        if (top < box.scrollTop || top > box.scrollTop + box.clientHeight - el.offsetHeight) {
            box.scrollTop = Math.max(0, top - box.clientHeight / 3);
        }
    }

    function mark(key, item, el) {
        var previous = current[key];
        if (previous === item) {
            return false;
        }
        if (previous) {
            (previous.button || previous.el).classList.remove('is-current');
            if (previous.button) {
                previous.el.classList.remove('is-current');
            }
        }
        current[key] = item;
        if (item) {
            (el || item.el).classList.add('is-current');
            if (item.button) {
                item.button.classList.add('is-current');
            }
        }
        return true;
    }

    function update(force) {
        if (!video) {
            return;
        }
        var t = video.currentTime;

        var bill = null;
        for (var i = 0; i < bills.length; i++) {
            if (bills[i].start <= t && t <= bills[i].end) {
                bill = bills[i];
            }
        }

        // The most recent speaker, so long as they were identified within the last two minutes
        // (chyrons identify speakers only briefly, as they begin).
        var speaker = null;
        for (var j = 0; j < appearances.length && appearances[j].start <= t; j++) {
            speaker = appearances[j];
        }
        if (speaker && t - speaker.start > 120) {
            speaker = null;
        }

        var line = null;
        for (var k = 0; k < lines.length && lines[k].start <= t; k++) {
            line = lines[k];
        }

        var changed = mark('bill', bill) | mark('speaker', speaker, speaker && speaker.el) | mark('line', line);
        if (changed || force) {
            [bill, speaker, line].forEach(function (item) {
                if (item) {
                    scrollIntoPanel(item.el);
                }
            });
            var parts = [];
            if (bill) {
                parts.push(bill.label);
            }
            if (speaker) {
                parts.push(speaker.name);
            }
            now.textContent = parts.length ? 'Now: ' + parts.join(' · ') : '';
            now.hidden = parts.length === 0;
        }
    }

    /*
     * Keep ?t= in the address bar at the time being watched (a few seconds behind, at most), once
     * the video has been played. replaceState() leaves the browser's history alone.
     */
    var lastRecorded = null;
    function recordTime() {
        if (!video || video.currentTime === 0 || !window.history.replaceState) {
            return;
        }
        var seconds = Math.floor(video.currentTime);
        if (lastRecorded !== null && Math.abs(seconds - lastRecorded) < 5 && !video.paused) {
            return;
        }
        lastRecorded = seconds;
        var url = new URL(window.location.href);
        url.searchParams.set('t', seconds);
        if (/^#t=/.test(url.hash)) {
            url.hash = '';
        }
        window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash);
    }

    if (video) {
        video.addEventListener('timeupdate', function () {
            update(false);
            if (!video.paused) {
                recordTime();
            }
        });
        video.addEventListener('seeked', function () { update(false); });
        video.addEventListener('pause', recordTime);
    }

    root.addEventListener('click', function (event) {
        if (event.target.closest('a')) {
            return;
        }
        var seekable = event.target.closest('[data-seek]');
        if (seekable) {
            cue(parseFloat(seekable.dataset.seek), true);
        }
    });

    Array.prototype.forEach.call(root.querySelectorAll('.video-player-scroll'), function (box) {
        ['wheel', 'touchmove', 'keydown'].forEach(function (type) {
            box.addEventListener(type, function () { lastManualScroll = Date.now(); }, { passive: true });
        });
    });

    /*
     * A time, as SECONDS or H:MM:SS, in seconds (or null if it isn't one).
     */
    function seconds(text) {
        var time = /^(?:(\d+):)?(?:(\d+):)?(\d+(?:\.\d+)?)$/.exec(text || '');
        if (!time) {
            return null;
        }
        return [time[1], time[2], time[3]]
            .filter(function (p) { return p !== undefined; })
            .map(parseFloat)
            .reduce(function (total, part) { return total * 60 + part; }, 0);
    }

    /*
     * Open the tab, or go to the time, that the URL names -- on load, and whenever the hash
     * changes.
     */
    function followUrl(initial) {
        var hash = window.location.hash;
        var linkedTab = tabs.filter(function (t) { return '#' + t.getAttribute('aria-controls') === hash; })[0];
        if (linkedTab || (initial && tabs.length > 0)) {
            selectTab(linkedTab || tabs[0]);
        }

        var time = seconds((hash.match(/^#t=(.+)$/) || [])[1]);
        if (time === null && initial) {
            time = seconds(new URLSearchParams(window.location.search).get('t'));
        }
        if (time !== null) {
            cue(time, false);
            if (!initial) {
                root.scrollIntoView();
            }
        }
    }

    window.addEventListener('hashchange', function () { followUrl(false); });
    followUrl(true);
}());
