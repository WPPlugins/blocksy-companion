import './public-path'

document.addEventListener('DOMContentLoaded', () => {
	;[...document.querySelectorAll('.ct-instagram-widget')].map(el =>
		import('./instagram-widget').then(({ initInstagramWidget }) => {
			initInstagramWidget(el)
		})
	)

	window.ctEvents.on('blocksy:instagram:init', () => {
		;[...document.querySelectorAll('.ct-instagram-widget')].map(el =>
			import('./instagram-widget').then(({ initInstagramWidget }) => {
				initInstagramWidget(el)
			})
		)
	})
})
