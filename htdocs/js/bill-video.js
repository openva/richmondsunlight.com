/*
 * Bill video player
 *
 * Plays the clips of a bill's floor and committee video in a native <video> element, one after
 * another. Each clip is a span of a (usually hours-long) video file, so the player's controls are
 * scoped to the clip: the scrubber covers only the clip, playback stops or advances to the next
 * clip at the end of each one, and the clip's transcript follows along.
 *
 * Expects the markup produced by bill.php: a #video section containing a <video> (with native
 * controls, as a fallback), a hidden .bill-video-controls bar, and a .bill-video-clips list whose
 * items carry data-src, data-start, data-end, data-poster, and data-label.
 */
(function () {
    'use strict';

    var root = document.getElementById('video');
    if (!root || !root.classList.contains('bill-video')) {
        return;
    }

    var player = root.querySelector('.bill-video-player');
    var video = root.querySelector('video');
    var controls = root.querySelector('.bill-video-controls');
    var playButton = root.querySelector('.bill-video-play');
    var muteButton = root.querySelector('.bill-video-mute');
    var fullscreenButton = root.querySelector('.bill-video-fullscreen');
    var prevButton = root.querySelector('.bill-video-prev');
    var nextButton = root.querySelector('.bill-video-next');
    var nowPlaying = root.querySelector('.bill-video-now');
    var scrubber = root.querySelector('.bill-video-scrubber');
    var timeLabel = root.querySelector('.bill-video-time');
    var notice = root.querySelector('.bill-video-notice');

    var clips = Array.prototype.map.call(root.querySelectorAll('.bill-video-clip'), function (el) {
        return {
            el: el,
            src: el.dataset.src,
            start: parseFloat(el.dataset.start),
            end: parseFloat(el.dataset.end),
            poster: el.dataset.poster,
            label: el.dataset.label,
            button: el.querySelector('.bill-video-select'),
            transcript: el.querySelector('.bill-video-transcript'),
            lines: Array.prototype.map.call(el.querySelectorAll('span[data-seek]'), function (span) {
                return { el: span, start: parseFloat(span.dataset.seek) };
            })
        };
    });

    if (!video || !controls || clips.length === 0) {
        return;
    }

    var current = 0;
    var pendingSeek = null;     // Where to seek once the current file's metadata has loaded.
    var scrubbing = false;
    var wantsToPlay = false;    // Whether the viewer last asked to play, rather than pause.
    var currentLine = null;
    var lastManualScroll = 0;

    // Swap the native controls, whose timeline spans the whole recording, for our own.
    root.classList.add('is-enhanced');
    video.controls = false;
    controls.hidden = false;
    if (clips.length === 1) {
        prevButton.hidden = true;
        nextButton.hidden = true;
    }
    if (!player.requestFullscreen && !video.webkitEnterFullscreen) {
        fullscreenButton.hidden = true;
    }

    function clock(seconds) {
        seconds = Math.max(0, Math.round(seconds));
        var h = Math.floor(seconds / 3600);
        var m = Math.floor(seconds / 60) % 60;
        var s = seconds % 60;
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        return h > 0 ? h + ':' + pad(m) + ':' + pad(s) : m + ':' + pad(s);
    }

    function play() {
        wantsToPlay = true;
        var playing = video.play();
        if (playing && playing.catch) {
            playing.catch(function () {});
        }
    }

    function setNotice(html) {
        notice.innerHTML = html || '';
        notice.hidden = !html;
    }

    /*
     * Make a clip the current one, loading its video file if it's not already loaded, and
     * optionally start playing it (from its start, or from a given time).
     */
    function select(index, options) {
        options = options || {};
        var clip = clips[index];
        var target = options.time !== undefined ? options.time : clip.start;

        current = index;
        setNotice('');

        if ((video.currentSrc || video.src).split('#')[0] !== clip.src) {
            pendingSeek = target;
            video.poster = clip.poster;
            video.src = clip.src;
            video.load();
        } else {
            video.currentTime = target;
        }

        if (options.play) {
            play();
        }

        render();
    }

    function render() {
        var clip = clips[current];

        clips.forEach(function (c, i) {
            var active = i === current;
            c.el.classList.toggle('is-active', active);
            c.button.setAttribute('aria-expanded', active ? 'true' : 'false');
            if (active) {
                c.button.setAttribute('aria-current', 'true');
            } else {
                c.button.removeAttribute('aria-current');
            }
        });

        nowPlaying.textContent = (clips.length > 1 ? 'Clip ' + (current + 1) + ' of ' + clips.length + ': ' : '')
            + clip.label;
        scrubber.max = Math.round(clip.end - clip.start);
        prevButton.disabled = current === 0;
        nextButton.disabled = current === clips.length - 1;
        updateTime();
    }

    function updateTime() {
        var clip = clips[current];
        var length = clip.end - clip.start;
        var position = Math.min(Math.max(video.currentTime - clip.start, 0), length);
        if (!scrubbing) {
            scrubber.value = Math.round(position);
        }
        scrubber.setAttribute('aria-valuetext', clock(position) + ' of ' + clock(length));
        timeLabel.textContent = clock(position) + ' / ' + clock(length);
        highlight(video.currentTime);
    }

    /*
     * Highlight the transcript line being spoken, scrolling it into view within the transcript
     * (but never scrolling the page, and not while the viewer is scrolling the transcript).
     */
    function highlight(time) {
        var clip = clips[current];
        var line = null;
        for (var i = 0; i < clip.lines.length; i++) {
            if (clip.lines[i].start > time) {
                break;
            }
            line = clip.lines[i];
        }
        if (line === currentLine) {
            return;
        }
        if (currentLine) {
            currentLine.el.classList.remove('is-current');
        }
        currentLine = line;
        if (!line) {
            return;
        }
        line.el.classList.add('is-current');

        if (Date.now() - lastManualScroll > 4000) {
            var box = clip.transcript;
            var top = line.el.offsetTop;
            if (top < box.scrollTop || top > box.scrollTop + box.clientHeight - 40) {
                box.scrollTop = Math.max(0, top - box.clientHeight / 3);
            }
        }
    }

    video.addEventListener('loadedmetadata', function () {
        if (pendingSeek !== null) {
            video.currentTime = pendingSeek;
            pendingSeek = null;
        }
    });

    /*
     * At the end of a clip, move on to the next one, or stop if this is the last.
     */
    function atEnd() {
        if (current < clips.length - 1) {
            select(current + 1, { play: wantsToPlay });
            return;
        }
        wantsToPlay = false;
        video.pause();
        video.currentTime = clips[current].end;
    }

    video.addEventListener('timeupdate', function () {
        if (pendingSeek !== null || video.seeking) {
            return;
        }
        if (video.currentTime >= clips[current].end) {
            atEnd();
            return;
        }
        updateTime();
    });

    video.addEventListener('play', function () {
        // Pressing play at the end of the last clip starts it over.
        var clip = clips[current];
        wantsToPlay = true;
        if (video.currentTime >= clip.end - 0.5) {
            video.currentTime = clip.start;
        }
        player.classList.add('is-playing');
        playButton.setAttribute('aria-label', 'Pause');
    });

    video.addEventListener('pause', function () {
        player.classList.remove('is-playing');
        playButton.setAttribute('aria-label', 'Play');

        // The fallback <source>'s media fragment (#t=start,end) makes the browser pause on its own
        // at the end of the first clip; treat that as reaching the end of the clip.
        if (wantsToPlay && pendingSeek === null && video.currentTime >= clips[current].end - 0.5) {
            atEnd();
        }
    });

    video.addEventListener('volumechange', function () {
        player.classList.toggle('is-muted', video.muted);
        muteButton.setAttribute('aria-pressed', video.muted ? 'true' : 'false');
    });

    video.addEventListener('error', function () {
        setNotice('This video couldn’t be loaded. You can <a href="' + clips[current].src + '#t='
            + clips[current].start + '">watch it at the Internet Archive</a> instead.');
    });

    function togglePlay() {
        if (video.paused) {
            play();
        } else {
            wantsToPlay = false;
            video.pause();
        }
    }

    playButton.addEventListener('click', togglePlay);
    video.addEventListener('click', togglePlay);

    muteButton.addEventListener('click', function () {
        video.muted = !video.muted;
    });

    fullscreenButton.addEventListener('click', function () {
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else if (player.requestFullscreen) {
            player.requestFullscreen();
        } else if (video.webkitEnterFullscreen) {
            // iOS can only make the <video> itself full screen.
            video.webkitEnterFullscreen();
        }
    });

    prevButton.addEventListener('click', function () {
        if (current > 0) {
            select(current - 1, { play: wantsToPlay });
        }
    });

    nextButton.addEventListener('click', function () {
        if (current < clips.length - 1) {
            select(current + 1, { play: wantsToPlay });
        }
    });

    scrubber.addEventListener('pointerdown', function () { scrubbing = true; });
    scrubber.addEventListener('input', function () {
        video.currentTime = clips[current].start + parseFloat(scrubber.value);
    });
    scrubber.addEventListener('change', function () { scrubbing = false; });

    root.addEventListener('click', function (event) {
        var target = event.target;
        var item = target.closest('.bill-video-clip');
        if (!item || target.closest('a')) {
            return;
        }
        var index = clips.findIndex(function (c) { return c.el === item; });

        if (target.closest('.bill-video-select')) {
            if (index !== current) {
                select(index, { play: true });
            }
            return;
        }

        var seekable = target.closest('[data-seek]');
        if (seekable) {
            select(index, { play: true, time: parseFloat(seekable.dataset.seek) });
        }
    });

    clips.forEach(function (clip) {
        ['wheel', 'touchmove', 'keydown'].forEach(function (type) {
            clip.transcript.addEventListener(type, function () {
                lastManualScroll = Date.now();
            }, { passive: true });
        });
    });

    // Start with the clip named in the URL (e.g., #video-clip-2), or else the first one.
    var linked = clips.findIndex(function (c) { return '#' + c.el.id === window.location.hash; });
    if (linked > 0) {
        select(linked);
    } else if (video.readyState >= 1) {
        if (video.currentTime < clips[0].start || video.currentTime > clips[0].end) {
            video.currentTime = clips[0].start;
        }
        render();
    } else {
        pendingSeek = clips[0].start;
        render();
    }
}());
