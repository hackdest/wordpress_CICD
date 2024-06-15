import { createElement, useState, useRef } from '@wordpress/element'
import { __ } from 'ct-i18n'

import { TextControl, ToolbarGroup, ToolbarButton } from '@wordpress/components'
import { dispatch, useSelect } from '@wordpress/data'

import {
	InspectorControls,
	useBlockProps,
	BlockControls,
	useInnerBlocksProps,
	store as blockEditorStore,
	__experimentalUseBorderProps as useBorderProps,
} from '@wordpress/block-editor'
import Preview from './Preview'

import { OptionsPanel } from 'blocksy-options'

import { PanelBody } from '@wordpress/components'

import { useUniqueId } from '../../hooks/use-unique-id'

import TermsPlaceholder from './edit/TermsPlaceholder'
import PatternSelectionModal from './edit/PatternSelectionModal'
import { useTaxonomies } from '../query/edit/utils/utils'
import TermsInspectorControls from './edit/TermsInspectorControls'

const Edit = ({
	clientId,

	className,

	attributes,
	setAttributes,

	context,
}) => {
	const innerBlocksProps = useInnerBlocksProps({}, {})

	const hasInnerBlocks = useSelect(
		(select) => !!select(blockEditorStore).getBlocks(clientId).length,
		[clientId]
	)

	const isOnboarding = !hasInnerBlocks && attributes.design !== 'default'

	const taxonomies = useTaxonomies()
	const taxonomiesSelectOptions = (taxonomies || []).reduce(
		(acc, taxonomy) => {
			acc[taxonomy.slug] = taxonomy.name

			return acc
		},
		{}
	)

	const { uniqueId, props: wrapperProps } = useUniqueId({
		attributes,
		setAttributes,
		clientId,
	})

	const { postId } = context

	const navRef = useRef()

	const borderProps = useBorderProps(attributes)

	const blockProps = useBlockProps({
		ref: navRef,
		className,
		style: {
			...borderProps.style,
		},
	})

	const [isPatternSelectionModalOpen, setIsPatternSelectionModalOpen] =
		useState(false)

	return (
		<>
			{isPatternSelectionModalOpen && (
				<PatternSelectionModal
					clientId={clientId}
					attributes={attributes}
					setIsPatternSelectionModalOpen={
						setIsPatternSelectionModalOpen
					}
					postType={attributes.taxonomy}
				/>
			)}

			{!isOnboarding ? (
				<div {...blockProps} {...wrapperProps}>
					{attributes.design === 'default' && (
						<Preview
							uniqueId={uniqueId}
							attributes={attributes}
							postId={postId}
						/>
					)}

					{hasInnerBlocks && <div {...innerBlocksProps} />}
				</div>
			) : (
				<div {...blockProps} {...wrapperProps}>
					<TermsPlaceholder
						setIsPatternSelectionModalOpen={
							setIsPatternSelectionModalOpen
						}
						attributes={attributes}
						setAttributes={setAttributes}
						clientId={clientId}
					/>
				</div>
			)}

			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						className="components-toolbar__control"
						icon="layout"
						label={__('Reset layout', 'blocksy')}
						disabled={isOnboarding}
						onClick={() => {
							if (hasInnerBlocks) {
								dispatch(
									'core/block-editor'
								).replaceInnerBlocks(clientId, [], false)
							} else {
								dispatch(
									'core/block-editor'
								).updateBlockAttributes(clientId, {
									design: '',
								})
							}
						}}
					/>
				</ToolbarGroup>
			</BlockControls>

			{!isOnboarding && (
				<>
					<InspectorControls>
						<PanelBody>
							<OptionsPanel
								purpose="gutenberg"
								onChange={(optionId, optionValue) => {
									setAttributes({
										[optionId]: optionValue,
									})
								}}
								options={{
									taxonomy: {
										type: 'ct-select',
										label: __('Taxonomy', 'blocksy'),
										value: '',
										defaultToFirstItem: false,
										choices: taxonomiesSelectOptions,
										purpose: 'default',
									},

									limit: {
										type: 'ct-number',
										label: __('Limit', 'blocksy'),
										value: '',
										min: 1,
										max: 100,
									},
								}}
								value={attributes}
								hasRevertButton={false}
							/>
						</PanelBody>
					</InspectorControls>

					<TermsInspectorControls
						attributes={attributes}
						setAttributes={setAttributes}
						context={context}
					/>
				</>
			)}

			<InspectorControls group="advanced">
				<TextControl
					__nextHasNoMarginBottom
					autoComplete="off"
					label={__('Block ID', 'blocksy')}
					value={uniqueId}
					onChange={(nextValue) => {}}
					onFocus={(e) => {
						e.target.select()
					}}
					help={__(
						'Please look at the documentation for more information on why this is useful.',
						'blocksy'
					)}
				/>
			</InspectorControls>
		</>
	)
}

export default Edit
