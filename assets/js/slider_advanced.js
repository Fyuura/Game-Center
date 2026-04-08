document.addEventListener('DOMContentLoaded', () => {
	const sliders = document.querySelectorAll('.slider-advanced');
	sliders.forEach((root) => {
		const main = root.querySelector('.slider-advanced__main');
		const thumbs = Array.from(root.querySelectorAll('.slider-advanced__thumb'));
		const prevBtn = root.querySelector('.slider-advanced__nav--prev');
		const nextBtn = root.querySelector('.slider-advanced__nav--next');

		let currentIndex = parseInt(root.getAttribute('data-initial-index') || '0', 10) || 0;

		function createMediaElement(type, src, poster) {
			if (type === 'video') {
				const video = document.createElement('video');
				video.className = 'slider-advanced__main-media';
				if (poster) video.setAttribute('poster', poster);
				video.setAttribute('controls', '');
				video.setAttribute('playsinline', '');
				const source = document.createElement('source');
				source.src = src;
				video.appendChild(source);
				return video;
			} else {
				const img = document.createElement('img');
				img.className = 'slider-advanced__main-media';
				img.src = src;
				img.alt = '';
				return img;
			}
		}

		function setActive(index) {
			if (index < 0 || index >= thumbs.length) return;
			const existingVideo = main.querySelector('video');
			if (existingVideo && !existingVideo.paused) {
				try { existingVideo.pause(); } catch (e) {}
			}

			const btn = thumbs[index];
			const type = btn.getAttribute('data-type');
			const src = btn.getAttribute('data-src');
			const poster = btn.getAttribute('data-poster') || '';

			main.innerHTML = '';
			main.appendChild(createMediaElement(type, src, poster));

			thumbs.forEach(t => t.classList.remove('is-active'));
			btn.classList.add('is-active');
			currentIndex = index;

			updateNavState();
			scrollThumbIntoView(btn);
		}

		function scrollThumbIntoView(btn) {
			try {
				btn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
			} catch (_) {}
		}

		function updateNavState() {
			prevBtn.disabled = thumbs.length <= 1;
			nextBtn.disabled = thumbs.length <= 1;
		}

		thumbs.forEach((btn, i) => {
			btn.addEventListener('click', () => setActive(i));
		});

		prevBtn.addEventListener('click', () => {
			const nextIndex = (currentIndex - 1 + thumbs.length) % thumbs.length;
			setActive(nextIndex);
		});

		nextBtn.addEventListener('click', () => {
			const nextIndex = (currentIndex + 1) % thumbs.length;
			setActive(nextIndex);
		});

		root.addEventListener('keydown', (e) => {
			if (e.key === 'ArrowLeft') { e.preventDefault(); prevBtn.click(); }
			if (e.key === 'ArrowRight') { e.preventDefault(); nextBtn.click(); }
		});
		root.setAttribute('tabindex', '0');

		setActive(currentIndex);
	});
});


