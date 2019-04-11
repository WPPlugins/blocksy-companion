import {
	createElement,
	Component,
	useEffect,
	useState
} from '@wordpress/element'
import { __ } from 'ct-i18n'
import classnames from 'classnames'

let exts_status_cache = null

const Extension = ({ extension, onExtsSync }) => {
	const [isLoading, setIsLoading] = useState(false)

	const makeAction = async (ext, actionName) => {
		const body = new FormData()
		body.append('ext', ext)
		body.append('action', actionName)

		setIsLoading(true)

		try {
			await fetch(ctDashboardLocalizations.ajax_url, {
				method: 'POST',
				body
			})

			onExtsSync()
		} catch (e) {}

		await new Promise(r => setTimeout(() => r(), 1000))

		setIsLoading(false)
	}

	return (
		<li>
			<h4 className="ct-extension-title">
				{extension.config.name}
			</h4>

			{extension.config.description && (
				<div className="ct-extension-description">
					{extension.config.description}
				</div>
			)}

			<div className="ct-extension-actions">
				<button
					className="ct-button"
					data-button="white"
					disabled={isLoading}
					onClick={() =>
						makeAction(
							extension.name,
							extension.__object
								? 'blocksy_extension_deactivate'
								: 'blocksy_extension_activate'
						)
					}>
					{isLoading
						? __('Loading')
						: extension.__object ? __('Deactivate') : __('Activate')}
				</button>
			</div>
		</li>
	)
}

const Extensions = () => {
	const [isLoading, setIsLoading] = useState(!exts_status_cache)
	const [exts_status, setExtsStatus] = useState(exts_status_cache || [])

	const syncExts = async (verbose = false) => {
		if (verbose) {
			setIsLoading(true)
		}

		const body = new FormData()
		body.append('action', 'blocksy_extensions_status')

		try {
			const response = await fetch(ctDashboardLocalizations.ajax_url, {
				method: 'POST',
				body
			})

			if (response.status === 200) {
				const { success, data } = await response.json()

				if (success) {
					setExtsStatus(data)
					exts_status_cache = data
				}
			}
		} catch (e) {}

		setIsLoading(false)
	}

	useEffect(() => {
		syncExts(!exts_status_cache)
	}, [])

	const exts = Object.values(exts_status).map((ext, index) => {
		ext['name'] = Object.keys(exts_status)[index]
		return ext
	})

	return (
		<div className="ct-extensions-list">
			{isLoading && (
				<p className="ct-loading-text">
					{__('Loading Extensions Status...')}
				</p>
			)}

			{!isLoading &&
				exts.length > 0 && (
					<ul>
						{exts.map(ext => (
							<Extension
								key={ext.name}
								extension={ext}
								onExtsSync={() => syncExts()}
							/>
						))}
					</ul>
				)}
		</div>
	)
}

export default Extensions
