export const markImagesAsLoaded = el =>
	[...el.querySelectorAll('.ct-image-container.ct-lazy')].map(el => {
		el
			.querySelector('img')
			.setAttribute('src', el.querySelector('img').dataset.original)

		if (el.querySelector('img').dataset.originalSet) {
			el
				.querySelector('img')
				.setAttribute(
					'srcset',
					el.querySelector('img').dataset.originalSet
				)
		}

		el.classList.remove('ct-lazy')
		el.classList.add('ct-lazy-loaded')
	})
