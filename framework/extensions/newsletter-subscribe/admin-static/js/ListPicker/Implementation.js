import {
	createElement,
	useState,
	useEffect,
	Fragment
} from '@wordpress/element'
import Downshift from 'downshift'
import { __ } from 'ct-i18n'
import classnames from 'classnames'

let listsCache = null

const ListPickerImplementation = ({ value, onChange }) => {
	const [lists, setLists] = useState(listsCache || [])
	const [isLoadingLists, setListsLoading] = useState(!listsCache)

	const [error, setError] = useState('')

	useEffect(() => {
		let active = true

		const fetchLists = async () => {
			const body = new FormData()
			body.append(
				'action',
				'blocksy_ext_newsletter_subscribe_get_actual_lists'
			)
			body.append(
				'nonce',
				(
					window.ctDashboardLocalizations ||
					window.ct_localizations ||
					window.ct_customizer_localizations
				).dashboard_actions_nonce
			)

			let error = __(
				'Unable to fetch lists. Please try again.',
				'blocksy-companion'
			)

			try {
				const response = await fetch(ajaxurl, { method: 'POST', body })
				const result = await response.json()

				if (!active) {
					return
				}

				if (
					response.ok &&
					result.success &&
					Array.isArray(result.data?.result)
				) {
					setLists(result.data.result)
					listsCache = result.data.result
					return
				}

				if (
					typeof result.data?.message === 'string' &&
					result.data.message
				) {
					error = result.data.message
				}
			} catch (e) {
			} finally {
				if (active) {
					setListsLoading(false)
				}
			}

			if (active) {
				setLists([])
				listsCache = null
				setError(error)
			}
		}

		fetchLists()

		return () => {
			active = false
		}
	}, [])

	return lists.length === 0 ? (
		<Fragment>
			<div className="ct-select-input">
				<input
					disabled
					placeholder={
						isLoadingLists
							? __('Loading...', 'blocksy-companion')
							: error
								? __(
										'Unable to load lists',
										'blocksy-companion'
									)
								: __('No lists found', 'blocksy-companion')
					}
				/>
			</div>
			{error && <p role="alert">{error}</p>}
		</Fragment>
	) : (
		<Downshift
			selectedItem={value || lists[0].id}
			onChange={(selection) => onChange(selection)}
			itemToString={(item) =>
				item ? (lists.find(({ id }) => id === item) || {}).name : ''
			}
		>
			{({
				getInputProps,
				getItemProps,
				getLabelProps,
				getMenuProps,
				isOpen,
				inputValue,
				highlightedIndex,
				selectedItem,
				openMenu
			}) => (
				<div className="ct-select-input">
					<input
						{...getInputProps({
							onFocus: () => openMenu(),
							onClick: () => openMenu()
						})}
						placeholder={__('Select list...', 'blocksy-companion')}
						readOnly
					/>

					{isOpen && (
						<div
							{...getMenuProps({
								className: 'ct-select-dropdown'
							})}
						>
							{lists.map((item, index) => (
								<div
									{...getItemProps({
										key: item.id,
										index,
										item: item.id,
										className: classnames(
											'ct-select-dropdown-item',
											{
												active:
													highlightedIndex === index,
												selected:
													selectedItem === item.id
											}
										)
									})}
								>
									{item.name}
								</div>
							))}
						</div>
					)}
				</div>
			)}
		</Downshift>
	)
}

export default ListPickerImplementation
