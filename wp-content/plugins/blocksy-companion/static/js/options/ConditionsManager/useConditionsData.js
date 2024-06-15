import { useContext, useState, useEffect } from '@wordpress/element'
import { ConditionsDataContext } from '../ConditionsManager'

const useConditionsData = (condition = null) => {
	const {
		isAdvancedMode,
		remoteConditions,
		allTaxonomies,
		allLanguages,
		allUsers,
	} = useContext(ConditionsDataContext)

	let rulesToUse = remoteConditions

	const allRules = rulesToUse
		.reduce(
			(current, { rules, title }) => [
				...current,
				...rules.map((r) => ({
					...r,
					group: title,
				})),
			],
			[]
		)
		.reduce(
			(current, { title, id, sub_ids = [], ...rest }) => [
				...current,
				{
					key:
						condition &&
						sub_ids.length > 0 &&
						sub_ids.find((i) => i.id === condition.rule)
							? condition.rule
							: id,
					value: title,
					sub_ids,
					...rest,
				},
			],
			[]
		)

	return {
		isAdvancedMode,
		allRules,
		rulesToUse,
		allTaxonomies,
		allLanguages,
		allUsers,
	}
}

export default useConditionsData
