/**
 * Rapid — editor bundle entry point
 * Bundled into dist/editor.js via esbuild (IIFE format).
 * Build: 2026-04-05
 */

import EditorJS        from '@editorjs/editorjs';
import Header          from '@editorjs/header';
import NestedList      from '@editorjs/nested-list';
import Table           from '@editorjs/table';
import Embed           from '@editorjs/embed';
import Quote           from '@editorjs/quote';
import ImageTool       from '@editorjs/image';
import CodeTool        from '@editorjs/code';
import Delimiter       from '@editorjs/delimiter';
import Warning         from '@editorjs/warning';
import Checklist       from '@editorjs/checklist';
import RawTool         from '@editorjs/raw';
import AttachesTool    from '@editorjs/attaches';
import InlineCode      from '@editorjs/inline-code';
import Marker          from '@editorjs/marker';
import Underline       from '@editorjs/underline';
import LinkTool        from '@editorjs/link';
import Alert           from 'editorjs-alert';
import ToggleBlock     from 'editorjs-toggle-block';

// Patch editorjs-toggle-block: addSupportForDragAndDropActions uses
// document.querySelector('.ce-toolbar__settings-btn') globally — returns null
// when toolbar isn't rendered yet. Guard against null to prevent crash.
(function patchToggleBlock() {
	const proto = ToggleBlock.prototype;
	if (!proto || !proto.addSupportForDragAndDropActions) return;
	const orig = proto.addSupportForDragAndDropActions;
	proto.addSupportForDragAndDropActions = function() {
		try {
			orig.call(this);
		} catch(e) {
			// null toolbar — retry after delay (same as toggle's own retry logic)
			if (!this.readOnly) setTimeout(() => this.addSupportForDragAndDropActions(), 250);
		}
	};
})();
import DragDrop        from 'editorjs-drag-drop';
import Undo            from 'editorjs-undo';


// ── Custom tool: Embed (YouTube / Vimeo / generic) ───────────────────────

class RapidEmbedTool {
	static get toolbox() {
		return {
			title: 'Video / Embed',
			icon: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>',
		};
	}

	static get isReadOnlySupported() { return true; }

	constructor({ data, config, api, readOnly }) {
		this._data    = data || { service: '', source: '', embed: '', width: 16, height: 9, caption: '' };
		this._readOnly = readOnly;
		this._wrapper = null;
	}

	render() {
		this._wrapper = document.createElement('div');
		this._wrapper.classList.add('rapid-embed-tool');
		this._wrapper.style.cssText = 'border:2px dashed #ddd;border-radius:4px;padding:12px;';
		this._wrapper.addEventListener('keydown', e => e.stopPropagation());
		this._wrapper.addEventListener('keyup',   e => e.stopPropagation());
		this._wrapper.addEventListener('keypress',e => e.stopPropagation());
		this._renderUI();
		return this._wrapper;
	}

	_renderUI() {
		this._wrapper.innerHTML = '';

		if (this._data.embed) {
			const preview = document.createElement('div');
			preview.style.cssText = 'position:relative;padding-bottom:56.25%;height:0;border-radius:4px;overflow:hidden;';
			const iframe = document.createElement('iframe');
			iframe.src = this._data.embed;
			iframe.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;border:none;';
			iframe.allowFullscreen = true;
			iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
			iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen');
			preview.appendChild(iframe);
			this._wrapper.appendChild(preview);

			const cap = document.createElement('input');
			cap.type = 'text';
			cap.value = this._data.caption || '';
			cap.placeholder = 'Caption (optional)';
			cap.style.cssText = 'width:100%;margin-top:8px;padding:4px 8px;border:1px solid #ddd;border-radius:3px;font-size:13px;box-sizing:border-box;';
			cap.addEventListener('input', e => { this._data.caption = e.target.value; });
			this._wrapper.appendChild(cap);

			const clear = document.createElement('button');
			clear.textContent = '\u00d7 Remove';
			clear.style.cssText = 'margin-top:6px;font-size:12px;color:#999;background:none;border:none;cursor:pointer;padding:0;';
			clear.addEventListener('click', () => {
				this._data = { service: '', source: '', embed: '', width: 16, height: 9, caption: '' };
				this._renderUI();
			});
			this._wrapper.appendChild(clear);
			return;
		}

		const label = document.createElement('div');
		label.textContent = 'Paste YouTube or Vimeo URL:';
		label.style.cssText = 'font-size:13px;color:#555;margin-bottom:8px;';
		this._wrapper.appendChild(label);

		const row = document.createElement('div');
		row.style.cssText = 'display:flex;gap:8px;';

		const inp = document.createElement('input');
		inp.type = 'text';
		inp.placeholder = 'https://www.youtube.com/watch?v=...';
		inp.style.cssText = 'flex:1;padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;';

		const btn = document.createElement('button');
		btn.textContent = 'Embed';
		btn.style.cssText = 'padding:6px 14px;background:#2563eb;color:#fff;border:none;border-radius:4px;font-size:13px;cursor:pointer;';
		btn.addEventListener('click', () => this._processUrl(inp.value.trim()));
		inp.addEventListener('keydown', e => { if (e.key === 'Enter') this._processUrl(inp.value.trim()); });

		row.append(inp, btn);
		this._wrapper.appendChild(row);
	}

