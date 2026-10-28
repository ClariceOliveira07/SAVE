const scanner = document.querySelector('[data-scanner]');

if (scanner) {
	const video = scanner.querySelector('[data-camera]');
	const preview = scanner.querySelector('[data-preview]');
	const emptyPreview = scanner.querySelector('[data-preview-empty]');
	const liveTag = scanner.querySelector('[data-live-tag]');
	const message = scanner.querySelector('[data-camera-message]');
	const errorBox = scanner.querySelector('[data-error]');
	const successBox = scanner.querySelector('[data-success]');
	const processing = scanner.querySelector('[data-processing]');
	const editor = scanner.querySelector('[data-item-editor]');
	const emptyResults = scanner.querySelector('[data-empty-results]');
	const analyzeButton = scanner.querySelector('[data-analyze]');
	const saveButton = scanner.querySelector('[data-save-items]');
	const captureButton = scanner.querySelector('[data-take-photo]');
	const switchButton = scanner.querySelector('[data-switch-camera]');
	const fileInput = scanner.querySelector('[data-file-input]');
	const csrf = document.querySelector('meta[name="csrf-token"]').content;
	const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
	let stream = null;
	let facingMode = 'environment';
	let selectedFile = null;
	let previewUrl = null;
	let analyzedFile = null;

	const showError = (text) => {
		errorBox.textContent = text;
		errorBox.hidden = !text;
		successBox.hidden = true;
	};

	const stopCamera = () => {
		stream?.getTracks().forEach((track) => track.stop());
		stream = null;
		video.srcObject = null;
		liveTag.hidden = true;
		captureButton.disabled = true;
		switchButton.disabled = true;
	};

	const clearItems = () => {
		editor.replaceChildren();
		emptyResults.hidden = false;
		saveButton.hidden = true;
	};

	const showPreview = (file) => {
		stopCamera();
		selectedFile = file;
		analyzedFile = null;
		clearItems();
		errorBox.hidden = true;
		successBox.hidden = true;

		if (previewUrl) {
			URL.revokeObjectURL(previewUrl);
		}

		previewUrl = URL.createObjectURL(file);
		preview.src = previewUrl;
		preview.hidden = false;
		video.hidden = true;
		emptyPreview.hidden = true;
		analyzeButton.disabled = false;
		message.textContent = `${file.name} · ${(file.size / 1024 / 1024).toFixed(1)} MB`;
	};

	const validateFile = (file) => {
		const extensionAllowed = /\.(jpe?g|png|webp)$/i.test(file.name);

		if (!allowedTypes.includes(file.type) || !extensionAllowed) {
			showError('Formato não aceito. Escolha uma imagem JPG, PNG ou WEBP.');
			return false;
		}

		if (file.size > 10 * 1024 * 1024) {
			showError('A imagem deve ter no máximo 10 MB.');
			return false;
		}

		return true;
	};

	const openCamera = async () => {
		showError('');
		stopCamera();

		if (!navigator.mediaDevices?.getUserMedia) {
			showError('Este navegador não oferece acesso à câmera. Use HTTPS ou localhost e envie uma imagem da galeria.');
			return;
		}

		try {
			stream = await navigator.mediaDevices.getUserMedia({
				audio: false,
				video: { facingMode: { ideal: facingMode }, width: { ideal: 1600 }, height: { ideal: 1200 } },
			});
			video.srcObject = stream;
			video.hidden = false;
			preview.hidden = true;
			emptyPreview.hidden = true;
			liveTag.hidden = false;
			captureButton.disabled = false;
			switchButton.disabled = false;
			analyzeButton.disabled = true;
			message.textContent = 'Enquadre os alimentos e capture a imagem.';
		} catch (error) {
			stopCamera();
			if (error.name === 'NotAllowedError' || error.name === 'SecurityError') {
				showError('A permissão da câmera foi negada. Autorize o acesso nas configurações do navegador ou envie uma imagem.');
			} else if (error.name === 'NotFoundError' || error.name === 'OverconstrainedError') {
				showError('Nenhuma câmera compatível foi encontrada. Envie uma imagem da galeria.');
			} else {
				showError('Não foi possível abrir a câmera. Confira a permissão do navegador ou envie uma imagem.');
			}
		}
	};

	const addItem = (item = {}, confidence = null, labelText = null) => {
		const row = document.createElement('article');
		row.className = 'food-edit-row';
		const rowNumber = editor.children.length + 1;
		const confidenceLabel = Number.isFinite(Number(confidence))
			? `Confiança estimada: ${Math.round(Number(confidence) * 100)}%`
			: 'Confira os dados estimados';
		const fields = [
			['name', 'Nome do alimento', item.name ?? '', 'text', 'field-wide', true],
			['category', 'Categoria', item.category ?? 'Outros', 'text', 'field-half', true],
			['quantity', 'Quantidade estimada', item.estimated_quantity ?? item.quantity ?? '', 'number', 'field-half', true],
			['unit', 'Unidade', item.unit ?? 'unidades', 'text', 'field-half', true],
			['packaging', 'Embalagem', item.packaging ?? '', 'text', 'field-half', false],
			['expiration_date', 'Validade confirmada', item.expiration_date ?? '', 'date', 'field-half', false],
			['price', 'Preço conhecido (R$)', item.price ?? '', 'number', 'field-half', false],
			['barcode', 'Código de barras', item.barcode ?? '', 'text', 'field-half', false],
		];

		row.innerHTML = `<div class="food-row-header"><span class="food-row-title">Alimento ${rowNumber}</span><span class="food-row-meta">${labelText ? 'Rótulo detectado' : confidenceLabel}</span><button class="remove-item" type="button" aria-label="Remover alimento">×</button></div><div class="food-fields"></div>`;
		const fieldsContainer = row.querySelector('.food-fields');

		fields.forEach(([key, label, value, type, width, required]) => {
			const wrapper = document.createElement('div');
			wrapper.className = `field ${width}`;
			const inputId = `food-${rowNumber}-${key}`;
			const labelElement = document.createElement('label');
			labelElement.htmlFor = inputId;
			labelElement.textContent = label;
			const input = document.createElement('input');
			input.id = inputId;
			input.dataset.field = key;
			input.type = type;
			input.value = value;
			input.required = required;
			if (key === 'quantity') {
				input.min = '0.01';
				input.step = '0.01';
			}
			if (key === 'price') {
				input.min = '0';
				input.step = '0.01';
			}
			wrapper.append(labelElement, input);
			fieldsContainer.append(wrapper);
		});

		if (labelText) {
			const labelNote = document.createElement('p');
			labelNote.className = 'food-row-meta';
			labelNote.textContent = `Texto visível no rótulo: ${labelText}`;
			row.append(labelNote);
		}

		row.querySelector('.remove-item').addEventListener('click', () => {
			row.remove();
			emptyResults.hidden = editor.children.length > 0;
			saveButton.hidden = editor.children.length === 0;
		});
		editor.append(row);
		emptyResults.hidden = true;
		saveButton.hidden = false;
	};

	scanner.querySelector('[data-open-camera]').addEventListener('click', openCamera);
	switchButton.addEventListener('click', async () => {
		facingMode = facingMode === 'environment' ? 'user' : 'environment';
		await openCamera();
	});
	captureButton.addEventListener('click', () => {
		if (!video.videoWidth || !video.videoHeight) {
			showError('A câmera ainda está carregando. Tente novamente em instantes.');
			return;
		}

		const canvas = document.createElement('canvas');
		canvas.width = video.videoWidth;
		canvas.height = video.videoHeight;
		canvas.getContext('2d').drawImage(video, 0, 0);
		canvas.toBlob((blob) => {
			if (!blob) {
				showError('Não foi possível capturar a imagem. Tente novamente.');
				return;
			}
			const capturedFile = new File([blob], `save-captura-${Date.now()}.jpg`, { type: 'image/jpeg' });
			showPreview(capturedFile);
		}, 'image/jpeg', 0.92);
	});
	fileInput.addEventListener('change', () => {
		const [file] = fileInput.files;
		if (file && validateFile(file)) {
			showPreview(file);
		} else if (file) {
			fileInput.value = '';
		}
	});
	scanner.querySelector('[data-add-item]').addEventListener('click', () => addItem());

	analyzeButton.addEventListener('click', async () => {
		if (!selectedFile || analyzedFile === selectedFile) {
			return;
		}

		const formData = new FormData();
		formData.append('image', selectedFile);
		analyzeButton.disabled = true;
		processing.hidden = false;
		showError('');

		try {
			const response = await fetch(scanner.dataset.analyzeUrl, {
				method: 'POST',
				headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
				body: formData,
			});
			const result = await response.json();
			if (!response.ok) {
				throw new Error(result.message ?? Object.values(result.errors ?? {}).flat()[0] ?? 'Não foi possível analisar esta imagem.');
			}
			if (!Array.isArray(result.items)) {
				throw new Error('O serviço de processamento retornou uma resposta inválida.');
			}

			clearItems();
			result.items.forEach((item) => addItem(item, item.confidence, item.label_text));
			analyzedFile = selectedFile;
			if (result.items.length === 0) {
				successBox.textContent = result.message;
				if (result.image?.width && result.image?.height) {
					successBox.textContent += ` (${result.image.width} × ${result.image.height} px)`;
				}
				successBox.hidden = false;
			}
		} catch (error) {
			showError(error.message || 'Falha de comunicação. Tente novamente ou cadastre manualmente.');
			analyzeButton.disabled = false;
		} finally {
			processing.hidden = true;
		}
	});

	saveButton.addEventListener('click', async () => {
		const rows = [...editor.querySelectorAll('.food-edit-row')];
		const items = rows.map((row) => Object.fromEntries(
			[...row.querySelectorAll('[data-field]')].map((input) => [input.dataset.field, input.value.trim() || null]),
		));

		if (items.some((item) => !item.name || !item.category || !item.quantity || !item.unit)) {
			showError('Preencha nome, categoria, quantidade e unidade de cada alimento antes de salvar.');
			return;
		}

		saveButton.disabled = true;
		showError('');
		try {
			const response = await fetch(scanner.dataset.saveUrl, {
				method: 'POST',
				headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
				body: JSON.stringify({ items }),
			});
			const result = await response.json();
			if (!response.ok) {
				throw new Error(result.message ?? Object.values(result.errors ?? {}).flat()[0] ?? 'Não foi possível salvar os alimentos.');
			}
			successBox.textContent = result.message;
			successBox.hidden = false;
			clearItems();
			window.setTimeout(() => window.location.reload(), 900);
		} catch (error) {
			showError(error.message || 'Não foi possível salvar os alimentos.');
			saveButton.disabled = false;
		}
	});

	window.addEventListener('pagehide', stopCamera);
}

