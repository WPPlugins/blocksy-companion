import { createElement, useEffect, useState } from '@wordpress/element'
import Downshift from 'downshift'
import { __ } from 'ct-i18n'
import classnames from 'classnames'

const ListPicker = ({
	listId,
	provider,
	apiKey,
	apiUrl,
	onChange,
	onListsLoading
}) => {
	const [lists, setLists] = useState([])
	const [isLoadingLists, setListsLoading] = useState(false)

	const [error, setError] = useState('')

	useEffect(() => {
		let active = true
		const controller =
			'AbortController' in window ? new AbortController() : null

		setLists([])
		setError('')
		setListsLoading(false)
		onListsLoading(0, '')

		if (!apiKey && !['mailpoet', 'fluentcrm'].includes(provider)) {
			return
		}

		const fetchLists = async () => {
			setListsLoading(true)

			const body = new FormData()
			body.append('api_key', apiKey)
			body.append('api_url', apiUrl || '')
			body.append('provider', provider)
			body.append(
				'action',
				'blocksy_ext_newsletter_subscribe_maybe_get_lists'
			)
			body.append(
				'nonce',
				ctDashboardLocalizations.dashboard_actions_nonce
			)

			let error = __(
				'Unable to fetch lists. Please try again.',
				'blocksy-companion'
			)

			try {
				const response = await fetch(
					ctDashboardLocalizations.ajax_url,
					{
						method: 'POST',
						signal: controller?.signal,
						body
					}
				)
				const result = await response.json()

				if (!active) {
					return
				}

				if (
					response.ok &&
					result.success &&
					Array.isArray(result.data?.result)
				) {
					const lists = result.data.result.map((list) => ({
						...list,
						id: list.id.toString()
					}))
					setLists(lists)
					onListsLoading(lists.length, '')
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
				setError(error)
				onListsLoading(0, error)
			}
		}

		fetchLists()

		return () => {
			active = false
			controller?.abort()
		}
	}, [provider, apiKey, apiUrl])

	return lists.length === 0 ? (
		<div className={classnames('ct-select-input', 'ct-no-results')}>
			<input
				disabled
				placeholder={
					isLoadingLists
						? __('Fetching...', 'blocksy-companion')
						: error
							? __('Unable to load lists', 'blocksy-companion')
							: apiKey ||
								  ['mailpoet', 'fluentcrm'].includes(provider)
								? __('No lists found', 'blocksy-companion')
								: ''
				}
			/>
		</div>
	) : (
		<Downshift
			selectedItem={lists.find(({ id }) => id === listId) ? listId : ''}
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

export default ListPicker
