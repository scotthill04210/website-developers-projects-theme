/**
 * Hamburger and slide-in menu for viewports under 1024px.
 */
(function () {
	'use strict';

	var toggle = document.getElementById('lidev-nav-toggle');
	var drawer = document.getElementById('lidev-nav-drawer');
	var backdrop = document.getElementById('lidev-nav-backdrop');
	var closeBtn = document.getElementById('lidev-nav-close');

	if (!drawer || !backdrop) {
		return;
	}

	var openLabel = (toggle && toggle.getAttribute('data-label-open')) || 'Open menu';
	var closeLabel = (toggle && toggle.getAttribute('data-label-close')) || 'Close menu';

	function isOpen() {
		return document.body.classList.contains('lidev-nav-open');
	}

	function setOpen(open) {
		document.body.classList.toggle('lidev-nav-open', open);
		drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
		drawer.inert = !open;

		if (toggle) {
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			toggle.setAttribute('aria-label', open ? closeLabel : openLabel);
		}

		backdrop.hidden = !open;

		if (open) {
			var first = drawer.querySelector('a, button');
			if (first) {
				first.focus({ preventScroll: true });
			}
		}
	}

	function close(restoreFocus) {
		if (!isOpen()) {
			return;
		}

		setOpen(false);

		if (restoreFocus && toggle) {
			toggle.focus({ preventScroll: true });
		}
	}

	if (toggle) {
		toggle.addEventListener('click', function (event) {
			event.preventDefault();
			if (isOpen()) {
				close(false);
			} else {
				setOpen(true);
			}
		});
	}

	if (closeBtn) {
		closeBtn.addEventListener('click', function (event) {
			event.preventDefault();
			close(true);
		});
	}

	backdrop.addEventListener('click', function () {
		close(true);
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			close(true);
		}
	});

	drawer.addEventListener('click', function (event) {
		if (event.target.closest('a')) {
			close(false);
		}
	});

	var mq = window.matchMedia('(min-width: 1024px)');
	function onBreakpoint(event) {
		if (event.matches) {
			close(false);
		}
	}
	if (mq.addEventListener) {
		mq.addEventListener('change', onBreakpoint);
	} else if (mq.addListener) {
		mq.addListener(onBreakpoint);
	}
})();
