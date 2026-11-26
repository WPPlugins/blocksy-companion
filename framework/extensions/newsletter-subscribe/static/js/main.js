import { registerDynamicChunk } from 'blocksy-frontend'

const submitAjax = (form) => {
	const body = new FormData(form)

	body.append('action', 'blc_newsletter_subscribe_process_ajax_subscribe')

	body.append('GROUP', form.dataset.provider.split(':')[1])

	form.classList.remove('subscribe-error', 'subscribe-success')
	form.classList.add('subscribe-loading')

	const showError = () => {
		form.classList.remove('subscribe-loading', 'subscribe-success')
		form.classList.add('subscribe-error')
		form.querySelector('.ct-newsletter-subscribe-message').textContent =
			blocksy_newsletter_subscribe.errorMessage
	}

	fetch(ct_localizations.ajax_url, {
		method: 'POST',
		body
	})
		.then((r) => r.json())
		.then(({ data }) => {
			form.classList.remove('subscribe-loading')

			if (data && data.form_url) {
				form.action = data.form_url
				form.dynamicJsChunkStop()
				form.requestSubmit()
				return
			}

			if (
				!data ||
				!data.message ||
				!['yes', 'no'].includes(data.result)
			) {
				showError()
				return
			}

			form.classList.add(
				data.result === 'no' ? 'subscribe-error' : 'subscribe-success'
			)

			form.querySelector('.ct-newsletter-subscribe-message').innerHTML =
				data.message
		})
		.catch(showError)
}

registerDynamicChunk('blocksy_ext_newsletter_subscribe', {
	mount: (el, { event }) => {
		const form = event.target

		submitAjax(form)
	}
})
