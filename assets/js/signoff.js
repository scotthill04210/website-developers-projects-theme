/**
 * Type or draw a signature. The submit button stays off until the form is complete.
 */
(function () {
	'use strict';

	var form = document.querySelector('[data-lidev-sign]');

	if (!form) {
		return;
	}

	var name = form.querySelector('#lidev-signer-name');
	var ack = form.querySelector('#lidev-acknowledge');
	var typeInput = form.querySelector('[data-lidev-type]');
	var drawnInput = form.querySelector('[data-lidev-drawn]');
	var typed = form.querySelector('[data-lidev-typed]');
	var canvas = form.querySelector('[data-lidev-pad]');
	var clearButton = form.querySelector('[data-lidev-clear]');
	var submit = form.querySelector('button[type="submit"]');
	var mode = 'typed';
	var inked = false;
	var drawing = false;
	var context = canvas && canvas.getContext ? canvas.getContext('2d') : null;

	function point(event) {
		var rect = canvas.getBoundingClientRect();
		var scaleX = rect.width ? canvas.width / rect.width : 1;
		var scaleY = rect.height ? canvas.height / rect.height : 1;

		return {
			x: (event.clientX - rect.left) * scaleX,
			y: (event.clientY - rect.top) * scaleY
		};
	}

	function refresh() {
		var readyName = name && name.value.trim().length >= 2;
		var readySign = mode === 'typed' ? readyName : inked;
		var ready = !!(readyName && readySign && ack && ack.checked);

		if (typed) {
			typed.textContent = name ? name.value.trim() : '';
		}

		if (submit) {
			submit.disabled = !ready;
		}
	}

	function show(next) {
		mode = next === 'drawn' ? 'drawn' : 'typed';

		if (typeInput) {
			typeInput.value = mode;
		}

		Array.prototype.forEach.call(form.querySelectorAll('[data-lidev-mode]'), function (button) {
			var selected = button.getAttribute('data-lidev-mode') === mode;
			button.setAttribute('aria-selected', selected ? 'true' : 'false');
		});

		Array.prototype.forEach.call(form.querySelectorAll('[data-lidev-panel]'), function (item) {
			item.hidden = item.getAttribute('data-lidev-panel') !== mode;
		});

		refresh();
	}

	function markInk() {
		inked = true;

		if (drawnInput && canvas) {
			drawnInput.value = canvas.toDataURL('image/png');
		}

		refresh();
	}

	function clearPad() {
		if (context && canvas) {
			context.clearRect(0, 0, canvas.width, canvas.height);
		}

		inked = false;

		if (drawnInput) {
			drawnInput.value = '';
		}

		refresh();
	}

	if (context) {
		context.lineWidth = 2.5;
		context.lineCap = 'round';
		context.lineJoin = 'round';
		context.strokeStyle = '#1E1E21';

		canvas.addEventListener('pointerdown', function (event) {
			var at = point(event);
			drawing = true;
			canvas.setPointerCapture(event.pointerId);
			context.beginPath();
			context.moveTo(at.x, at.y);
			context.lineTo(at.x + 2, at.y + 2);
			context.stroke();
			markInk();
		});

		canvas.addEventListener('pointermove', function (event) {
			if (!drawing) {
				return;
			}

			var at = point(event);
			context.lineTo(at.x, at.y);
			context.stroke();
			markInk();
		});

		function stop(event) {
			if (!drawing) {
				return;
			}

			drawing = false;

			if (canvas.hasPointerCapture(event.pointerId)) {
				canvas.releasePointerCapture(event.pointerId);
			}
		}

		canvas.addEventListener('pointerup', stop);
		canvas.addEventListener('pointercancel', stop);
	}

	Array.prototype.forEach.call(form.querySelectorAll('[data-lidev-mode]'), function (button) {
		button.addEventListener('click', function () {
			show(button.getAttribute('data-lidev-mode'));
		});
	});

	if (clearButton) {
		clearButton.addEventListener('click', clearPad);
	}

	if (name) {
		name.addEventListener('input', refresh);
	}

	if (ack) {
		ack.addEventListener('change', refresh);
	}

	form.addEventListener('submit', function (event) {
		refresh();

		if (submit && submit.disabled) {
			event.preventDefault();
		}
	});

	show('typed');
})();
