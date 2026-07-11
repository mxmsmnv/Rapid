document.addEventListener('click', async (event) => {
	const button = event.target.closest('.rapid-fe-save');
	if (!button) return;

	const config = JSON.parse(button.dataset.config);
	const toolbar = button.closest('.rapid-fe-toolbar');
	const status = toolbar?.querySelector('.rapid-fe-status');
	const holder = document.getElementById(config.holderId);
	const editor = holder?._ejsEditor;
	if (!editor) {
		if (status) status.textContent = 'Editor not ready';
		return;
	}

	button.disabled = true;
	if (status) {
		status.className = 'rapid-fe-status';
		status.textContent = 'Saving…';
	}

	try {
		const data = JSON.stringify(await editor.save());
		const jsonInput = document.getElementById(`${config.holderId}-json`);
		if (jsonInput) jsonInput.value = data;

		const response = await fetch(config.saveUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({
				pageId: config.pageId,
				fieldName: config.fieldName,
				nonce: config.nonce,
				data,
			}),
		});
		const result = await response.json();
		if (status && result.success) {
			status.className = 'rapid-fe-status ok';
			status.textContent = 'Saved ✓';
			setTimeout(() => { status.textContent = ''; }, 3000);
		} else if (status) {
			status.className = 'rapid-fe-status err';
			status.textContent = `Error: ${result.error || 'unknown'}`;
		}
	} catch (error) {
		if (status) {
			status.className = 'rapid-fe-status err';
			status.textContent = `Network error: ${error.message}`;
		}
	} finally {
		button.disabled = false;
	}
});