	_processUrl(url) {
		if (!url) return;
		const ytMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/shorts\/)([\w-]+)/);
		if (ytMatch) {
			this._data.service = 'youtube';
			this._data.source  = url;
			this._data.embed   = `https://www.youtube-nocookie.com/embed/${ytMatch[1]}`;
			this._data.width   = 16; this._data.height = 9;
			this._renderUI(); return;
		}
		const vmMatch = url.match(/vimeo\.com\/(\d+)/);
		if (vmMatch) {
			this._data.service = 'vimeo';
			this._data.source  = url;
			this._data.embed   = `https://player.vimeo.com/video/${vmMatch[1]}`;
			this._data.width   = 16; this._data.height = 9;
			this._renderUI(); return;
		}
		if (url.startsWith('http')) {
			this._data.service = 'other';
			this._data.source  = url;
			this._data.embed   = url;
			this._data.width   = 16; this._data.height = 9;
			this._renderUI();
		}
	}

	save() { return this._data; }
}


// ── Tool registry ─────────────────────────────────────────────────────────

function buildTools(config) {
	const uploadUrl    = config?.uploadUrl    ?? '';
	const pageId       = config?.pageId       ?? 0;
	const fieldName    = config?.fieldName    ?? '';
	const headerLevels = config?.headerLevels ?? [2, 3, 4];
	const headerDefault = config?.headerDefault ?? 2;
	const maxUploadMB  = config?.maxUploadMB  ?? 10;

	return {
		// Block tools
		header: {
			class:  Header,
			config: {
				levels:       headerLevels,
				defaultLevel: headerDefault,
			},
		},
		nestedList: {
			class:         NestedList,
			inlineToolbar: true,
			config:        { defaultStyle: 'unordered' },
		},
		table: {
			class:         Table,
			inlineToolbar: true,
		},
		quote: {
			class:         Quote,
			inlineToolbar: true,
		},
		image: {
			class:  ImageTool,
			config: {
				endpoints:             { byFile: uploadUrl },
				types:                 'image/*',
				additionalRequestData: { pageId, fieldName },
				maxImageFileSize:      maxUploadMB * 1024 * 1024,
			},
		},
		attaches: {
			class:  AttachesTool,
			config: {
				// AttachesTool has no additionalRequestData — pass pageId in URL
				endpoint: uploadUrl.replace('/upload/', '/attach/') + '?' + new URLSearchParams({ pageId, fieldName }),
				field:    'file',
			},
		},
		code:      { class: CodeTool },
		delimiter: { class: Delimiter },
		warning: {
			class:  Warning,
			config: { titlePlaceholder: 'Title', messagePlaceholder: 'Message' },
		},
		checklist: {
			class:         Checklist,
			inlineToolbar: true,
		},
		raw:   { class: RawTool },
		alert: {
			class:  Alert,
			config: { defaultType: 'info', types: { primary: 'Primary', secondary: 'Secondary', info: 'Info', success: 'Success', warning: 'Warning', danger: 'Danger', light: 'Light', dark: 'Dark' } },
		},
		toggle: {
			class:  ToggleBlock,
			inlineToolbar: true,
		},
		linkTool: {
			class:  LinkTool,
			config: { endpoint: uploadUrl.replace('/upload/', '/link/') },
		},
		embed: {
			class:  RapidEmbedTool,
		},
		// Inline tools
		inlineCode: { class: InlineCode },
		marker:     { class: Marker },
		underline:  { class: Underline },

		// Tunes
	};
}