const recipePage = document.querySelector('[data-recipes]');

if (recipePage) {
	const generateButton = recipePage.querySelector('[data-generate-recipes]');
	const recipeGrid = recipePage.querySelector('[data-recipe-grid]');
	const loading = recipePage.querySelector('[data-recipe-loading]');
	const errorBox = recipePage.querySelector('[data-recipe-error]');
	const csrf = document.querySelector('meta[name="csrf-token"]').content;

	const createRecipeCard = (recipe, index) => {
		const card = document.createElement('article');
		card.className = 'recipe-card';
		const availableCount = recipe.ingredients.filter((ingredient) => ingredient.available).length;
		card.innerHTML = `<div class="recipe-card-top"><span class="recipe-number">${String(index + 1).padStart(2, '0')}</span><span class="recipe-match">${availableCount} itens disponíveis</span></div><h3></h3><p class="recipe-summary"></p><div class="recipe-ingredients"><h4>Ingredientes</h4><div class="recipe-ingredient-list"></div></div><details class="recipe-steps"><summary>Ver preparo</summary><ol></ol></details>`;
		card.querySelector('h3').textContent = recipe.title;
		card.querySelector('.recipe-summary').textContent = recipe.summary;
		const ingredients = card.querySelector('.recipe-ingredient-list');

		recipe.ingredients.forEach((ingredient) => {
			const line = document.createElement('div');
			line.className = `recipe-ingredient ${ingredient.available ? 'is-available' : 'is-missing'}`;
			const state = document.createElement('span');
			state.className = 'ingredient-state';
			state.textContent = ingredient.available ? '✓' : '+';
			state.setAttribute('aria-hidden', 'true');
			const name = document.createElement('span');
			name.textContent = ingredient.name;
			line.append(state, name);
			if (ingredient.expiring) {
				const expiry = document.createElement('small');
				expiry.textContent = 'validade cadastrada próxima';
				line.append(expiry);
			}
			if (!ingredient.available) {
				const missing = document.createElement('em');
				missing.textContent = 'falta';
				line.append(missing);
			}
			ingredients.append(line);
		});

		const steps = card.querySelector('.recipe-steps ol');
		recipe.steps.forEach((step) => {
			const item = document.createElement('li');
			item.textContent = step;
			steps.append(item);
		});

		return card;
	};

	if (generateButton) {
		generateButton.addEventListener('click', async () => {
			generateButton.disabled = true;
			loading.hidden = false;
			errorBox.hidden = true;

			try {
				const response = await fetch(recipePage.dataset.generateUrl, {
					method: 'POST',
					headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
				});
				const result = await response.json();
				if (!response.ok) {
					throw new Error(result.message ?? 'Não foi possível criar sugestões agora.');
				}
				recipeGrid.replaceChildren(...result.recipes.map(createRecipeCard));
				if (result.recipes.length === 0) {
					errorBox.textContent = 'Adicione alimentos confirmados à sua despensa para gerar sugestões.';
					errorBox.hidden = false;
				}
			} catch (error) {
				errorBox.textContent = error.message || 'Falha de comunicação. Tente novamente.';
				errorBox.hidden = false;
			} finally {
				loading.hidden = true;
				generateButton.disabled = false;
			}
		});
	}
}
