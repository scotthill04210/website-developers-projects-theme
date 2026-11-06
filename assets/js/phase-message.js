(function () {
	'use strict';

	function escapeHtml(value) {
		return value
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	function toEditorHtml(value) {
		if (/<[a-z][\s\S]*>/i.test(value)) {
			return value;
		}

		if ('' === value.trim()) {
			return '';
		}

		return value.split(/\n{2,}/).map(function (part) {
			return '<p>' + escapeHtml(part).replace(/\n/g, '<br>') + '</p>';
		}).join('');
	}

	function syncEditor(editor) {
		var field = document.getElementById(editor.getAttribute('data-lidev-editor') || '');

		if (!field) {
			return;
		}

		field.value = editor.innerHTML;
		field.dispatchEvent(new Event('input', { bubbles: true }));
	}

	function placeCaret(editor) {
		var range = document.createRange();
		var selection = window.getSelection();

		range.selectNodeContents(editor);
		range.collapse(false);

		if (!selection) {
			return;
		}

		selection.removeAllRanges();
		selection.addRange(range);
	}

	function command(editor, name, value) {
		editor.focus();
		document.execCommand(name, false, value || null);
		syncEditor(editor);
	}

	function bindEditor(field) {
		if (field.getAttribute('data-lidev-ready') === '1') {
			return;
		}

		field.setAttribute('data-lidev-ready', '1');

		var wrap = document.createElement('div');
		wrap.className = 'lidev-signoffs__editor';

		var tools = document.createElement('div');
		tools.className = 'lidev-signoffs__editor-tools';
		tools.setAttribute('role', 'toolbar');
		tools.setAttribute('aria-label', 'Message formatting');

		var buttons = [
			{ cmd: 'bold', label: 'B', title: 'Bold' },
			{ cmd: 'italic', label: 'I', title: 'Italic' },
			{ cmd: 'insertUnorderedList', label: 'List', title: 'Bulleted list' },
			{ cmd: 'insertOrderedList', label: '1.', title: 'Numbered list' }
		];

		buttons.forEach(function (item) {
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'lidev-signoffs__editor-button';
			button.setAttribute('data-lidev-cmd', item.cmd);
			button.title = item.title;
			button.textContent = item.label;

			if ('bold' === item.cmd) {
				button.style.fontWeight = '700';
			}

			if ('italic' === item.cmd) {
				button.style.fontStyle = 'italic';
			}

			tools.appendChild(button);
		});

		var linkButton = document.createElement('button');
		linkButton.type = 'button';
		linkButton.className = 'lidev-signoffs__editor-button';
		linkButton.setAttribute('data-lidev-cmd', 'link');
		linkButton.title = 'Link';
		linkButton.textContent = 'Link';
		tools.appendChild(linkButton);

		var linkRow = document.createElement('div');
		linkRow.className = 'lidev-signoffs__editor-link';
		linkRow.hidden = true;

		var linkInput = document.createElement('input');
		linkInput.type = 'url';
		linkInput.placeholder = 'https://';
		linkInput.setAttribute('aria-label', 'Link address');

		var linkAdd = document.createElement('button');
		linkAdd.type = 'button';
		linkAdd.className = 'lidev-signoffs__editor-button';
		linkAdd.textContent = 'Add link';

		linkRow.appendChild(linkInput);
		linkRow.appendChild(linkAdd);

		var body = document.createElement('div');
		body.className = 'lidev-signoffs__editor-body';
		body.setAttribute('contenteditable', 'true');
		body.setAttribute('role', 'textbox');
		body.setAttribute('aria-multiline', 'true');
		body.setAttribute('data-lidev-editor', field.id);
		body.innerHTML = toEditorHtml(field.value);

		var label = field.closest('label');
		var caption = label ? label.querySelector('span') : null;

		if (caption) {
			if (!caption.id) {
				caption.id = field.id + '-label';
			}

			body.setAttribute('aria-labelledby', caption.id);
		}

		if (label) {
			label.removeAttribute('for');
		}

		wrap.appendChild(tools);
		wrap.appendChild(linkRow);
		wrap.appendChild(body);
		field.classList.add('lidev-signoffs__editor-source');

		if (label && label.parentNode) {
			label.parentNode.insertBefore(wrap, label.nextSibling);
		} else {
			field.parentNode.insertBefore(wrap, field);
		}

		var savedRange = null;

		function rememberRange() {
			var selection = window.getSelection();

			if (!selection || !selection.rangeCount || !body.contains(selection.anchorNode)) {
				return;
			}

			savedRange = selection.getRangeAt(0).cloneRange();
		}

		function restoreRange() {
			body.focus();

			if (!savedRange) {
				placeCaret(body);
				return;
			}

			var selection = window.getSelection();

			if (!selection) {
				return;
			}

			selection.removeAllRanges();
			selection.addRange(savedRange);
		}

		tools.addEventListener('mousedown', function (event) {
			if (event.target.closest('button')) {
				event.preventDefault();
			}
		});

		tools.addEventListener('click', function (event) {
			var button = event.target.closest('[data-lidev-cmd]');

			if (!button) {
				return;
			}

			event.preventDefault();
			var cmd = button.getAttribute('data-lidev-cmd');

			if ('link' === cmd) {
				rememberRange();
				linkRow.hidden = false;
				linkInput.value = 'https://';
				linkInput.focus();
				return;
			}

			command(body, cmd);
		});

		linkAdd.addEventListener('click', function (event) {
			event.preventDefault();
			var url = linkInput.value.trim();

			if ('' === url) {
				linkRow.hidden = true;
				return;
			}

			if (!/^https?:\/\//i.test(url) && !/^mailto:/i.test(url)) {
				url = 'https://' + url;
			}

			restoreRange();
			document.execCommand('createLink', false, url);
			linkRow.hidden = true;
			syncEditor(body);
		});

		body.addEventListener('keyup', rememberRange);
		body.addEventListener('mouseup', rememberRange);

		body.addEventListener('paste', function (event) {
			var clipboard = event.clipboardData || window.clipboardData;
			var text = clipboard ? clipboard.getData('text/plain') : '';

			if ('' === text) {
				return;
			}

			event.preventDefault();
			document.execCommand('insertText', false, text);
			syncEditor(body);
		});

		body.addEventListener('input', function () {
			syncEditor(body);
		});
	}

	function insertToken(button) {
		var id = button.getAttribute('data-lidev-target') || '';
		var token = button.getAttribute('data-lidev-insert') || '';
		var editor = document.querySelector('[data-lidev-editor="' + id + '"]');
		var field = document.getElementById(id);

		if ('' === token) {
			return;
		}

		if (editor) {
			editor.focus();

			var selection = window.getSelection();

			if (!selection || !selection.rangeCount || !editor.contains(selection.anchorNode)) {
				placeCaret(editor);
			}

			document.execCommand('insertText', false, token);
			syncEditor(editor);
			return;
		}

		if (!field) {
			return;
		}

		var start = typeof field.selectionStart === 'number' ? field.selectionStart : field.value.length;
		var end = typeof field.selectionEnd === 'number' ? field.selectionEnd : field.value.length;

		field.value = field.value.slice(0, start) + token + field.value.slice(end);
		field.focus();

		if (typeof field.setSelectionRange === 'function') {
			var caret = start + token.length;
			field.setSelectionRange(caret, caret);
		}

		field.dispatchEvent(new Event('input', { bubbles: true }));
	}

	document.querySelectorAll('.lidev-email-source').forEach(bindEditor);

	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-lidev-insert]');

		if (!button) {
			return;
		}

		event.preventDefault();
		insertToken(button);
	});

	document.addEventListener('submit', function (event) {
		var form = event.target;

		if (!form || !form.querySelectorAll) {
			return;
		}

		form.querySelectorAll('[data-lidev-editor]').forEach(syncEditor);
	});

	document.querySelectorAll('[data-lidev-preview]').forEach(function (form) {
		var frame = form.querySelector('[data-lidev-email-frame]');
		var caption = form.querySelector('[data-lidev-email-caption]');
		var dialog = form.querySelector('[data-lidev-preview-dialog]');
		var open = form.querySelector('[data-lidev-preview-open]');
		var timer = 0;

		if (!frame || !dialog || !open) {
			return;
		}

		function refresh() {
			var link = form.querySelector('[name="lidev_link"]');
			var note = form.querySelector('[name="lidev_message"]');
			var body = new FormData();

			body.append('action', 'lidev_preview_phase_email');
			body.append('lidev_nonce', form.getAttribute('data-lidev-nonce') || '');
			body.append('project_id', form.getAttribute('data-lidev-project') || '');
			body.append('phase_id', form.getAttribute('data-lidev-phase') || '');
			body.append('lidev_link', link ? link.value : '');
			body.append('lidev_message', note ? note.value : '');

			window.fetch(form.getAttribute('data-lidev-ajax') || '', {
				method: 'POST',
				credentials: 'same-origin',
				body: body
			}).then(function (response) {
				return response.json();
			}).then(function (data) {
				if (!data || !data.success || !data.data) {
					return;
				}

				frame.srcdoc = data.data.html || '';

				if (caption) {
					caption.textContent = data.data.caption || '';
				}
			}).catch(function () {});
		}

		open.addEventListener('click', function () {
			if (typeof dialog.showModal === 'function') {
				dialog.showModal();
			} else {
				dialog.setAttribute('open', 'open');
			}

			refresh();
		});

		form.querySelectorAll('[data-lidev-preview-close]').forEach(function (button) {
			button.addEventListener('click', function () {
				if (typeof dialog.close === 'function') {
					dialog.close();
				} else {
					dialog.removeAttribute('open');
				}
			});
		});

		dialog.addEventListener('click', function (event) {
			if (event.target === dialog && typeof dialog.close === 'function') {
				dialog.close();
			}
		});

		form.addEventListener('input', function () {
			if (!dialog.open) {
				return;
			}

			window.clearTimeout(timer);
			timer = window.setTimeout(refresh, 250);
		});
	});
}());