// ── Init ──────────────────────────────────────────────────────────────────

async function doSave(api, textarea) {
	try {
		const saved = await api.saver.save();
		textarea.value = JSON.stringify(saved);
		textarea.dispatchEvent(new Event('change', { bubbles: true }));
	} catch (e) {
		console.error('[Rapid] save error', e);
	}
}

function initEditor({ holderId, valueId, data, config }) {
	const holder   = document.getElementById(holderId);
	const textarea = document.getElementById(valueId);
	if (!holder || !textarea) return;

	const allowed     = config?.allowedBlocks ?? [];
	const inlineTools = config?.inlineTools   ?? [];
	const allTools    = buildTools(config);

	// Filter block tools — all tools respect allowedBlocks including toggle/linkTool/alert
	const tools = allowed.length
		? Object.fromEntries(Object.entries(allTools).filter(([k]) => allowed.includes(k)))
		: allTools;

	// Build inlineToolbar config — controls which inline tools appear in the toolbar
	// Editor.js built-ins (bold, italic, link) are controlled via the tools config with inlineToolbar
	// Custom tools (underline, inlineCode, marker) can be fully removed from registry
	// Filter inline tools
	// Custom tools (registerd in tools object) can be deleted directly.
	// Built-in tools (bold, italic, link) are controlled via inlineToolbar array on each block.
	const CUSTOM_INLINE   = ['underline', 'inlineCode', 'marker'];
	const BUILTIN_INLINE  = ['bold', 'italic', 'link', 'strikethrough'];
	const ALL_INLINE      = [...CUSTOM_INLINE, ...BUILTIN_INLINE];

	if (inlineTools.length) {
		// Remove custom inline tools not in the list
		for (const key of CUSTOM_INLINE) {
			if (!inlineTools.includes(key)) delete tools[key];
		}
		// For built-ins: set inlineToolbar per block tool to restrict which appear
		// Build the inlineToolbar array: only tools in the allowed list
		const allowedInline = ALL_INLINE.filter(k => inlineTools.includes(k));
		if (allowedInline.length) {
			for (const key of Object.keys(tools)) {
				if (CUSTOM_INLINE.includes(key) || BUILTIN_INLINE.includes(key)) continue;
				// Block tool — set its inlineToolbar
				if (typeof tools[key] === 'object') {
					tools[key] = { ...tools[key], inlineToolbar: allowedInline };
				}
			}
		}
	}

	const debounceMs  = config?.debounce ?? 300;
	let   saveTimer   = null;

	const editor = new EditorJS({
		holder,
		data:        data ?? { blocks: [] },
		tools,
		i18n:        config?.i18n ?? {},
		minHeight:   config?.minHeight ?? 50,
		autofocus:   false,
		placeholder: config?.placeholder ?? 'Start writing\u2026',

		onChange: (api) => {
			if (debounceMs > 0) {
				clearTimeout(saveTimer);
				saveTimer = setTimeout(() => doSave(api, textarea), debounceMs);
			} else {
				doSave(api, textarea);
			}
		},
	});

	holder._ejsEditor = editor;

	// Init plugins after editor is ready
	editor.isReady.then(() => {
		new Undo({ editor });

		// DragDrop
		const initDragDrop = (attempt = 0) => {
			try {
				const toolbar = holder.querySelector('.ce-toolbar');
				if (!toolbar && attempt < 3) {
					setTimeout(() => initDragDrop(attempt + 1), 150 * (attempt + 1));
					return;
				}
				new DragDrop(editor);
			} catch(e) {
				if (attempt < 3) setTimeout(() => initDragDrop(attempt + 1), 150 * (attempt + 1));
			}
		};
		setTimeout(() => initDragDrop(), 150);
	}).catch(() => {});
}

function processQueue() {
	(window.EJSQueue ?? []).forEach(initEditor);
	window.EJSQueue = { push: initEditor };
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', processQueue);
} else {
	processQueue();
}
