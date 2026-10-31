/**
 * OhMyLMS Custom Video Player
 * 
 * A custom video player that supports YouTube, Vimeo, and self-hosted videos
 * with minimal branding and enhanced playback controls.
 * 
 * @package OhMyLMS
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Video Platform Handler Class
     * Base class for different video platform implementations
     */
    class VideoPlatformHandler {
        constructor(container, config) {
            this.container = container;
            this.config = config;
            this.player = null;
            this.isReady = false;
            this.isPaused = true;
        }

        init() {
            throw new Error('init() must be implemented by subclass');
        }

        play() {
            throw new Error('play() must be implemented by subclass');
        }

        pause() {
            throw new Error('pause() must be implemented by subclass');
        }

        setVolume(volume) {
            throw new Error('setVolume() must be implemented by subclass');
        }

        setPlaybackRate(rate) {
            throw new Error('setPlaybackRate() must be implemented by subclass');
        }

        getCurrentTime() {
            throw new Error('getCurrentTime() must be implemented by subclass');
        }

        getDuration() {
            throw new Error('getDuration() must be implemented by subclass');
        }

        seekTo(time) {
            throw new Error('seekTo() must be implemented by subclass');
        }

        destroy() {
            if (this.player) {
                this.player = null;
            }
        }
    }

    /**
     * YouTube Video Handler (Following Plyr.js approach)
     */
    class YouTubeHandler extends VideoPlatformHandler {
        constructor(container, config) {
            super(container, config);
            this.videoId = this.extractVideoId(config.url);
            this.hasPlayed = false;
        }

        extractVideoId(url) {
            const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=)([^#&?]*).*/;
            const match = url.match(regExp);
            return (match && match[2].length === 11) ? match[2] : null;
        }

        init() {
            return new Promise((resolve, reject) => {
                if (!this.videoId) {
                    reject(new Error('Invalid YouTube URL'));
                    return;
                }

                // Setup YouTube API
                if (typeof window.YT === 'object' && typeof window.YT.Player === 'function') {
                    this.ready(resolve, reject);
                } else {
                    // Reference current global callback
                    const callback = window.onYouTubeIframeAPIReady;

                    // Set callback to process queue
                    window.onYouTubeIframeAPIReady = () => {
                        // Call global callback if set
                        if (typeof callback === 'function') {
                            callback();
                        }
                        this.ready(resolve, reject);
                    };

                    // Load the SDK
                    if (!document.getElementById('youtube-iframe-api')) {
                        const tag = document.createElement('script');
                        tag.id = 'youtube-iframe-api';
                        tag.src = 'https://www.youtube.com/iframe_api';
                        tag.async = true;
                        const firstScriptTag = document.getElementsByTagName('script')[0];
                        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
                    }
                }
            });
        }

        ready(resolve, reject) {
            // Replace container with proper div for YouTube
            const id = `youtube-${Date.now()}`;
            const placeholder = document.createElement('div');
            placeholder.id = id;
            
            // Clear container
            this.container.innerHTML = '';
            this.container.appendChild(placeholder);

            // Setup instance following Plyr's approach
            const playerVars = {
                // Autoplay
                autoplay: this.config.autoplay ? 1 : 0,
                // Hide all controls for custom player (like Plyr with customControls)
                controls: 0,
                // Disable keyboard as we handle it
                disablekb: 1,
                // Allow iOS inline playback
                playsinline: 1,
                // Minimal branding
                modestbranding: 1,
                // No related videos
                rel: 0,
                // Hide video info
                showinfo: 0,
                // Hide annotations
                iv_load_policy: 3,
                // Enable JS API
                enablejsapi: 1,
                // Origin for security
                origin: window.location.origin,
                // Captions
                cc_load_policy: 0,
                // Tracking
                widget_referrer: window.location.href
            };

            // Add loop support
            if (this.config.loop) {
                playerVars.loop = 1;
                playerVars.playlist = this.videoId;
            }

            // Create player instance
            this.player = new window.YT.Player(id, {
                videoId: this.videoId,
                playerVars: playerVars,
                events: {
                    onReady: (event) => this.onPlayerReady(event, resolve),
                    onStateChange: (event) => this.onPlayerStateChange(event),
                    onError: (event) => this.onPlayerError(event, reject)
                }
            });
        }

        onPlayerReady(event, resolve) {
            const instance = event.target;

            // Store instance for use by methods
            this.ytPlayer = instance;

            // Set initial state
            this.isReady = true;
            this.isPaused = !this.config.autoplay;

            // Add player-ready class to show the iframe
            const iframe = instance.getIframe();
            if (iframe) {
                iframe.classList.add('player-ready');
                // Ensure iframe is touchable on mobile
                iframe.style.pointerEvents = 'auto';
                iframe.style.touchAction = 'auto';
            }

            // Setup buffering timer (like Plyr)
            this.bufferingTimer = setInterval(() => {
                const buffered = instance.getVideoLoadedFraction();
                if (buffered > 0) {
                    // Trigger progress event through config callback
                    if (this.config.onProgress) {
                        this.config.onProgress(buffered);
                    }
                }
                if (buffered === 1) {
                    clearInterval(this.bufferingTimer);
                }
            }, 200);

            if (this.config.onReady) {
                this.config.onReady(event);
            }

            resolve(this);
        }

        play() {
            if (!this.ytPlayer || !this.isReady) return;
            
            if (!this.hasPlayed) {
                this.hasPlayed = true;
            }
            if (this.isPaused) {
                this.isPaused = false;
                if (this.config.onPlay) {
                    this.config.onPlay();
                }
            }
            this.ytPlayer.playVideo();
        }

        pause() {
            if (!this.ytPlayer || !this.isReady) return;
            
            if (!this.isPaused) {
                this.isPaused = true;
                if (this.config.onPause) {
                    this.config.onPause();
                }
            }
            this.ytPlayer.pauseVideo();
        }

        setVolume(volume) {
            if (!this.ytPlayer || !this.isReady) return;
            this.ytPlayer.setVolume(volume * 100);
        }

        setPlaybackRate(rate) {
            if (!this.ytPlayer || !this.isReady) return;
            this.ytPlayer.setPlaybackRate(rate);
        }

        getCurrentTime() {
            if (!this.ytPlayer || !this.isReady) return 0;
            return this.ytPlayer.getCurrentTime();
        }

        getDuration() {
            if (!this.ytPlayer || !this.isReady) return 0;
            return this.ytPlayer.getDuration();
        }

        seekTo(time) {
            if (!this.ytPlayer || !this.isReady) return;
            
            // If paused and never played, mute audio preventively (like Plyr)
            if (this.isPaused && !this.hasPlayed) {
                this.ytPlayer.mute();
            }
            this.ytPlayer.seekTo(time);
        }

        onPlayerStateChange(event) {
            if (!this.ytPlayer) return;
            
            // Handle different states (following Plyr's approach)
            switch (event.data) {
                case -1: // Unstarted
                    break;

                case 0: // Ended
                    this.isPaused = true;
                    if (this.config.onEnded) {
                        this.config.onEnded();
                    }
                    if (this.config.onPause) {
                        this.config.onPause();
                    }
                    break;

                case 1: // Playing
                    // Restore paused state if autoplay is off and hasn't played yet (like Plyr)
                    if (!this.config.autoplay && this.isPaused && !this.hasPlayed) {
                        this.pause();
                    } else {
                        if (this.isPaused) {
                            this.isPaused = false;
                            if (this.config.onPlay) {
                                this.config.onPlay();
                            }
                        }
                        if (!this.hasPlayed) {
                            this.hasPlayed = true;
                        }
                    }
                    break;

                case 2: // Paused
                    // Restore audio if muted (like Plyr)
                    if (!this.config.muted && this.ytPlayer.isMuted()) {
                        this.ytPlayer.unMute();
                    }
                    if (!this.isPaused) {
                        this.isPaused = true;
                        if (this.config.onPause) {
                            this.config.onPause();
                        }
                    }
                    break;

                case 3: // Buffering
                    break;

                default:
                    break;
            }

            if (this.config.onStateChange) {
                this.config.onStateChange(event);
            }
        }

        onPlayerError(event, reject) {
            const code = event.data;
            const messages = {
                2: 'Invalid parameter value',
                5: 'HTML5 player error',
                100: 'Video not found',
                101: 'Video not allowed in embedded players',
                150: 'Video not allowed in embedded players'
            };
            
            const error = {
                code: code,
                message: messages[code] || 'Unknown error occurred'
            };

            console.error('YouTube player error:', error);
            
            if (reject) {
                reject(error);
            }
        }

        destroy() {
            if (this.bufferingTimer) {
                clearInterval(this.bufferingTimer);
            }
            if (this.player && this.player.destroy) {
                this.player.destroy();
            }
            super.destroy();
        }
    }

    /**
     * Vimeo Video Handler
     */
    class VimeoHandler extends VideoPlatformHandler {
        constructor(container, config) {
            super(container, config);
            this.videoId = this.extractVideoId(config.url);
            // Cache for synchronous access to avoid async lag in render loop
            this.cachedCurrentTime = 0;
            this.cachedDuration = 0;
        }

        extractVideoId(url) {
            const regExp = /vimeo\.com\/(?:video\/)?(\d+)/;
            const match = url.match(regExp);
            return match ? match[1] : null;
        }

        init() {
            return new Promise((resolve, reject) => {
                if (!this.videoId) {
                    reject(new Error('Invalid Vimeo URL'));
                    return;
                }

                // Load Vimeo Player API if not already loaded
                if (typeof Vimeo === 'undefined' || typeof Vimeo.Player === 'undefined') {
                    const script = document.createElement('script');
                    script.src = 'https://player.vimeo.com/api/player.js';
                    script.onload = () => {
                        this.createPlayer(resolve, reject);
                    };
                    script.onerror = reject;
                    document.head.appendChild(script);
                } else {
                    this.createPlayer(resolve, reject);
                }
            });
        }

        createPlayer(resolve, reject) {
            try {
                const options = {
                    id: this.videoId,
                    autoplay: this.config.autoplay,
                    loop: this.config.loop,
                    controls: false, // Always hide Vimeo native controls
                    muted: this.config.muted,
                    title: false,
                    byline: false,
                    portrait: false,
                    playsinline: true,
                    badge: false,
                    transparent: false,
                    color: 'ffffff' // White color for less branding
                };

                // Create iframe element
                const iframe = document.createElement('div');
                this.container.appendChild(iframe);

                this.player = new Vimeo.Player(iframe, options);

                this.player.ready().then(async () => {
                    this.isReady = true;
                    
                    // Check initial playing state and cache initial values
                    try {
                        this.isPaused = await this.player.getPaused();
                        this.cachedDuration = await this.player.getDuration();
                        this.cachedCurrentTime = await this.player.getCurrentTime();
                    } catch (e) {
                        this.isPaused = true;
                    }
                    
                    // Set up event listeners
                    this.player.on('play', () => {
                        this.isPaused = false;
                        if (this.config.onPlay) this.config.onPlay();
                    });

                    this.player.on('pause', () => {
                        this.isPaused = true;
                        if (this.config.onPause) this.config.onPause();
                    });

                    this.player.on('ended', () => {
                        this.isPaused = true;
                        if (this.config.onEnded) this.config.onEnded();
                    });
                    
                    // Cache time and duration on every timeupdate for smooth synchronous progress updates
                    this.player.on('timeupdate', async (data) => {
                        // Cache the current time from the event data (most efficient)
                        this.cachedCurrentTime = data.seconds;
                        this.cachedDuration = data.duration;
                        
                        // Periodically sync isPaused state
                        try {
                            this.isPaused = await this.player.getPaused();
                        } catch (e) {
                            // Ignore errors during state sync
                        }
                    });

                    if (this.config.onReady) {
                        this.config.onReady();
                    }

                    resolve(this);
                }).catch(reject);
            } catch (error) {
                reject(error);
            }
        }

        play() {
            if (this.player && this.isReady) {
                this.isPaused = false; // Update state immediately
                this.player.play();
            }
        }

        pause() {
            if (this.player && this.isReady) {
                this.isPaused = true; // Update state immediately
                this.player.pause();
            }
        }

        async setVolume(volume) {
            if (this.player && this.isReady) {
                await this.player.setVolume(volume);
            }
        }

        async setPlaybackRate(rate) {
            if (this.player && this.isReady) {
                await this.player.setPlaybackRate(rate);
            }
        }

        getCurrentTime() {
            // Return cached value for synchronous access (updated via timeupdate event)
            if (this.isReady) {
                return this.cachedCurrentTime;
            }
            return 0;
        }

        getDuration() {
            // Return cached value for synchronous access (updated via timeupdate event)
            if (this.isReady) {
                return this.cachedDuration;
            }
            return 0;
        }

        async seekTo(time) {
            if (this.player && this.isReady) {
                const duration = this.getDuration();
                // Ensure time is within valid range - clamp between 0 and duration
                const validTime = Math.max(0, Math.min(duration, time));
                await this.player.setCurrentTime(validTime);
                // Update cache immediately for instant UI feedback
                this.cachedCurrentTime = validTime;
            }
        }

        destroy() {
            if (this.player && this.player.destroy) {
                this.player.destroy();
            }
            super.destroy();
        }
    }

    /**
     * HTML5 Video Handler (for self-hosted videos)
     */
    class HTML5Handler extends VideoPlatformHandler {
        constructor(container, config) {
            super(container, config);
            this.playPromise = null; // Track play promise to avoid race conditions
        }

        init() {
            return new Promise((resolve, reject) => {
                try {
                    // Create video element
                    const video = document.createElement('video');
                    video.className = 'ohmylms-html5-video';
                    video.controls = this.config.showControls;
                    video.autoplay = this.config.autoplay;
                    video.loop = this.config.loop;
                    video.muted = this.config.muted;
                    video.playsInline = true;
                    video.preload = 'metadata';
                    
                    // Only set crossOrigin for external URLs, not for same-origin videos
                    const isSameOrigin = this.config.url.startsWith(window.location.origin) || 
                                        this.config.url.startsWith('/') || 
                                        !this.config.url.startsWith('http');
                    
                    if (!isSameOrigin) {
                        video.crossOrigin = 'anonymous';
                    }
                    
                    if (this.config.poster) {
                        video.poster = this.config.poster;
                    }

                    // Add source - for MOV files, try without type attribute first for better compatibility
                    const isMov = this.config.url.toLowerCase().includes('.mov');
                    
                    if (isMov) {
                        // For MOV files, set src directly and let browser negotiate codec
                        video.src = this.config.url;
                    } else {
                        // For other formats, use source element with explicit type
                        const source = document.createElement('source');
                        source.src = this.config.url;
                        source.type = this.getMimeType(this.config.url);
                        video.appendChild(source);
                    }

                    // Clear container and add video
                    this.container.innerHTML = '';
                    this.container.appendChild(video);
                    this.player = video;

                    // Track if we've resolved
                    let hasResolved = false;

                    // Set up event listeners
                    video.addEventListener('loadedmetadata', () => {
                        if (!hasResolved) {
                            this.isReady = true;
                            this.isPaused = !this.config.autoplay;
                            if (this.config.onReady) {
                                this.config.onReady();
                            }
                            hasResolved = true;
                            resolve(this);
                        }
                    });

                    // Also try loadeddata as backup
                    video.addEventListener('loadeddata', () => {
                        if (!hasResolved && video.readyState >= 2) {
                            this.isReady = true;
                            this.isPaused = !this.config.autoplay;
                            if (this.config.onReady) {
                                this.config.onReady();
                            }
                            hasResolved = true;
                            resolve(this);
                        }
                    });

                    video.addEventListener('play', () => {
                        this.isPaused = false;
                        if (this.config.onPlay) this.config.onPlay();
                    });

                    video.addEventListener('pause', () => {
                        this.isPaused = true;
                        if (this.config.onPause) this.config.onPause();
                    });

                    video.addEventListener('ended', () => {
                        this.isPaused = true;
                        if (this.config.onEnded) this.config.onEnded();
                    });

                    video.addEventListener('timeupdate', () => {
                        if (this.config.onTimeUpdate) {
                            this.config.onTimeUpdate();
                        }
                    });

                    video.addEventListener('durationchange', () => {
                        // Trigger ready callback again when duration is available
                        if (video.duration && video.duration > 0 && this.config.onReady) {
                            this.config.onReady();
                        }
                    });

                    video.addEventListener('error', (e) => {
                        if (!hasResolved) {
                            const isMov = this.config.url.toLowerCase().includes('.mov');
                            const errorMessage = video.error ? video.error.message : 'Unknown error';
                            
                            // Provide specific guidance for MOV files
                            if (isMov) {
                                const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
                                
                                if (!isSafari) {
                                    console.error('MOV format is not supported in ' + navigator.userAgent.split(' ').pop() + '. MOV files work best in Safari. Please convert to MP4 for universal browser support.');
                                } else {
                                    console.warn('MOV file error in Safari. The codec inside the MOV container may not be supported.');
                                }
                            }
                            
                            hasResolved = true;
                            reject(new Error('Failed to load video: ' + errorMessage));
                        }
                    });

                    // Fallback timeout - be more aggressive with initialization
                    setTimeout(() => {
                        if (!hasResolved) {
                            this.isReady = true;
                            this.isPaused = !this.config.autoplay;
                            if (this.config.onReady) {
                                this.config.onReady();
                            }
                            hasResolved = true;
                            resolve(this);
                        }
                    }, 2000);

                    // Try to load the video
                    video.load();

                } catch (error) {
                    reject(error);
                }
            });
        }

        getMimeType(url) {
            const extension = url.split('.').pop().toLowerCase().split('?')[0];
            const mimeTypes = {
                'mp4': 'video/mp4',
                'webm': 'video/webm',
                'ogg': 'video/ogg',
                'ogv': 'video/ogg',
                'mov': 'video/quicktime',
                'm4v': 'video/mp4',
                'avi': 'video/x-msvideo',
                'flv': 'video/x-flv',
                'mkv': 'video/x-matroska'
            };
            return mimeTypes[extension] || 'video/mp4';
        }

        play() {
            if (this.player && this.isReady) {
                // Track the play promise to avoid race conditions
                this.playPromise = this.player.play();
                
                if (this.playPromise !== undefined) {
                    this.playPromise
                        .then(() => {
                            this.playPromise = null;
                        })
                        .catch(e => {
                            this.playPromise = null;
                            // Ignore AbortError - it's triggered by pause() interrupting play()
                            if (e.name !== 'AbortError') {
                                console.error('Play failed:', e);
                            }
                        });
                }
            }
        }

        pause() {
            if (this.player && this.isReady) {
                // Wait for play promise to resolve before pausing to avoid AbortError
                if (this.playPromise !== null) {
                    this.playPromise
                        .then(() => {
                            this.player.pause();
                        })
                        .catch(() => {
                            // If play failed, just pause anyway
                            this.player.pause();
                        });
                } else {
                    this.player.pause();
                }
            }
        }

        setVolume(volume) {
            if (this.player && this.isReady) {
                this.player.volume = volume;
            }
        }

        setPlaybackRate(rate) {
            if (this.player && this.isReady) {
                this.player.playbackRate = rate;
            }
        }

        getCurrentTime() {
            if (this.player && this.isReady) {
                const currentTime = this.player.currentTime;
                return (currentTime && isFinite(currentTime)) ? currentTime : 0;
            }
            return 0;
        }

        getDuration() {
            if (this.player && this.isReady) {
                const duration = this.player.duration;
                // Return 0 if duration is NaN, Infinity, or falsy
                return (duration && isFinite(duration)) ? duration : 0;
            }
            return 0;
        }

        seekTo(time) {
            if (this.player && this.isReady) {
                this.player.currentTime = time;
            }
        }

        destroy() {
            if (this.player) {
                this.player.pause();
                this.player.src = '';
                this.player.load();
                this.player.remove();
            }
            super.destroy();
        }
    }

    /**
     * Main OhMyLMS Video Player
     */
    class OhMyLMSVideoPlayer {
        constructor(element, options = {}) {
            this.element = $(element);
            this.options = $.extend({
                url: '',
                autoplay: false,
                loop: false,
                muted: false,
                showControls: false, // Use custom controls by default
                customControls: true,
                poster: null,
                onReady: null,
                onPlay: null,
                onPause: null,
                onEnded: null,
                onStateChange: null,
                logoUrl: '',
            }, options);

            this.platformHandler = null;
            this.controlsVisible = true;
            this.volume = 1;
            this.playbackRate = 1;
            this.isFullscreen = false;
            this.durationKnown = false; // Track if duration has been loaded
            this.hasEnded = false; // Track if video has ended
            this.isMobile = this.detectMobile(); // Detect mobile device

            this.init();
        }

        /**
         * Detect if device is mobile
         */
        detectMobile() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
                   (navigator.maxTouchPoints && navigator.maxTouchPoints > 2 && /MacIntel/.test(navigator.platform));
        }

        init() {
            this.detectPlatform();
            this.createPlayerContainer();
            this.addBrandingBlockers(); // Always add blockers for YouTube/Vimeo

            // Always create custom controls (showControls is false by default)
            if (this.options.customControls && !this.options.showControls) {
                this.createCustomControls();
                // Logo replaces big play button when set
                if (!this.options.logoUrl) {
                    this.createBigPlayButton();
                }
            }

            this.createLogoOverlay();
            this.setupKeyboardControls();
            this.initPlatformHandler();
        }

        createLogoOverlay() {
            if (!this.options.logoUrl) return;

            const logo = $('<img />', {
                src: this.options.logoUrl,
                alt: '',
                class: 'ohmylms-player-logo',
            }).css({ top: '50%', left: '50%', transform: 'translate(-50%, -50%)' });

            const self = this;
            logo.on('click touchend', function(e) {
                e.stopPropagation();
                e.preventDefault();
                self.play();
                logo.hide();
                if (self.clickBlocker) self.clickBlocker.hide();
            });

            this.element.append(logo);
            this.logoOverlay = logo;
        }

        detectPlatform() {
            const url = this.options.url;
            
            if (url.includes('youtube.com') || url.includes('youtu.be')) {
                this.platform = 'youtube';
            } else if (url.includes('vimeo.com')) {
                this.platform = 'vimeo';
            } else {
                this.platform = 'html5';
            }
        }

        createPlayerContainer() {
            this.playerContainer = $('<div class="ohmylms-video-player-container"></div>');
            this.element.append(this.playerContainer);
            
            // Add click/touch-to-play/pause functionality for all video types
            const self = this;
            this.playerContainer.on('click touchend', function(e) {
                // Don't trigger if clicking on controls
                if ($(e.target).closest('.ohmylms-custom-controls').length > 0) {
                    return;
                }
                
                // For touch events, prevent default to avoid double-firing
                if (e.type === 'touchend') {
                    e.preventDefault();
                    // Show controls on tap
                    self.showControls();
                }
                
                if (self.platformHandler) {
                    if (self.platformHandler.isPaused) {
                        self.play();
                    } else {
                        self.pause();
                    }
                }
            });
        }

        addBrandingBlockers() {
            // Only add blockers for YouTube and Vimeo to hide their branding
            // Reduce blocking on mobile for better interaction
            if (this.platform === 'youtube' || this.platform === 'vimeo') {
                const brandingBlockers = $(`
                    <div class="ohmylms-video-interaction-blocker"></div>
                    <div class="ohmylms-branding-blocker-top"></div>
                    <div class="ohmylms-branding-blocker-bottom-right"></div>
                `);
                this.element.append(brandingBlockers);
                this.brandingBlockers = brandingBlockers;
                
                // Attach click/touch events to blockers for play/pause  
                const self = this;
                brandingBlockers.on('click touchend', function(e) {
                    // Prevent default for touch to avoid double-firing
                    if (e.type === 'touchend') {
                        e.preventDefault();
                    }
                    
                    if (self.platformHandler && self.platformHandler.isPaused) {
                        self.play();
                    } else if (self.platformHandler) {
                        self.pause();
                    }
                });
            }
        }

        createBigPlayButton() {
            // Responsive size: larger for desktop, smaller for mobile
            const buttonSize = this.isMobile ? 70 : 140;
            
            const bigPlayButton = $(`
                <div class="ohmylms-big-play-button">
                    <svg width="${buttonSize}" height="${buttonSize}" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle class="play-button-circle" cx="50" cy="50" r="48" stroke-width="4"/>
                        <path class="play-button-icon" d="M40 30L70 50L40 70V30Z"/>
                    </svg>
                </div>
            `);
            
            // For YouTube, add a full-screen invisible overlay to block YouTube's own play button
            // But NOT on mobile devices where it can block necessary interactions
            if (this.platform === 'youtube' && !this.isMobile) {
                const clickBlocker = $('<div class="ohmylms-youtube-click-blocker"></div>');
                this.element.append(clickBlocker);
                this.clickBlocker = clickBlocker; // Store reference to remove it later
                
                const self = this;
                clickBlocker.on('click touchend', function(e) {
                    e.stopPropagation();
                    e.preventDefault();
                    if (self.platformHandler && self.platformHandler.isPaused) {
                        self.play();
                        if (self.bigPlayButton) self.bigPlayButton.hide();
                        // Hide the click blocker once video starts to allow controls to work
                        if (self.clickBlocker) self.clickBlocker.hide();
                    }
                });
            }
            
            this.element.append(bigPlayButton);
            this.bigPlayButton = bigPlayButton;
            
            const self = this;
            // Add both click and touchend for better mobile support
            bigPlayButton.on('click touchend', function(e) {
                e.stopPropagation();
                e.preventDefault();
                self.play();
                bigPlayButton.hide();
                // Hide the click blocker once video starts to allow controls to work
                if (self.clickBlocker) self.clickBlocker.hide();
            });
        }

        createCustomControls() {            const controls = $(`                <div class="ohmylms-custom-controls">
                    <div class="ohmylms-controls-progress-container">
                        <div class="ohmylms-progress-bar">
                            <div class="ohmylms-progress-filled"></div>
                            <div class="ohmylms-progress-handle"></div>
                        </div>
                        <div class="ohmylms-time-display">
                            <span class="ohmylms-current-time">0:00</span>
                            <span class="ohmylms-separator">/</span>
                            <span class="ohmylms-duration">--:--</span>
                        </div>
                    </div>
                    <div class="ohmylms-controls-bottom">
                        <div class="ohmylms-controls-left">
                            <button class="ohmylms-control-btn ohmylms-play-pause" aria-label="Play">
                                <svg class="ohmylms-play-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M8 5v14l11-7z" fill="currentColor"/>
                                </svg>
                                <svg class="ohmylms-pause-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:none;">
                                    <path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z" fill="currentColor"/>
                                </svg>
                            </button>
                            <div class="ohmylms-volume-control">
                                <button class="ohmylms-control-btn ohmylms-volume-btn" aria-label="Mute">
                                    <svg class="ohmylms-volume-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02z" fill="currentColor"/>
                                    </svg>
                                    <svg class="ohmylms-mute-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:none;">
                                        <path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z" fill="currentColor"/>
                                    </svg>
                                </button>
                                <div class="ohmylms-volume-slider">
                                    <div class="ohmylms-volume-track" role="slider" aria-label="Volume" aria-valuemin="0" aria-valuemax="100" aria-valuenow="100" tabindex="0">
                                        <div class="ohmylms-volume-fill"></div>
                                        <div class="ohmylms-volume-thumb"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="ohmylms-controls-right">
                            <div class="ohmylms-speed-control">
                                <button class="ohmylms-control-btn ohmylms-speed-btn" aria-label="Playback speed">
                                    <span class="ohmylms-speed-text">1x</span>
                                </button>
                                <div class="ohmylms-speed-menu">
                                    <button data-speed="0.5">0.5x</button>
                                    <button data-speed="0.75">0.75x</button>
                                    <button data-speed="1" class="active">1x</button>
                                    <button data-speed="1.25">1.25x</button>
                                    <button data-speed="1.5">1.5x</button>
                                    <button data-speed="2">2x</button>
                                </div>
                            </div>
                            <button class="ohmylms-control-btn ohmylms-fullscreen-btn" aria-label="Fullscreen">
                                <svg class="ohmylms-fullscreen-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z" fill="currentColor"/>
                                </svg>
                                <svg class="ohmylms-exit-fullscreen-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:none;">
                                    <path d="M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z" fill="currentColor"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            `);

            this.element.append(controls);
            this.controls = controls;
            
            // Initialize volume fill bar
            this.updateVolumeFill(100);
            
            this.attachControlEvents();
        }

        attachControlEvents() {
            const self = this;

            // Play/Pause button - add touch support
            this.controls.find('.ohmylms-play-pause').on('click touchend', function(e) {
                // Prevent default for touch to avoid double-firing
                if (e.type === 'touchend') {
                    e.preventDefault();
                }
                
                if (self.platformHandler.isPaused) {
                    self.play();
                } else {
                    self.pause();
                }
            });

            // Volume controls - add touch support
            this.controls.find('.ohmylms-volume-btn').on('click touchend', function(e) {
                // Prevent default for touch to avoid double-firing
                if (e.type === 'touchend') {
                    e.preventDefault();
                }
                
                if (self.volume > 0) {
                    self.previousVolume = self.volume;
                    self.setVolume(0);
                } else {
                    self.setVolume(self.previousVolume || 1);
                }
            });

            // Custom volume slider handling with full touch support
            const volumeTrack = this.controls.find('.ohmylms-volume-track');
            let isDraggingVolume = false;
            
            const updateVolumeFromPosition = (e) => {
                const rect = volumeTrack[0].getBoundingClientRect();
                let clientX;
                
                // Handle both mouse and touch events
                if (e.type && e.type.indexOf('touch') !== -1) {
                    const touch = e.touches ? e.touches[0] : e.changedTouches ? e.changedTouches[0] : null;
                    if (touch) {
                        clientX = touch.clientX;
                    } else {
                        return; // No valid touch
                    }
                } else {
                    clientX = e.clientX;
                }
                
                const percent = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
                self.setVolume(percent);
                self.updateVolumeFill(percent * 100);
            };
            
            // Mouse events
            volumeTrack.on('mousedown', function(e) {
                e.preventDefault();
                isDraggingVolume = true;
                $(this).addClass('is-dragging');
                updateVolumeFromPosition(e.originalEvent || e);
            });
            
            // Touch events for mobile
            volumeTrack.on('touchstart', function(e) {
                e.preventDefault();
                isDraggingVolume = true;
                $(this).addClass('is-dragging');
                updateVolumeFromPosition(e.originalEvent || e);
            });
            
            $(document).on('mousemove', function(e) {
                if (isDraggingVolume) {
                    e.preventDefault();
                    updateVolumeFromPosition(e.originalEvent || e);
                }
            });
            
            $(document).on('touchmove', function(e) {
                if (isDraggingVolume) {
                    e.preventDefault();
                    updateVolumeFromPosition(e.originalEvent || e);
                }
            });
            
            $(document).on('mouseup', function() {
                if (isDraggingVolume) {
                    isDraggingVolume = false;
                    volumeTrack.removeClass('is-dragging');
                }
            });
            
            $(document).on('touchend touchcancel', function() {
                if (isDraggingVolume) {
                    isDraggingVolume = false;
                    volumeTrack.removeClass('is-dragging');
                }
            });
            
            // Keyboard support for custom volume slider
            volumeTrack.on('keydown', function(e) {
                let handled = false;
                const step = 0.05; // 5% per key press
                
                switch(e.key) {
                    case 'ArrowRight':
                    case 'ArrowUp':
                        e.preventDefault();
                        self.setVolume(Math.min(1, self.volume + step));
                        handled = true;
                        break;
                    case 'ArrowLeft':
                    case 'ArrowDown':
                        e.preventDefault();
                        self.setVolume(Math.max(0, self.volume - step));
                        handled = true;
                        break;
                    case 'Home':
                        e.preventDefault();
                        self.setVolume(0);
                        handled = true;
                        break;
                    case 'End':
                        e.preventDefault();
                        self.setVolume(1);
                        handled = true;
                        break;
                }
            });

            // Speed control - add touch support
            this.controls.find('.ohmylms-speed-btn').on('click touchend', function(e) {
                // Prevent default for touch to avoid double-firing
                if (e.type === 'touchend') {
                    e.preventDefault();
                }
                self.controls.find('.ohmylms-speed-menu').toggleClass('active');
            });

            this.controls.find('.ohmylms-speed-menu button').on('click touchend', function(e) {
                e.stopPropagation();
                // Prevent default for touch to avoid double-firing
                if (e.type === 'touchend') {
                    e.preventDefault();
                }
                const speed = parseFloat($(this).data('speed'));
                self.setPlaybackRate(speed);
                self.controls.find('.ohmylms-speed-menu button').removeClass('active');
                $(this).addClass('active');
                self.controls.find('.ohmylms-speed-text').text(speed + 'x');
                self.controls.find('.ohmylms-speed-menu').removeClass('active');
            });

            // Progress bar - handle async for Vimeo with smooth dragging
            let isDragging = false;
            let lastSeekTime = 0;
            let pendingSeek = null;
            let cachedDuration = 0;
            let cachedBarOffset = 0;
            let cachedBarWidth = 0;
            
            // Cache DOM elements for better performance during drag
            const progressBar = self.controls.find('.ohmylms-progress-bar');
            const progressFilledEl = progressBar.find('.ohmylms-progress-filled')[0];
            const progressHandleEl = progressBar.find('.ohmylms-progress-handle')[0];
            const currentTimeDisplay = self.controls.find('.ohmylms-current-time');
            
            const updateProgressUI = (percent, targetTime) => {
                // Direct DOM updates for instant feedback
                const percentValue = (percent * 100) + '%';
                progressFilledEl.style.width = percentValue;
                progressHandleEl.style.left = percentValue;
                progressHandleEl.style.opacity = '1';
                progressHandleEl.style.transform = 'translateY(-50%) translateX(-50%) scale(1.2)';
                currentTimeDisplay[0].textContent = self.formatTime(targetTime);
            };
            
            const performSeek = (e) => {
                // Handle both mouse and touch events
                let clientX;
                if (e.type && e.type.indexOf('touch') !== -1) {
                    const touch = e.touches ? e.touches[0] : e.changedTouches[0];
                    clientX = touch.clientX;
                } else {
                    clientX = e.pageX || e.clientX;
                }
                
                // Use cached bar dimensions for instant calculation
                const percent = Math.max(0, Math.min(1, (clientX - cachedBarOffset) / cachedBarWidth));
                
                // Use cached duration for instant UI updates
                if (cachedDuration > 0) {
                    const targetTime = percent * cachedDuration;
                    pendingSeek = targetTime;
                    
                    // Update UI immediately - synchronous for smooth feedback
                    updateProgressUI(percent, targetTime);
                    
                    // Throttle actual seek calls to every 150ms to avoid overwhelming the player
                    const now = Date.now();
                    if (now - lastSeekTime > 150) {
                        lastSeekTime = now;
                        self.seekTo(targetTime);
                    }
                }
            };
            
            // Mouse events
            progressBar.on('mousedown', async function(e) {
                // Cache all values at drag start for smooth updates
                cachedDuration = self.platformHandler.getDuration();
                cachedBarOffset = progressBar.offset().left;
                cachedBarWidth = progressBar.width();
                isDragging = true;
                progressBar.addClass('is-seeking');
                performSeek(e);
                e.preventDefault();
            });
            
            // Touch events for mobile
            progressBar.on('touchstart', async function(e) {
                // Cache all values at drag start for smooth updates
                cachedDuration = self.platformHandler.getDuration();
                cachedBarOffset = progressBar.offset().left;
                cachedBarWidth = progressBar.width();
                isDragging = true;
                progressBar.addClass('is-seeking');
                performSeek(e.originalEvent);
                e.preventDefault();
            });
            
            // Use native addEventListener for better touch performance
            document.addEventListener('mousemove', (e) => {
                if (isDragging) {
                    performSeek(e);
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
            
            // Use passive: false for touchmove to allow preventDefault
            document.addEventListener('touchmove', (e) => {
                if (isDragging) {
                    performSeek(e);
                    e.preventDefault();
                    e.stopPropagation();
                }
            }, { passive: false });
            
            const onDragEnd = async () => {
                if (isDragging) {
                    isDragging = false;
                    progressBar.removeClass('is-seeking');
                    
                    // Perform final seek to the exact target position
                    if (pendingSeek !== null) {
                        await self.seekTo(pendingSeek);
                        pendingSeek = null;
                    }
                }
            };
            
            document.addEventListener('mouseup', onDragEnd);
            document.addEventListener('touchend', onDragEnd);
            document.addEventListener('touchcancel', onDragEnd);
            
            // Also support click/tap for single seeks
            progressBar.on('click', async function(e) {
                if (!isDragging) {
                    // Cache all values for click seeks
                    cachedDuration = self.platformHandler.getDuration();
                    cachedBarOffset = progressBar.offset().left;
                    cachedBarWidth = progressBar.width();
                    performSeek(e);
                    // Perform immediate seek for clicks
                    if (pendingSeek !== null) {
                        await self.seekTo(pendingSeek);
                        pendingSeek = null;
                    }
                }
            });

            // Fullscreen - add touch support
            this.controls.find('.ohmylms-fullscreen-btn').on('click touchend', function(e) {
                // Prevent default for touch to avoid double-firing
                if (e.type === 'touchend') {
                    e.preventDefault();
                }
                self.toggleFullscreen();
            });

            // Update progress with requestAnimationFrame for smoother updates
            const updateLoop = () => {
                // Update if playing OR if duration hasn't been loaded yet
                if (self.platformHandler && !isDragging) {
                    if (!self.platformHandler.isPaused || !self.durationKnown) {
                        self.updateProgress();
                    }
                }
                requestAnimationFrame(updateLoop);
            };
            requestAnimationFrame(updateLoop);

            // Hide controls on inactivity - support both mouse and touch
            let inactivityTimer;
            
            // Show controls on any interaction
            const showControlsOnInteraction = () => {
                self.showControls();
                clearTimeout(inactivityTimer);
                if (!self.platformHandler.isPaused) {
                    inactivityTimer = setTimeout(() => {
                        self.hideControls();
                    }, 3000);
                }
            };
            
            // Mouse events
            this.element.on('mousemove', showControlsOnInteraction);
            
            // Touch events for mobile
            this.element.on('touchstart', showControlsOnInteraction);
            this.element.on('touchmove', showControlsOnInteraction);
            
            // Also show controls when video is paused
            this.element.on('pause', () => {
                self.showControls();
                clearTimeout(inactivityTimer);
            });
        }

        setupKeyboardControls() {
            const self = this;
            
            // Make the video player focusable
            this.element.attr('tabindex', '0');
            
            // Add keyboard event listener
            this.element.on('keydown', function(e) {
                // Don't interfere if user is typing in an input field
                if ($('input, textarea').is(':focus')) {
                    return;
                }
                
                const key = e.key;
                const keyCode = e.keyCode || e.which;
                
                // Prevent default for keys we handle to avoid page scrolling
                const handledKeys = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', ' ', 'k', 'K', 'm', 'M', 'f', 'F'];
                if (handledKeys.includes(key) || (keyCode >= 48 && keyCode <= 57)) {
                    e.preventDefault();
                }
                
                switch (key) {
                    // Play/Pause
                    case ' ':  // Spacebar
                    case 'k':
                    case 'K':
                        if (self.platformHandler) {
                            if (self.platformHandler.isPaused) {
                                self.play();
                            } else {
                                self.pause();
                            }
                        }
                        break;
                    
                    // Volume Up
                    case 'ArrowUp':
                        self.adjustVolume(0.1);
                        break;
                    
                    // Volume Down
                    case 'ArrowDown':
                        self.adjustVolume(-0.1);
                        break;
                    
                    // Seek Backward (5 seconds)
                    case 'ArrowLeft':
                        self.seekRelative(-5);
                        break;
                    
                    // Seek Forward (5 seconds)
                    case 'ArrowRight':
                        self.seekRelative(5);
                        break;
                    
                    // Mute/Unmute
                    case 'm':
                    case 'M':
                        if (self.volume > 0) {
                            self.previousVolume = self.volume;
                            self.setVolume(0);
                        } else {
                            self.setVolume(self.previousVolume || 1);
                        }
                        break;
                    
                    // Fullscreen
                    case 'f':
                    case 'F':
                        self.toggleFullscreen();
                        break;
                    
                    // Number keys (0-9) - Seek to percentage
                    default:
                        if (keyCode >= 48 && keyCode <= 57) {
                            const digit = keyCode - 48;
                            self.seekToPercentage(digit * 10);
                        }
                        break;
                }
            });
            
            // Auto-focus on click to enable keyboard controls
            this.element.on('click', function() {
                $(this).focus();
            });
        }

        adjustVolume(delta) {
            const oldVolume = this.volume;
            const newVolume = Math.max(0, Math.min(1, this.volume + delta));
            const actualDelta = newVolume - oldVolume;
            this.setVolume(newVolume);
            this.showVolumeIndicator(newVolume, actualDelta);
        }

        async seekRelative(seconds) {
            if (!this.platformHandler) return;
            
            const currentTime = this.platformHandler.getCurrentTime();
            const duration = this.platformHandler.getDuration();
            const newTime = Math.max(0, Math.min(duration, currentTime + seconds));
            
            await this.seekTo(newTime);
            this.showSeekIndicator(seconds);
            
            // Manually update progress after seeking to show correct time
            setTimeout(() => this.updateProgress(), 100);
        }

        async seekToPercentage(percentage) {
            if (!this.platformHandler) return;
            
            const duration = this.platformHandler.getDuration();
            const targetTime = (percentage / 100) * duration;
            
            await this.seekTo(targetTime);
        }

        showVolumeIndicator(volume, delta) {
            // Remove existing indicator
            this.element.find('.ohmylms-volume-indicator').remove();
            
            const percentage = Math.round(volume * 100);
            const deltaPercentage = Math.abs(Math.round(delta * 100));
            const icon = volume === 0 ? 'mute' : 'volume';
            
            const volumeIcon = volume === 0 ? 
                `<path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z" fill="white"/>` :
                `<path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02z" fill="white"/>`;
            
            const indicator = $(`
                <div class="ohmylms-volume-indicator">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        ${volumeIcon}
                    </svg>
                    <div class="ohmylms-volume-text">
                        <span class="ohmylms-volume-current">${percentage}%</span>
                        <span class="ohmylms-volume-delta">${deltaPercentage}%</span>
                    </div>
                </div>
            `);
            
            this.element.append(indicator);
            
            // Fade out after 1 second
            setTimeout(() => {
                indicator.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 1000);
        }

        showSeekIndicator(seconds) {
            // Remove existing indicator
            this.element.find('.ohmylms-seek-indicator').remove();
            
            const absSeconds = Math.abs(seconds);
            const text = seconds > 0 ? `${absSeconds}s >>` : `<< ${absSeconds}s`;
            
            // Use different icons for forward/backward
            const icon = seconds > 0 ? 
                `<svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 13c0 4.4 3.6 8 8 8s8-3.6 8-8h-2c0 3.3-2.7 6-6 6s-6-2.7-6-6 2.7-6 6-6v4l5-5-5-5v4c-4.4 0-8 3.6-8 8z" fill="white"/>
                    <path d="M12.5 8v4.7l3.6 2.1-.8 1.2-4.3-2.5V8h1.5z" fill="white"/>
                </svg>` :
                `<svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 13c0 4.4-3.6 8-8 8s-8-3.6-8-8h2c0 3.3 2.7 6 6 6s6-2.7 6-6-2.7-6-6-6V3l-5 5 5 5V9c4.4 0 8 3.6 8 8z" fill="white"/>
                    <path d="M11 8v4.7l3.6 2.1-.8 1.2-4.3-2.5V8H11z" fill="white"/>
                </svg>`;
            
            const indicator = $(`
                <div class="ohmylms-seek-indicator">
                    ${icon}
                    <span>${text}</span>
                </div>
            `);
            
            this.element.append(indicator);
            
            // Fade out after 0.8 seconds
            setTimeout(() => {
                indicator.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 800);
        }

        async updateProgress() {
            if (!this.platformHandler || !this.controls) return;

            // For Vimeo, getCurrentTime and getDuration are now synchronous (cached)
            // For YouTube and HTML5, they're already synchronous
            let currentTime = this.platformHandler.getCurrentTime();
            const duration = this.platformHandler.getDuration();

            // Only update duration-dependent elements when duration is valid
            if (duration && duration > 0 && isFinite(duration)) {
                let timeToShow = currentTime;
                let percentForBar = (currentTime / duration) * 100;
                
                // If video has ended (onEnded was triggered), always show full duration
                // This handles cases where currentTime reports wrong value after video ends
                if (this.hasEnded) {
                    timeToShow = duration;
                    percentForBar = 100; // Progress bar at 100%
                } else {
                    // If paused and VERY close to end (within 0.3 seconds), show duration
                    // This handles browser reporting slightly less than duration when naturally ending
                    const timeFromEnd = duration - currentTime;
                    if (this.platformHandler.isPaused && timeFromEnd >= 0 && timeFromEnd < 0.3) {
                        timeToShow = duration;
                        percentForBar = 100; // Progress bar at 100%
                    }
                }
                
                this.controls.find('.ohmylms-current-time').text(this.formatTime(timeToShow));
                this.controls.find('.ohmylms-progress-filled').css('width', percentForBar + '%');
                this.controls.find('.ohmylms-progress-handle').css('left', percentForBar + '%');
                this.controls.find('.ohmylms-duration').text(this.formatTime(duration));
                this.durationKnown = true;
            } else {
                // If duration not available, show loading state
                this.controls.find('.ohmylms-current-time').text(this.formatTime(currentTime || 0));
                this.controls.find('.ohmylms-duration').text('00:00');
            }
        }

        formatTime(seconds) {
            // Round to nearest second to avoid display issues
            seconds = Math.round(seconds);
            
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = Math.floor(seconds % 60);
            
            if (h > 0) {
                return `${h}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
            }
            return `${m}:${s.toString().padStart(2, '0')}`;
        }

        initPlatformHandler() {
            const config = {
                url: this.options.url,
                autoplay: this.options.autoplay,
                loop: this.options.loop,
                muted: this.options.muted,
                showControls: this.options.showControls && !this.options.customControls,
                poster: this.options.poster,
                onReady: () => {
                    // Update duration display once video is ready
                    this.updateProgress();
                    if (this.options.onReady) this.options.onReady();
                },
                onPlay: () => {
                    this.updatePlayPauseButton(false);
                    if (this.bigPlayButton) this.bigPlayButton.hide();
                    if (this.logoOverlay) this.logoOverlay.hide();
                    if (this.options.onPlay) this.options.onPlay();
                },
                onPause: () => {
                    this.updatePlayPauseButton(true);
                    if (this.bigPlayButton) this.bigPlayButton.show();
                    if (this.logoOverlay) this.logoOverlay.show();
                    if (this.options.onPause) this.options.onPause();
                },
                onEnded: () => {
                    // Mark video as ended
                    this.hasEnded = true;
                    
                    // Force display of full duration when video ends
                    if (this.controls) {
                        const duration = this.platformHandler.getDuration();
                        if (typeof duration === 'number' && duration > 0) {
                            this.controls.find('.ohmylms-current-time').text(this.formatTime(duration));
                            this.controls.find('.ohmylms-progress-filled').css('width', '100%');
                        } else {
                            // For async getDuration (Vimeo)
                            Promise.resolve(duration).then(dur => {
                                if (dur && dur > 0) {
                                    this.controls.find('.ohmylms-current-time').text(this.formatTime(dur));
                                    this.controls.find('.ohmylms-progress-filled').css('width', '100%');
                                }
                            });
                        }
                    }
                    if (this.options.onEnded) this.options.onEnded();
                },
                onStateChange: this.options.onStateChange
            };

            switch (this.platform) {
                case 'youtube':
                    this.platformHandler = new YouTubeHandler(this.playerContainer[0], config);
                    break;
                case 'vimeo':
                    this.platformHandler = new VimeoHandler(this.playerContainer[0], config);
                    break;
                default:
                    this.platformHandler = new HTML5Handler(this.playerContainer[0], config);
            }

            this.platformHandler.init()
                .then(() => {
                    // Player initialized successfully
                    this.bindNativeFullscreenEvents();
                })
                .catch(error => {
                    this.handleError(error);
                });
        }

        // iPhone Safari has no Fullscreen API on arbitrary elements - only the
        // native <video> tag supports fullscreen, via webkitEnterFullscreen(),
        // and exit happens through Safari's own UI (no exitFullscreen call from us).
        // Keep our button icon/state in sync with that native lifecycle.
        bindNativeFullscreenEvents() {
            const videoEl = this.platformHandler && this.platformHandler.player;
            if (!videoEl || typeof videoEl.addEventListener !== 'function') return;

            videoEl.addEventListener('webkitbeginfullscreen', () => {
                this.isFullscreen = true;
                this.controls.find('.ohmylms-fullscreen-icon').hide();
                this.controls.find('.ohmylms-exit-fullscreen-icon').show();
            });

            videoEl.addEventListener('webkitendfullscreen', () => {
                this.isFullscreen = false;
                this.controls.find('.ohmylms-fullscreen-icon').show();
                this.controls.find('.ohmylms-exit-fullscreen-icon').hide();
            });
        }

        updatePlayPauseButton(isPaused) {
            if (!this.controls) return;

            if (isPaused) {
                this.controls.find('.ohmylms-play-icon').show();
                this.controls.find('.ohmylms-pause-icon').hide();
            } else {
                this.controls.find('.ohmylms-play-icon').hide();
                this.controls.find('.ohmylms-pause-icon').show();
            }
        }

        play() {
            if (this.platformHandler) {
                this.hasEnded = false; // Reset ended flag when playing
                this.platformHandler.play();
            }
        }

        pause() {
            if (this.platformHandler) {
                this.platformHandler.pause();
            }
        }

        setVolume(volume) {
            this.volume = Math.max(0, Math.min(1, volume));
            
            if (this.platformHandler) {
                this.platformHandler.setVolume(this.volume);
            }

            if (this.controls) {
                // Update fill bar and thumb position
                this.updateVolumeFill(this.volume * 100);
                
                if (this.volume === 0) {
                    this.controls.find('.ohmylms-volume-icon').hide();
                    this.controls.find('.ohmylms-mute-icon').show();
                } else {
                    this.controls.find('.ohmylms-volume-icon').show();
                    this.controls.find('.ohmylms-mute-icon').hide();
                }
            }
        }

        updateVolumeFill(percentage) {
            if (this.controls) {
                this.controls.find('.ohmylms-volume-fill').css('width', percentage + '%');
                this.controls.find('.ohmylms-volume-thumb').css('left', percentage + '%');
                // Update ARIA attribute for accessibility
                this.controls.find('.ohmylms-volume-track').attr('aria-valuenow', Math.round(percentage));
            }
        }

        setPlaybackRate(rate) {
            this.playbackRate = rate;
            
            if (this.platformHandler) {
                this.platformHandler.setPlaybackRate(rate);
            }
        }

        async seekTo(time) {
            if (this.platformHandler) {
                this.hasEnded = false; // Reset ended flag when seeking
                return await this.platformHandler.seekTo(time);
            }
        }

        toggleFullscreen() {
            const elem = this.element[0];
            const videoEl = this.platformHandler && this.platformHandler.player;

            // iPhone Safari doesn't support the Fullscreen API on container
            // elements (only iPadOS's desktop-class Safari does) - it only
            // supports native fullscreen on the <video> element itself.
            const containerFullscreenSupported = !!(
                document.fullscreenEnabled ||
                document.webkitFullscreenEnabled ||
                elem.requestFullscreen ||
                elem.mozRequestFullScreen ||
                elem.msRequestFullscreen
            );

            if (!containerFullscreenSupported && videoEl && typeof videoEl.webkitEnterFullscreen === 'function') {
                videoEl.webkitEnterFullscreen();
                return; // state/icons synced via webkitbeginfullscreen/webkitendfullscreen listeners
            }

            if (!this.isFullscreen) {
                if (elem.requestFullscreen) {
                    elem.requestFullscreen();
                } else if (elem.webkitRequestFullscreen) {
                    elem.webkitRequestFullscreen();
                } else if (elem.mozRequestFullScreen) {
                    elem.mozRequestFullScreen();
                } else if (elem.msRequestFullscreen) {
                    elem.msRequestFullscreen();
                }
                this.isFullscreen = true;
                this.controls.find('.ohmylms-fullscreen-icon').hide();
                this.controls.find('.ohmylms-exit-fullscreen-icon').show();
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
                this.isFullscreen = false;
                this.controls.find('.ohmylms-fullscreen-icon').show();
                this.controls.find('.ohmylms-exit-fullscreen-icon').hide();
            }
        }

        showControls() {
            if (this.controls) {
                this.controls.removeClass('hidden');
                this.controlsVisible = true;
            }
        }

        hideControls() {
            if (this.controls) {
                this.controls.addClass('hidden');
                this.controlsVisible = false;
            }
        }

        handleError(error) {
            let errorText = 'Unable to load video. Please try again later.';
            
            // Check if it's a MOV format issue
            if (error.message && error.message.includes('MOV format not supported')) {
                errorText = 'MOV video format is not supported by your browser. Please convert the video to MP4 format for better compatibility.';
            }
            
            const errorMessage = $(`
                <div class="ohmylms-video-error">
                    <p>${errorText}</p>
                </div>
            `);
            this.element.append(errorMessage);
        }

        destroy() {
            if (this.platformHandler) {
                this.platformHandler.destroy();
            }
            if (this.controls) {
                this.controls.remove();
            }
            this.element.empty();
        }
    }

    // jQuery plugin wrapper
    $.fn.ohMyLMSVideoPlayer = function(options) {
        return this.each(function() {
            const $this = $(this);
            let instance = $this.data('ohMyLMSVideoPlayer');

            if (!instance) {
                instance = new OhMyLMSVideoPlayer(this, options);
                $this.data('ohMyLMSVideoPlayer', instance);
            }

            return instance;
        });
    };

    // Auto-initialize video players
    function initVideoPlayers() {
        const players = $('.ohmylms-custom-video-player');
        
        if (players.length === 0) {
            return;
        }
        
        players.each(function() {
            const $player = $(this);
            const videoUrl = $player.data('video-url');
            
            const options = {
                url: videoUrl,
                autoplay: $player.data('autoplay') === 'true' || $player.data('autoplay') === true,
                loop: $player.data('loop') === 'true' || $player.data('loop') === true,
                muted: $player.data('muted') === 'true' || $player.data('muted') === true,
                showControls: false, // Always use custom controls
                customControls: true,
                poster: $player.data('poster') || null,
                logoUrl: $player.data('logo-url') || '',
            };
            
            if (videoUrl) {
                $player.ohMyLMSVideoPlayer(options);
            }
        });
    }
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        initVideoPlayers();
    });

    // Expose to global scope
    window.OhMyLMSVideoPlayer = OhMyLMSVideoPlayer;
    window.initOhMyLMSVideoPlayers = initVideoPlayers;

})(jQuery);
