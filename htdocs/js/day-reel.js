/*
 * A day's highlight reel, on its page (/day/YYYY-MM-DD/)
 *
 * Plays a part of each highlight's clip, one after another, in one <video>: when the video passes
 * the end of a part, it moves to the next part, loading the next video if that part is in another
 * one. The parts (each highlight's segments, and the video they're in) come from the day's API
 * record, by way of the page (data-reel). A caption says which bill is playing, and the chapters
 * jump to a bill. See DayHighlights::reel_segments(), and docs/minutes-replacement.md.
 */
(function () {
    'use strict';

    var reel = document.querySelector('.day-reel');
    if (!reel) {
        return;
    }
    var items;
    try {
        items = JSON.parse(reel.getAttribute('data-reel'));
    } catch (e) {
        return;
    }
    var video = reel.querySelector('video');
    var start = reel.querySelector('.day-reel-start');
    var caption = reel.querySelector('.day-reel-caption');
    var chapters = reel.querySelectorAll('[data-chapter]');

    // A video's file, only if it's on one of the hosts that video pages play from (as
    // Video::media_url() requires), over https; otherwise null.
    var hosts = /^(video\.richmondsunlight\.com|archive\.org|[a-z0-9-]+\.archive\.org|[a-z0-9-]+\.sliq\.net|archive-video\.granicus\.com)$/;
    function mp4_url(url) {
        var match = /^https:\/\/([a-z0-9.-]+)(\/[^\s"'<>]*)$/i.exec(String(url || ''));
        if (!match || !hosts.test(match[1].toLowerCase())) {
            return null;
        }
        return 'https://' + match[1] + match[2];
    }

    // A video's captions, on this site, from its slug.
    function captions_url(slug) {
        return /^[a-z0-9]{4}$/.test(String(slug || '')) ? '/video/' + slug + '/captions.vtt' : null;
    }

    // Every part, in order, with the highlight it belongs to. (A highlight whose video can't be
    // played is left out.)
    var parts = [];
    items.forEach(function (item, i) {
        var mp4 = mp4_url(item.mp4);
        if (mp4 === null) {
            return;
        }
        item.segments.forEach(function (segment) {
            parts.push({ item: i, mp4: mp4, captions: captions_url(item.captions), start: Number(segment.start), end: Number(segment.end) });
        });
    });
    if (parts.length === 0) {
        return;
    }

    var current = -1;

    // Each video's captions, if it has them, replacing the last video's. Captions that a viewer
    // turned on stay on.
    function caption_track(url) {
        var old = video.querySelector('track');
        var mode = old && old.track ? old.track.mode : 'disabled';
        if (old) {
            video.removeChild(old);
        }
        if (!url) {
            return;
        }
        var track = document.createElement('track');
        track.kind = 'captions';
        track.srclang = 'en';
        track.label = 'English';
        track.src = url;
        video.appendChild(track);
        track.track.mode = mode === 'showing' ? 'showing' : 'disabled';
    }

    function label(item) {
        caption.textContent = '';
        var bill = document.createElement('strong');
        bill.textContent = item.number;
        caption.appendChild(bill);
        caption.appendChild(document.createTextNode(' ' + item.title + ' '));
        var action = document.createElement('span');
        action.className = 'day-reel-action';
        action.textContent = item.action;
        caption.appendChild(action);
        caption.hidden = false;
    }

    function mark(index) {
        chapters.forEach(function (chapter) {
            if (Number(chapter.getAttribute('data-chapter')) === index) {
                chapter.setAttribute('aria-current', 'true');
            } else {
                chapter.removeAttribute('aria-current');
            }
        });
    }

    // Play a part, from its start.
    function play(index) {
        if (index >= parts.length) {
            finish();
            return;
        }
        var part = parts[index];
        current = index;
        label(items[part.item]);
        mark(part.item);

        var seek = function () {
            video.currentTime = part.start;
            var playing = video.play();
            if (playing && playing.catch) {
                playing.catch(function () {});
            }
        };
        if (video.getAttribute('src') !== part.mp4) {
            video.setAttribute('src', part.mp4);
            caption_track(part.captions);
            video.addEventListener('loadedmetadata', seek, { once: true });
            video.load();
        } else {
            seek();
        }
    }

    function finish() {
        video.pause();
        current = -1;
        mark(-1);
        caption.hidden = true;
        start.textContent = 'Watch the highlights again';
        start.hidden = false;
        video.controls = false;
    }

    function begin(index) {
        start.hidden = true;
        video.controls = true;
        play(index);
    }

    // Move on at the end of each part. (A part can end at the end of its video, too.)
    video.addEventListener('timeupdate', function () {
        if (current >= 0 && !video.seeking && video.currentTime >= parts[current].end) {
            play(current + 1);
        }
    });
    video.addEventListener('ended', function () {
        if (current >= 0) {
            play(current + 1);
        }
    });
    // A video that won't load is skipped.
    video.addEventListener('error', function () {
        if (current >= 0) {
            var item = parts[current].item;
            var next = current + 1;
            while (next < parts.length && parts[next].item === item) {
                next++;
            }
            play(next);
        }
    });

    start.addEventListener('click', function () {
        begin(0);
    });
    chapters.forEach(function (chapter) {
        chapter.addEventListener('click', function () {
            var item = Number(chapter.getAttribute('data-chapter'));
            for (var i = 0; i < parts.length; i++) {
                if (parts[i].item === item) {
                    begin(i);
                    return;
                }
            }
        });
    });

    reel.hidden = false;
})();
