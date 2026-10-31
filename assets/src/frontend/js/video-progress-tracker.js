/**
 * OhMyLMS Video Progress Tracker
 * 
 * Tracks video watch progress for YouTube, Vimeo, and self-hosted videos
 * 
 * @package OhMyLMS
 * @since 1.1.0
 */

(function($) {
    'use strict';

    const OhMyLMSVideoTracker = {
        // Configuration
        config: {
            saveInterval: 10000, // Save progress every 10 seconds
            heartbeatInterval: 5000, // Check playback every 5 seconds
            completionThreshold: 90, // Default 90% watched to mark as complete (overridden from settings)
            minWatchDuration: 1, // Minimum seconds to start tracking (like YouTube)
        },

        // State
        state: {
            lessonId: 0,
            courseId: 0,
            totalDuration: 0,
            watchedSegments: [],
            lastPosition: 0,
            lastTrackedPosition: 0, // Track the last position we recorded
            isPlaying: false,
            saveTimer: null,
            heartbeatTimer: null,
            player: null,
            playerType: null, // 'youtube', 'vimeo', 'html5'
            hasInitialized: false,
            resumePosition: 0, // Position to resume from
            hasResumed: false, // Whether we've already sought to resume position
        },

        /**
         * Initialize video tracking
         */
        init() {
            // Only initialize on lesson pages
            if (!this.isLessonPage()) {
                return;
            }
            
            // Get completion threshold from localized data
            if (typeof ohmylms_frontend_params !== 'undefined' && ohmylms_frontend_params.video_completion_threshold) {
                this.config.completionThreshold = parseInt(ohmylms_frontend_params.video_completion_threshold, 10);
            }

            // A lesson's own "watch N%" completion rule wins over the site setting (the server applies the same).
            const lessonPercent = parseInt($('[data-ohmylms-watch-percent]').first().attr('data-ohmylms-watch-percent'), 10);
            if (lessonPercent >= 1 && lessonPercent <= 100) {
                this.config.completionThreshold = lessonPercent;
            }

            // Get lesson and course IDs
            this.state.lessonId = this.getLessonId();
            this.state.courseId = this.getCourseId();


            if (!this.state.lessonId || !this.state.courseId) {
                return;
            }

            // Check if user is logged in
            if (!ohmylms_frontend_params.current_student_id) {
                return;
            }


            // Initialize appropriate player type
            this.detectAndInitializePlayer();

            // Save progress before page unload
            $(window).on('beforeunload', () => this.saveProgress());

            // Handle visibility change (tab switch, minimize)
            $(document).on('visibilitychange', () => {
                if (document.hidden) {
                    this.saveProgress();
                }
            });
        },

        /**
         * Detect and initialize the appropriate video player
         */
        detectAndInitializePlayer() {
            
            // Check for custom video player container first
            const customPlayer = $('.ohmylms-custom-video-player');
            if (customPlayer.length) {
                this.initCustomPlayer(customPlayer[0]);
                return;
            }
            
            // Check for YouTube iframe
            const youtubeIframe = $('iframe[src*="youtube.com"], iframe[src*="youtu.be"]');
            if (youtubeIframe.length) {
                this.initYouTubePlayer(youtubeIframe[0]);
                return;
            }

            // Check for Vimeo iframe
            const vimeoIframe = $('iframe[src*="vimeo.com"]');
            if (vimeoIframe.length) {
                this.initVimeoPlayer(vimeoIframe[0]);
                return;
            }

            // Check for HTML5 video
            const html5Video = $('video.the-video, .ohmylms-video-player video');
            if (html5Video.length) {
                this.initHTML5Player(html5Video[0]);
                return;
            }
        },

        /**
         * Initialize tracking for custom video player
         * Uses the OhMyLMSVideoPlayer instance and its platformHandler
         */
        initCustomPlayer(container) {
            const videoUrl = $(container).attr('data-video-url');
            
            if (!videoUrl) {
                return;
            }
            
            // Determine video type from URL
            const isYouTube = videoUrl.includes('youtube.com') || videoUrl.includes('youtu.be');
            const isVimeo = videoUrl.includes('vimeo.com');
            
            
            // Wait for the OhMyLMSVideoPlayer instance to be created AND duration to be available
            const checkInterval = setInterval(async () => {
                const playerInstance = $(container).data('ohMyLMSVideoPlayer');
                
                if (playerInstance && playerInstance.platformHandler && playerInstance.platformHandler.isReady) {
                    // Also check if duration is available before initializing
                    let duration = 0;
                    if (isVimeo) {
                        duration = await playerInstance.platformHandler.getDuration();
                    } else {
                        duration = playerInstance.platformHandler.getDuration();
                    }
                    
                    if (duration > 0) {
                        clearInterval(checkInterval);
                        await this.initCustomPlayerTracking(playerInstance, isYouTube, isVimeo);
                    }
                }
            }, 200); // Check every 200ms instead of 100ms
            
            // Clear interval after 15 seconds if player not found
            setTimeout(() => {
                clearInterval(checkInterval);
            }, 15000); // Increased from 10s to 15s
        },

        /**
         * Initialize tracking using the custom player's platform handler
         */
        async initCustomPlayerTracking(playerInstance, isYouTube, isVimeo) {
            this.state.player = playerInstance.platformHandler;
            this.state.playerInstance = playerInstance; // Keep reference to wrapper
            
            // Get duration based on player type
            if (isYouTube) {
                this.state.playerType = 'youtube';
                this.state.totalDuration = this.state.player.getDuration();
            } else if (isVimeo) {
                this.state.playerType = 'vimeo';
                this.state.totalDuration = await this.state.player.getDuration();
            } else {
                this.state.playerType = 'html5';
                this.state.totalDuration = this.state.player.getDuration();
            }
            
            // Verify we have a valid duration (should already be checked, but double-check)
            if (!this.state.totalDuration || this.state.totalDuration === 0) {
                return;
            }
            
            // Mark as initialized BEFORE loading progress
            this.state.hasInitialized = true;
            
            // Load progress
            const progress = await this.loadProgress();
            let resumePosition = 0;
            if (progress && progress.last_position > 1 && !progress.is_completed) {
                resumePosition = progress.last_position;
                
                // Update UI to show resume position (including handle)
                setTimeout(() => {
                    if (playerInstance.controls) {
                        playerInstance.controls.find('.ohmylms-current-time').text(playerInstance.formatTime(resumePosition));
                        const percentForBar = (resumePosition / this.state.totalDuration) * 100;
                        playerInstance.controls.find('.ohmylms-progress-filled').css('width', percentForBar + '%');
                        playerInstance.controls.find('.ohmylms-progress-handle').css('left', percentForBar + '%');
                    }
                }, 100);
            }
            
            // Track whether we've seeked to resume position
            let hasResumed = false;
            
            // Hook into the player's onPlay and onPause callbacks
            const originalOnPlay = playerInstance.options.onPlay;
            const originalOnPause = playerInstance.options.onPause;
            
            playerInstance.options.onPlay = async () => {
                // If we have a resume position and haven't resumed yet, seek to it BEFORE starting tracking
                if (!hasResumed && resumePosition > 0) {
                    hasResumed = true;
                    await this.seekToPosition(resumePosition);
                    this.state.lastTrackedPosition = resumePosition;
                    await new Promise(resolve => setTimeout(resolve, 200));
                } else if (this.state.lastTrackedPosition === 0) {
                    // Initialize tracking position when starting from beginning
                    this.state.lastTrackedPosition = await this.getCurrentTime();
                }
                
                this.state.isPlaying = true;
                this.startTracking();
                if (originalOnPlay) originalOnPlay();
            };
            
            playerInstance.options.onPause = async () => {
                this.state.isPlaying = false;
                // Track final position before pausing
                const currentTime = await this.getCurrentTime();
                this.updateWatchedSegments(currentTime);
                this.stopTracking();
                this.saveProgress();
                if (originalOnPause) originalOnPause();
            };
            
            // Handle video ended event
            const originalOnEnded = playerInstance.options.onEnded;
            playerInstance.options.onEnded = async () => {
                this.state.isPlaying = false;
                const currentTime = await this.getCurrentTime();
                this.updateWatchedSegments(currentTime);
                this.stopTracking();
                this.saveProgress();
                if (originalOnEnded) originalOnEnded();
            };
        },

        /**
         * Initialize YouTube player tracking
         */
        initYouTubePlayer(iframe) {
            this.state.playerType = 'youtube';
            
            // Load YouTube IFrame API if not already loaded
            if (typeof YT === 'undefined' || typeof YT.Player === 'undefined') {
                const tag = document.createElement('script');
                tag.src = 'https://www.youtube.com/iframe_api';
                const firstScriptTag = document.getElementsByTagName('script')[0];
                firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

                // Wait for API to load
                window.onYouTubeIframeAPIReady = () => {
                    this.setupYouTubePlayer(iframe);
                };
            } else {
                this.setupYouTubePlayer(iframe);
            }
        },

        /**
         * Setup YouTube player with API
         */
        setupYouTubePlayer(iframe) {
            // Ensure iframe has an ID
            if (!iframe.id) {
                iframe.id = 'ohmylms-youtube-player-' + this.state.lessonId;
            }

            // Enable JS API in iframe src if not already enabled
            const src = iframe.src;
            if (src.indexOf('enablejsapi=1') === -1) {
                iframe.src = src + (src.indexOf('?') === -1 ? '?' : '&') + 'enablejsapi=1';
            }

            // Create YouTube player
            this.state.player = new YT.Player(iframe.id, {
                events: {
                    'onReady': (event) => this.onYouTubeReady(event),
                    'onStateChange': (event) => this.onYouTubeStateChange(event),
                }
            });
        },

        /**
         * YouTube player ready callback
         */
        onYouTubeReady(event) {
            this.state.totalDuration = event.target.getDuration();
            this.state.hasInitialized = true;
            
            // Load progress and store resume position
            this.loadProgress().then((progress) => {
                if (progress && progress.last_position > 0 && !progress.is_completed) {
                    this.state.resumePosition = progress.last_position;
                    this.state.hasResumed = false;
                }
            });
        },

        /**
         * YouTube player state change callback
         */
        onYouTubeStateChange(event) {
            if (event.data === YT.PlayerState.PLAYING) {
                // If we have a resume position and haven't resumed yet, seek to it
                if (!this.state.hasResumed && this.state.resumePosition > 0) {
                    this.seekToPosition(this.state.resumePosition);
                    this.state.lastTrackedPosition = this.state.resumePosition;
                    this.state.hasResumed = true;
                } else if (this.state.lastTrackedPosition === 0) {
                    this.state.lastTrackedPosition = event.target.getCurrentTime();
                }
                
                this.state.isPlaying = true;
                this.startTracking();
            } else if (event.data === YT.PlayerState.PAUSED || event.data === YT.PlayerState.ENDED) {
                this.state.isPlaying = false;
                this.updateWatchedSegments(event.target.getCurrentTime());
                this.stopTracking();
                this.saveProgress();
            }
        },

        /**
         * Initialize Vimeo player tracking
         */
        initVimeoPlayer(iframe) {
            this.state.playerType = 'vimeo';
            
            // Load Vimeo Player API if not already loaded
            if (typeof Vimeo === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://player.vimeo.com/api/player.js';
                script.onload = () => {
                    this.setupVimeoPlayer(iframe);
                };
                document.head.appendChild(script);
            } else {
                this.setupVimeoPlayer(iframe);
            }
        },

        /**
         * Setup Vimeo player with API
         */
        setupVimeoPlayer(iframe) {
            this.state.player = new Vimeo.Player(iframe);
            this.state.hasInitialized = true;

            // Get duration
            this.state.player.getDuration().then((duration) => {
                this.state.totalDuration = duration;
                
                // Load progress and store resume position
                this.loadProgress().then((progress) => {
                    if (progress && progress.last_position > 0 && !progress.is_completed) {
                        this.state.resumePosition = progress.last_position;
                        this.state.hasResumed = false;
                    }
                });
            });

            // Listen to events
            this.state.player.on('play', async () => {
                // If we have a resume position and haven't resumed yet, seek to it
                if (!this.state.hasResumed && this.state.resumePosition > 0) {
                    await this.seekToPosition(this.state.resumePosition);
                    this.state.lastTrackedPosition = this.state.resumePosition;
                    this.state.hasResumed = true;
                    await new Promise(resolve => setTimeout(resolve, 200));
                } else if (this.state.lastTrackedPosition === 0) {
                    this.state.lastTrackedPosition = await this.state.player.getCurrentTime();
                }
                
                this.state.isPlaying = true;
                this.startTracking();
            });

            this.state.player.on('pause', async () => {
                this.state.isPlaying = false;
                const currentTime = await this.state.player.getCurrentTime();
                this.updateWatchedSegments(currentTime);
                this.stopTracking();
                this.saveProgress();
            });

            this.state.player.on('ended', async () => {
                this.state.isPlaying = false;
                const currentTime = await this.state.player.getCurrentTime();
                this.updateWatchedSegments(currentTime);
                this.stopTracking();
                this.saveProgress();
            });

            this.state.player.on('timeupdate', (data) => {
                this.updateWatchedSegments(data.seconds);
            });
        },

        /**
         * Initialize HTML5 video player tracking
         */
        initHTML5Player(video) {
            this.state.playerType = 'html5';
            this.state.player = video;
            this.state.totalDuration = video.duration || 0;
            this.state.hasInitialized = true;

            // If duration not available yet, wait for loadedmetadata
            if (!this.state.totalDuration) {
                $(video).on('loadedmetadata', () => {
                    this.state.totalDuration = video.duration;
                    
                    // Load progress and store resume position
                    this.loadProgress().then((progress) => {
                        if (progress && progress.last_position > 0 && !progress.is_completed) {
                            this.state.resumePosition = progress.last_position;
                            this.state.hasResumed = false;
                        }
                    });
                });
            } else {
                // Load progress and store resume position
                this.loadProgress().then((progress) => {
                    if (progress && progress.last_position > 0 && !progress.is_completed) {
                        this.state.resumePosition = progress.last_position;
                        this.state.hasResumed = false;
                    }
                });
            }

            // Listen to events
            $(video).on('play', () => {
                // If we have a resume position and haven't resumed yet, seek to it
                if (!this.state.hasResumed && this.state.resumePosition > 0) {
                    this.seekToPosition(this.state.resumePosition);
                    this.state.lastTrackedPosition = this.state.resumePosition;
                    this.state.hasResumed = true;
                } else if (this.state.lastTrackedPosition === 0) {
                    this.state.lastTrackedPosition = video.currentTime;
                }
                
                this.state.isPlaying = true;
                this.startTracking();
            });

            $(video).on('pause', () => {
                this.state.isPlaying = false;
                this.stopTracking();
                this.saveProgress();
            });

            $(video).on('ended', () => {
                this.state.isPlaying = false;
                this.stopTracking();
                this.saveProgress();
            });

            $(video).on('timeupdate', () => {
                this.updateWatchedSegments(video.currentTime);
            });
        },

        /**
         * Start tracking video progress
         */
        startTracking() {
            // Start periodic save
            if (!this.state.saveTimer) {
                this.state.saveTimer = setInterval(() => {
                    this.saveProgress();
                }, this.config.saveInterval);
            }

            // Start heartbeat to track current position
            if (!this.state.heartbeatTimer) {
                this.state.heartbeatTimer = setInterval(() => {
                    this.trackCurrentPosition();
                }, this.config.heartbeatInterval);
            }
        },

        /**
         * Stop tracking video progress
         */
        stopTracking() {
            if (this.state.saveTimer) {
                clearInterval(this.state.saveTimer);
                this.state.saveTimer = null;
            }

            if (this.state.heartbeatTimer) {
                clearInterval(this.state.heartbeatTimer);
                this.state.heartbeatTimer = null;
            }
        },

        /**
         * Track current playback position
         */
        trackCurrentPosition() {
            if (!this.state.isPlaying) return;

            this.getCurrentTime().then((currentTime) => {
                if (currentTime > 0) {
                    this.updateWatchedSegments(currentTime);
                }
            });
        },

        /**
         * Get current playback time
         */
        async getCurrentTime() {
            if (this.state.playerType === 'youtube' && this.state.player) {
                return this.state.player.getCurrentTime();
            } else if (this.state.playerType === 'vimeo' && this.state.player) {
                return await this.state.player.getCurrentTime();
            } else if (this.state.playerType === 'html5' && this.state.player) {
                // Support both platform handler (method) and direct video element (property)
                if (typeof this.state.player.getCurrentTime === 'function') {
                    return this.state.player.getCurrentTime();
                } else if (typeof this.state.player.currentTime === 'number') {
                    return this.state.player.currentTime;
                }
            }
            return 0;
        },

        /**
         * Update watched segments
         */
        updateWatchedSegments(currentTime) {
            if (currentTime < this.config.minWatchDuration) {
                return;
            }

            this.state.lastPosition = currentTime;

            // If we have a previous position, create a segment from last to current
            // This properly tracks continuous watching
            if (this.state.lastTrackedPosition > 0 && this.state.isPlaying) {
                const timeDiff = Math.abs(currentTime - this.state.lastTrackedPosition);
                
                // Only create segment if positions are close (not a seek/jump)
                // Allow up to heartbeat interval + 1 second tolerance
                if (timeDiff <= (this.config.heartbeatInterval / 1000) + 1) {
                    const start = Math.min(this.state.lastTrackedPosition, currentTime);
                    const end = Math.max(this.state.lastTrackedPosition, currentTime);
                    
                    if (end > start) {
                        const segment = { start: Math.floor(start), end: Math.ceil(end) };
                        this.state.watchedSegments.push(segment);
                        this.state.watchedSegments = this.mergeSegments(this.state.watchedSegments);
                    }
                }
            }
            
            this.state.lastTrackedPosition = currentTime;
        },

        /**
         * Merge overlapping time segments
         */
        mergeSegments(segments) {
            if (segments.length <= 1) return segments;

            // Sort segments by start time
            segments.sort((a, b) => a.start - b.start);

            const merged = [segments[0]];

            for (let i = 1; i < segments.length; i++) {
                const current = segments[i];
                const last = merged[merged.length - 1];

                if (current.start <= last.end + 1) {
                    // Overlapping or adjacent - merge
                    last.end = Math.max(last.end, current.end);
                } else {
                    // Non-overlapping - add as new segment
                    merged.push(current);
                }
            }

            return merged;
        },

        /**
         * Calculate total watched duration
         */
        calculateWatchedDuration() {
            return this.state.watchedSegments.reduce((total, segment) => {
                return total + (segment.end - segment.start);
            }, 0);
        },

        /**
         * Load saved progress from server
         * @returns {Promise} Promise that resolves with progress data
         */
        loadProgress() {
            // Guard on required state instead of hasInitialized to allow loading during init
            if (!this.state.lessonId || typeof ohmylms_frontend_params === 'undefined' || !ohmylms_frontend_params.ajax_url) {
                return Promise.resolve(null);
            }

            return new Promise((resolve, reject) => {
                $.ajax({
                    url: ohmylms_frontend_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ohmylms_get_video_progress',
                        nonce: ohmylms_frontend_params.video_progress_nonce,
                        lesson_id: this.state.lessonId,
                    },
                    success: (response) => {
                        if (response.success && response.data.progress) {
                            const progress = response.data.progress;
                            resolve(progress);
                        } else {
                            resolve(null);
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(error);
                    }
                });
            });
        },

        /**
         * Save progress to server
         */
        saveProgress() {
            if (!this.state.hasInitialized || this.state.totalDuration === 0) {
                return;
            }

            const watchedDuration = this.calculateWatchedDuration();
            
            // Don't save if less than minimum watch duration
            if (watchedDuration < this.config.minWatchDuration) {
                return;
            }

            $.ajax({
                url: ohmylms_frontend_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'ohmylms_save_video_progress',
                    nonce: ohmylms_frontend_params.video_progress_nonce,
                    lesson_id: this.state.lessonId,
                    course_id: this.state.courseId,
                    watched_duration: watchedDuration,
                    total_duration: this.state.totalDuration,
                    last_position: this.state.lastPosition,
                },
                success: (response) => {
                    if (response.success) {
                        // Trigger custom event for lesson completion
                        if (response.data.progress && response.data.progress.is_completed) {
                            $(document).trigger('ohmylms:video:completed', {
                                lessonId: this.state.lessonId,
                                courseId: this.state.courseId,
                            });

                            // The server completed the lesson; show it instead of the "watch N%" hint.
                            $('#ohmylms-completed-lesson[data-ohmylms-video-pending]')
                                .prop('checked', true)
                                .removeAttr('data-ohmylms-video-pending aria-describedby');
                            $('#ohmylms-video-completion-hint').remove();
                            
                            // Clear progress from database so video starts fresh next time
                            this.clearProgress();
                        }
                    }
                },
                error: (xhr, status, error) => {
                    // Silent error
                }
            });
        },

        /**
         * Clear video progress from database
         */
        clearProgress() {
            if (!this.state.lessonId || typeof ohmylms_frontend_params === 'undefined' || !ohmylms_frontend_params.ajax_url) {
                return;
            }

            $.ajax({
                url: ohmylms_frontend_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'ohmylms_clear_video_progress',
                    nonce: ohmylms_frontend_params.video_progress_nonce,
                    lesson_id: this.state.lessonId,
                },
                success: (response) => {
                    if (response.success) {
                        // Reset local state
                        this.state.watchedSegments = [];
                        this.state.lastPosition = 0;
                        this.state.lastTrackedPosition = 0;
                        this.state.resumePosition = 0;
                    }
                },
                error: (xhr, status, error) => {
                    // Silent error
                }
            });
        },

        /**
         * Seek to a specific position in the video
         */
        async seekToPosition(position) {
            try {
                if (this.state.playerType === 'youtube' && this.state.player) {
                    if (typeof this.state.player.seekTo === 'function') {
                        this.state.player.seekTo(position, true);
                    }
                } else if (this.state.playerType === 'vimeo' && this.state.player) {
                    if (typeof this.state.player.setCurrentTime === 'function') {
                        await this.state.player.setCurrentTime(position);
                    } else if (typeof this.state.player.seekTo === 'function') {
                        const result = this.state.player.seekTo(position);
                        if (result && typeof result.then === 'function') {
                            await result;
                        }
                    }
                } else if (this.state.playerType === 'html5' && this.state.player) {
                    // Platform handler uses seekTo() method
                    if (typeof this.state.player.seekTo === 'function') {
                        this.state.player.seekTo(position);
                    } else if (typeof this.state.player.currentTime !== 'undefined') {
                        // Fallback for direct video element
                        this.state.player.currentTime = position;
                    }
                }
            } catch (error) {
                console.error('[VideoTracker] Seek error:', error);
            }
        },

        /**
         * Check if current page is a lesson page
         */
        isLessonPage() {
            return $('body').hasClass('single-lesson') || 
                   $('body').hasClass('single-ohmylms-lesson') || 
                   $('.ohmylms-lesson-content-body').length > 0;
        },

        /**
         * Get lesson ID from page
         */
        getLessonId() {
            // Try to get from data attribute
            const lessonId = $('[data-lesson-id]').attr('data-lesson-id');
            if (lessonId) return parseInt(lessonId);

            // Try to get from body class
            const bodyClasses = $('body').attr('class');
            const match = bodyClasses && bodyClasses.match(/postid-(\d+)/);
            if (match) return parseInt(match[1]);

            // Try to get from global variable if available
            if (typeof ohmylms_lesson_id !== 'undefined') {
                return parseInt(ohmylms_lesson_id);
            }

            return 0;
        },

        /**
         * Get course ID from page
         */
        getCourseId() {
            // Try to get from data attribute
            const courseId = $('[data-course-id]').attr('data-course-id');
            if (courseId) return parseInt(courseId);

            // Try to get from global variable if available
            if (typeof ohmylms_course_id !== 'undefined') {
                return parseInt(ohmylms_course_id);
            }

            // Try to extract from page elements
            const courseLink = $('.ohmylms-course-link').attr('href');
            if (courseLink) {
                const match = courseLink.match(/post=(\d+)/);
                if (match) return parseInt(match[1]);
            }

            return 0;
        },
    };

    // Initialize on document ready
    $(document).ready(function() {
        OhMyLMSVideoTracker.init();
    });

})(jQuery);
