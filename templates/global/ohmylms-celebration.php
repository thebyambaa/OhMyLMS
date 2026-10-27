<?php
/**
 * Celebration Template
 * Include this once in your theme or plugin footer.
 *
 * @package OhMyLMS
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! \OhMyLMS\Extensions\Interactivity::once( 'celebration' ) ) {
	return;
}
ohmylms_enqueue_interactivity_module( 'ohmylms/gamification' );
?>

<style>
	@media (prefers-reduced-motion: reduce) {
	.ohmylms-celebration-popup { animation: none !important; right: 20px; }
	#ohmylms-confetti-canvas { display: none; }
	}
	.ohmylms-body-wrapper {
	pointer-events: none;
	}

	.ohmylms-celebration-popup {
	position: fixed;
	top: 20px;
	right: -400px;
	background: white;
	padding: 18px 30px;
	border-radius: 12px;
	font-size: 18px;
	color: #333;
	z-index: 99999;
	animation:
		ohmylms-slideBounceIn 0.6s cubic-bezier(0.68, -0.6, 0.32, 1.6) forwards,
		ohmylms-pulseGlow 1.5s ease-in-out 0.6s,
		ohmylms-fadeOut 1s 2.5s forwards;
	box-shadow: 0 0 0 rgba(0, 0, 0, 0);
	}

	@keyframes ohmylms-slideBounceIn {
	0% {
		right: -400px;
		transform: scale(0.9);
		opacity: 0;
	}
	60% {
		right: 30px;
		transform: scale(1.05);
		opacity: 1;
	}
	100% {
		right: 20px;
		transform: scale(1);
	}
	}

	@keyframes ohmylms-pulseGlow {
	0% {
		box-shadow: 0 0 0 rgba(79, 70, 229, 0);
	}
	50% {
		box-shadow: 0 0 20px rgba(79, 70, 229, 0.4);
	}
	100% {
		box-shadow: 0 0 0 rgba(79, 70, 229, 0);
	}
	}

	@keyframes ohmylms-fadeOut {
	to {
		opacity: 0;
		transform: translateY(-20px);
	}
	}

	canvas#ohmylms-confetti-canvas {
	position: fixed;
	top: 0;
	left: 0;
	pointer-events: none;
	z-index: 99998;
	}
</style>

<canvas id="ohmylms-confetti-canvas"></canvas>
<div data-wp-interactive="ohmylms/gamification" data-wp-init="callbacks.mount">
	<div class="ohmylms-celebration-popup" role="status" aria-live="polite" hidden data-wp-bind--hidden="!state.visible">
	<span data-wp-text="state.message"></span>
	<button type="button" aria-label="<?php esc_attr_e( 'Dismiss notification', 'ohmylms' ); ?>" data-wp-on--click="actions.dismiss">×</button>
	</div>
</div>

<script>
/**
 * OhMyLMS Celebration Handler
 * 
 * Usage:
 *   OhMyLMSCelebrate.show('🎉 You earned 10 points!');
 */
(function(window, document) {
	const OhMyLMSCelebrate = {
	canvas: null,
	ctx: null,
	confettiPieces: [],
	animationFrameId: null,

	/**
	 * Initialize canvas and context
	 */
	initCanvas: function() {
		this.canvas = document.getElementById("ohmylms-confetti-canvas");
		if (!this.canvas) return;
		this.ctx = this.canvas.getContext("2d");
		this.resizeCanvas();
		window.addEventListener("resize", this.resizeCanvas.bind(this));
	},

	resizeCanvas: function() {
		this.canvas.width = window.innerWidth;
		this.canvas.height = window.innerHeight;
	},

	/**
	 * Generate confetti pieces
	 */
	createConfettiPieces: function(count = 100) {
		return Array.from({ length: count }).map(() => ({
		x: Math.random() * this.canvas.width,
		y: Math.random() * -this.canvas.height,
		size: Math.random() * 6 + 4,
		color: `hsl(${Math.floor(Math.random() * 360)}, 70%, 60%)`,
		speed: Math.random() * 3 + 2,
		rotation: Math.random() * 2 * Math.PI,
		opacity: 1,
		fade: false
		}));
	},

	/**
	 * Draw and animate confetti
	 */
	drawConfetti: function() {
		this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
		this.confettiPieces.forEach(p => {
		this.ctx.globalAlpha = p.opacity;
		this.ctx.fillStyle = p.color;
		this.ctx.save();
		this.ctx.translate(p.x, p.y);
		this.ctx.rotate(p.rotation);
		this.ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size);
		this.ctx.restore();

		p.y += p.speed;
		p.rotation += 0.01;
		if (p.fade) p.opacity -= 0.02;
		if (p.y > this.canvas.height) {
			p.y = -20;
			p.x = Math.random() * this.canvas.width;
		}
		});

		this.ctx.globalAlpha = 1;
		this.confettiPieces = this.confettiPieces.filter(p => p.opacity > 0);

		if (this.confettiPieces.length > 0) {
		this.animationFrameId = requestAnimationFrame(this.drawConfetti.bind(this));
		} else {
		cancelAnimationFrame(this.animationFrameId);
		this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
		}
	},

	/**
	 * Start the confetti animation
	 */
	startConfetti: function(duration = 5000) {
		this.confettiPieces = this.createConfettiPieces();
		this.drawConfetti();
		setTimeout(() => {
		this.confettiPieces.forEach(p => p.fade = true);
		}, duration);
	},

	/**
	 * Show the celebration message and animation
	 * @param {string} message - The message to show
	 */
	show: function(message) {
		if (!this.canvas) this.initCanvas();

		// Backwards-compatible facade: the Interactivity store owns notification state.
		if (window.ohmylmsCelebrationReady) {
		document.dispatchEvent(new CustomEvent('ohmylms:celebrate', {detail: {message: String(message)}}));
		} else {
		(window.ohmylmsCelebrationQueue ||= []).push(String(message));
		}
	}
	};

	// Expose globally
	window.OhMyLMSCelebrate = OhMyLMSCelebrate;
	// Auto-init
	document.addEventListener("DOMContentLoaded", () => {
	OhMyLMSCelebrate.initCanvas();
	});
})(window, document);
</script>
