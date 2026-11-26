import { registerDynamicChunk } from 'blocksy-frontend'
import './main'

jest.mock('blocksy-frontend', () => ({ registerDynamicChunk: jest.fn() }), {
	virtual: true
})

const mount = registerDynamicChunk.mock.calls[0][1].mount
const originalFormData = global.FormData
const originalFetch = global.fetch

afterAll(() => {
	global.FormData = originalFormData
	global.fetch = originalFetch
	delete global.ct_localizations
	delete global.blocksy_newsletter_subscribe
})

const submit = async (response) => {
	const classes = new Set()
	const message = { innerHTML: '', textContent: '' }
	const form = {
		dataset: { provider: 'brevo:1' },
		classList: {
			add: (...names) => names.forEach((name) => classes.add(name)),
			remove: (...names) => names.forEach((name) => classes.delete(name))
		},
		querySelector: () => message
	}
	global.FormData = class {
		append() {}
	}
	global.ct_localizations = { ajax_url: '/admin-ajax.php' }
	global.blocksy_newsletter_subscribe = {
		errorMessage: 'Unable to subscribe. Please try again later.'
	}
	global.fetch = jest.fn(response)
	mount(form, { event: { target: form } })
	await new Promise(setImmediate)
	return { classes, message }
}

it.each([
	['network failure', () => Promise.reject(new Error('Connection lost'))],
	[
		'invalid JSON',
		async () => ({ json: () => Promise.reject(new Error('JSON')) })
	],
	['missing data', async () => ({ json: async () => ({ success: false }) })],
	[
		'missing message',
		async () => ({ json: async () => ({ data: { result: 'no' } }) })
	]
])('shows a visible fallback after %s', async (name, response) => {
	const { classes, message } = await submit(response)
	expect(classes.has('subscribe-error')).toBe(true)
	expect(classes.has('subscribe-loading')).toBe(false)
	expect(message.textContent).toBe(
		'Unable to subscribe. Please try again later.'
	)
})

it.each([
	['no', 'Unable to subscribe. Please try again later.', 'subscribe-error'],
	[
		'yes',
		'Thank you for subscribing to our newsletter!',
		'subscribe-success'
	],
	['yes', 'Please confirm your subscription.', 'subscribe-success']
])('preserves a valid %s response: %s', async (result, message, className) => {
	const state = await submit(async () => ({
		json: async () => ({ data: { result, message } })
	}))
	expect(state.classes.has(className)).toBe(true)
	expect(state.classes.has('subscribe-loading')).toBe(false)
	expect(state.message.innerHTML).toBe(message)
})
