/*
 * Clip player
 *
 * Plays a list of video clips (e.g., every discussion of a bill, or every time a legislator spoke)
 * in a native <video> element, one after another. Each clip is a span of a (usually hours-long)
 * video file, so the player's controls are scoped to the clip: the scrubber covers only the clip,
 * playback stops or advances to the next clip at the end of each one, and the clip's transcript
 * follows along.
 *
 * Expects the markup from Video::player_html(), within a .clip-player element that also contains
 * .clip-player-clip items carrying data-src, data-start, data-end, data-poster, and data-label.
 * Each item's transcript is either in the page, or loaded when the clip is first selected from
 * the URL in a data-transcript-src attribute. Items may be grouped in <details> elements, which
 * are opened as playback reaches them.
 */
(function () {
    'use strict';

    Array.prototype.forEach.call(document.querySelectorAll('.clip-player'), init);

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

    function init(root) {
        var player = root.querySelector('.clip-player-stage');
        var video = root.querySelector('video');
        var controls = root.querySelector('.clip-player-controls');
        var playButton = root.querySelector('.clip-player-play');
        var muteButton = root.querySelector('.clip-player-mute');
        var captionsButton = root.querySelector('.clip-player-captions');
        var fullscreenButton = root.querySelector('.clip-player-fullscreen');
        var prevButton = root.querySelector('.clip-player-prev');
        var nextButton = root.querySelector('.clip-player-next');
        var nowPlaying = root.querySelector('.clip-player-now');
        var scrubber = root.querySelector('.clip-player-scrubber');
        var timeLabel = root.querySelector('.clip-player-time');
        var notice = root.querySelector('.clip-player-notice');

        var clips = Array.prototype.map.call(root.querySelectorAll('.clip-player-clip'), function (el) {
            return {
                el: el,
                src: el.dataset.src,
                start: parseFloat(el.dataset.start),
                end: parseFloat(el.dataset.end),
                poster: el.dataset.poster,
                captions: el.dataset.captions,
                label: el.dataset.label,
                button: el.querySelector('.clip-player-select'),
                transcript: el.querySelector('.clip-player-transcript'),
                lines: findLines(el)
            };
        });

        function findLines(el) {
            return Array.prototype.map.call(el.querySelectorAll('span[data-seek]'), function (span) {
                return { el: span, start: parseFloat(span.dataset.seek) };
            });
        }

        if (!video || !controls || clips.length === 0) {
            return;
        }

        var current = 0;
        var pendingSeek = null;     // Where to seek once the current file's metadata has loaded.
        var scrubbing = false;
        var wantsToPlay = false;    // Whether the viewer last asked to play, rather than pause.
        var currentLine = null;
        var lastManualScroll = 0;
        var captionsClip = null;    // The clip whose captions are loaded.
        var captionsOn = false;
        try {
            captionsOn = window.localStorage.getItem('rs-captions') === 'on';
        } catch (e) {}

        // Swap the native controls, whose timeline spans the whole recording, for our own.
        root.classList.add('is-enhanced');
        preloadAfterLoad(video);
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

        /*
         * Load a clip's transcript, if it's not in the page already.
         */
        function loadTranscript(clip) {
            var target = clip.el.querySelector('[data-transcript-src]');
            if (!target || target.dataset.loaded) {
                return;
            }
            target.dataset.loaded = 'loading';
            target.innerHTML = '<p class="video-transcript-empty">Loading the transcript…</p>';
            fetch(target.dataset.transcriptSrc)
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error(response.status);
                    }
                    return response.text();
                })
                .then(function (html) {
                    target.innerHTML = html;
                    target.dataset.loaded = 'yes';
                    clip.lines = findLines(clip.el);
                    currentLine = null;
                    if (clip === clips[current]) {
                        highlight(video.currentTime);
                    }
                })
                .catch(function () {
                    target.innerHTML = '<p class="video-transcript-empty">The transcript couldn’t be loaded.</p>';
                    delete target.dataset.loaded;
                });
        }

        /*
         * Captions: each clip has its own track, covering just that clip, which replaces the last
         * one's. Whether they're shown is remembered from one visit to the next.
         */
        function setCaptions(clip) {
            captionsClip = clip;
            Array.prototype.forEach.call(video.querySelectorAll('track'), function (old) {
                video.removeChild(old);
            });
            captionsButton.disabled = !clip.captions;
            if (!clip.captions) {
                return;
            }
            var track = document.createElement('track');
            track.kind = 'captions';
            track.srclang = 'en';
            track.label = 'English';
            track.src = clip.captions;
            track.addEventListener('load', function () {
                if (track.track.cues && track.track.cues.length === 0) {
                    captionsButton.disabled = true;
                }
            });
            track.addEventListener('error', function () {
                captionsButton.disabled = true;
            });
            video.appendChild(track);
            showCaptions(captionsOn);
        }

        function showCaptions(on) {
            captionsOn = on;
            Array.prototype.forEach.call(video.textTracks, function (track) {
                track.mode = on ? 'showing' : 'hidden';
            });
            captionsButton.setAttribute('aria-pressed', on ? 'true' : 'false');
            player.classList.toggle('has-captions', on);
        }

        function render() {
            var clip = clips[current];

            if (clip !== captionsClip) {
                setCaptions(clip);
            }

            var group = clip.el.closest('details');
            if (group && !group.open) {
                group.open = true;
            }
            loadTranscript(clip);

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

        // Long recordings can take a while to start (the browser may first have to download an index
        // of the whole file), and the native controls, with their spinner, are hidden; so say so.
        function setLoading(loading) {
            player.classList.toggle('is-loading', loading);
        }
        video.addEventListener('waiting', function () { setLoading(true); });
        video.addEventListener('playing', function () { setLoading(false); });
        video.addEventListener('pause', function () { setLoading(false); });
        video.addEventListener('error', function () { setLoading(false); });
        video.addEventListener('play', function () {
            if (video.readyState < 3) {
                setLoading(true);
            }
        });

        video.addEventListener('volumechange', function () {
            player.classList.toggle('is-muted', video.muted);
            muteButton.setAttribute('aria-pressed', video.muted ? 'true' : 'false');
        });

        video.addEventListener('error', function () {
            setNotice('This video couldn’t be loaded. You can <a href="' + clips[current].src + '#t='
                + clips[current].start + '">try opening it directly</a> instead.');
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

        captionsButton.addEventListener('click', function () {
            showCaptions(!captionsOn);
            try {
                window.localStorage.setItem('rs-captions', captionsOn ? 'on' : 'off');
            } catch (e) {}
        });

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
            var item = target.closest('.clip-player-clip');
            if (!item || target.closest('a')) {
                return;
            }
            var index = clips.findIndex(function (c) { return c.el === item; });

            if (target.closest('.clip-player-select')) {
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
    }
}());
