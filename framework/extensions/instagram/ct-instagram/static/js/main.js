import './public-path'

console.log('here we are')

document.addEventListener('DOMContentLoaded', () => {
	;[...document.querySelectorAll('.ct-instagram-widget')].map(el =>
		import('./instagram-widget').then(({ initInstagramWidget }) => {
			initInstagramWidget(el)
		})
	)
})
