import { createElement, memo, useState, RawHTML } from '@wordpress/element'
import { __ } from 'ct-i18n'

import { list, grid, desktop } from '@wordpress/icons'

import classnames from 'classnames'
import { useSelect } from '@wordpress/data'
import {
	Spinner,
	ToolbarGroup,
	PanelBody,
	RangeControl,
} from '@wordpress/components'

import {
	useInnerBlocksProps,
	BlockControls,
	BlockContextProvider,
	__experimentalUseBlockPreview as useBlockPreview,
	BlockVerticalAlignmentToolbar,
	useBlockProps,
	store as blockEditorStore,
	InspectorControls,
} from '@wordpress/block-editor'

import { useTaxBlockData } from '../tax-query/hooks/use-tax-block-data'

const TEMPLATE = []

function PostTemplateInnerBlocks() {
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'wp-block-post' },
		{ template: TEMPLATE, __unstableDisableLayoutClassNames: true }
	)

	return <div {...innerBlocksProps} />
}

function PostTemplateBlockPreview({
	blocks,
	blockContextId,
	isHidden,
	setActiveBlockContextId,
}) {
	const blockPreviewProps = useBlockPreview({
		blocks,
		props: {
			className: 'wp-block-post',
		},
	})

	const handleOnClick = () => {
		setActiveBlockContextId(blockContextId)
	}

	const style = {
		display: isHidden ? 'none' : undefined,
	}

	return (
		<div
			{...blockPreviewProps}
			tabIndex={0}
			// eslint-disable-next-line jsx-a11y/no-noninteractive-element-to-interactive-role
			role="button"
			onClick={handleOnClick}
			onKeyPress={handleOnClick}
			style={style}
		/>
	)
}

const MemoizedPostTemplateBlockPreview = memo(PostTemplateBlockPreview)

const Edit = ({
	clientId,

	attributes: { layout, verticalAlignment },
	attributes,

	setAttributes,

	context,
	__unstableLayoutClassNames,
}) => {
	const { postId } = context

	const [activeBlockContextId, setActiveBlockContextId] = useState()

	const { type: layoutType, columnCount = 3 } = layout || {}
	const isGridLayout = layoutType === 'grid'

	const blockProps = useBlockProps({
		className: classnames(__unstableLayoutClassNames, {
			'ct-query-template-grid': isGridLayout,
			'ct-query-template-list': !isGridLayout,
		}),
		style: isGridLayout
			? {
					'grid-template-columns': `repeat(var(--ct-grid-columns, ${columnCount}), minmax(0, 1fr))`,
					'--ct-grid-columns-tablet': `${attributes.tabletColumns}`,
					'--ct-grid-columns-mobile': `${attributes.mobileColumns}`,

					...(verticalAlignment
						? {
								'align-items':
									verticalAlignment === 'top'
										? 'flex-start'
										: verticalAlignment === 'bottom'
										? 'flex-end'
										: 'center',
						  }
						: {}),
			  }
			: {},
	})

	const { blockData } = useTaxBlockData({
		attributes: context,
		previewedPostId: postId,
	})

	const { blocks } = useSelect(
		(select) => {
			const { getBlocks } = select(blockEditorStore)

			return {
				blocks: getBlocks(clientId),
			}
		},
		[clientId]
	)

	if (!blockData) {
		return (
			<p {...blockProps}>
				<Spinner />
			</p>
		)
	}

	let blockContexts = blockData.all_terms.map((term) => ({
		termId: term.term_id,
		termIcon: term?.icon,
		termImage: term?.image,
	}))

	const setDisplayLayout = (newDisplayLayout) =>
		setAttributes({
			layout: { ...layout, ...newDisplayLayout },
		})

	const displayLayoutControls = [
		{
			icon: list,
			title: __('List view'),
			onClick: () => setDisplayLayout({ type: 'default' }),
			isActive: layoutType === 'default' || layoutType === 'constrained',
		},
		{
			icon: grid,
			title: __('Grid view'),
			onClick: () =>
				setDisplayLayout({
					type: 'grid',
					columnCount,
				}),
			isActive: isGridLayout,
		},
	]

	return (
		<>
			<BlockControls>
				<ToolbarGroup controls={displayLayoutControls} />
				{isGridLayout ? (
					<BlockVerticalAlignmentToolbar
						onChange={(newVerticalAlignment) => {
							setAttributes({
								verticalAlignment: newVerticalAlignment,
							})
						}}
						value={verticalAlignment}
					/>
				) : null}
			</BlockControls>

			<InspectorControls>
				{isGridLayout ? (
					<>
						<PanelBody>
							<RangeControl
								__nextHasNoMarginBottom
								label={__(
									'Tablet Columns',
									'blocksy-companion'
								)}
								onChange={(columns) =>
									setAttributes({
										tabletColumns: columns,
									})
								}
								min={1}
								max={6}
								initialPosition={attributes?.tabletColumns}
								value={attributes?.tabletColumns}
							/>
						</PanelBody>
						<PanelBody>
							<RangeControl
								__nextHasNoMarginBottom
								label={__(
									'Mobile Columns',
									'blocksy-companion'
								)}
								onChange={(columns) =>
									setAttributes({
										mobileColumns: columns,
									})
								}
								min={1}
								max={6}
								initialPosition={attributes?.mobileColumns}
								value={attributes?.mobileColumns}
							/>
						</PanelBody>
					</>
				) : null}
			</InspectorControls>

			<div {...blockProps}>
				{blockContexts.map((blockContext) => (
					<BlockContextProvider
						key={blockContext.termId}
						value={blockContext}>
						{blockContext.termId ===
						(activeBlockContextId || blockContexts[0]?.termId) ? (
							<PostTemplateInnerBlocks />
						) : null}

						<MemoizedPostTemplateBlockPreview
							blocks={blocks}
							blockContextId={blockContext.termId}
							setActiveBlockContextId={setActiveBlockContextId}
							isHidden={
								blockContext.termId ===
								(activeBlockContextId ||
									blockContexts[0]?.termId)
							}
						/>
					</BlockContextProvider>
				))}
			</div>
		</>
	)
}

export default Edit
