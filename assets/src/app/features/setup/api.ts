import { get, post } from '../../lib/api';
import {
	parseCurrencies,
	parseState,
	parseStateResponse,
	parseStepResponse,
} from './model';
import type {
	CurrencyOption,
	SetupState,
	StepId,
	StepInput,
	StepResponse,
} from './types';

const BASE = '/ohmylms/v1/setup';

/**
 * Load the wizard state.
 *
 * @param signal Cancels the request.
 * @return Validated state.
 */
export async function getSetup( signal?: AbortSignal ): Promise< SetupState > {
	return parseState( await get< unknown >( BASE, { signal } ) );
}

/**
 * Save a step and mark it done.
 *
 * @param step   Step id.
 * @param input  Step input (empty object for steps without fields).
 * @param signal Cancels the request.
 * @return New state and the step result.
 */
export async function saveStep(
	step: StepId,
	input: StepInput,
	signal?: AbortSignal
): Promise< StepResponse > {
	return parseStepResponse(
		await post< unknown >( `${ BASE }/steps/${ step }`, input, { signal } )
	);
}

/**
 * Skip a step.
 *
 * @param step   Step id.
 * @param signal Cancels the request.
 * @return New state and an empty step result.
 */
export async function skipStep(
	step: StepId,
	signal?: AbortSignal
): Promise< StepResponse > {
	return parseStepResponse(
		await post< unknown >( `${ BASE }/steps/${ step }/skip`, undefined, {
			signal,
		} )
	);
}

/**
 * Complete the wizard.
 *
 * @param signal Cancels the request.
 * @return New state.
 */
export async function completeSetup(
	signal?: AbortSignal
): Promise< SetupState > {
	return parseStateResponse(
		await post< unknown >( `${ BASE }/complete`, undefined, { signal } )
	);
}

/**
 * Leave the wizard without completing it.
 *
 * @param step   The step the user was on.
 * @param signal Cancels the request.
 * @return New state.
 */
export async function dismissSetup(
	step: StepId | null,
	signal?: AbortSignal
): Promise< SetupState > {
	return parseStateResponse(
		await post< unknown >( `${ BASE }/dismiss`, step ? { step } : {}, {
			signal,
		} )
	);
}

/**
 * Start the wizard over (progress only).
 *
 * @param signal Cancels the request.
 * @return New state.
 */
export async function resetSetup(
	signal?: AbortSignal
): Promise< SetupState > {
	return parseStateResponse(
		await post< unknown >( `${ BASE }/reset`, undefined, { signal } )
	);
}

/**
 * List currencies for the preferences step (settings API, same capability).
 *
 * @param signal Cancels the request.
 * @return Currencies.
 */
export async function listCurrencies(
	signal?: AbortSignal
): Promise< CurrencyOption[] > {
	return parseCurrencies(
		await get< unknown >( '/ohmylms/v1/settings/currencies', { signal } )
	);
}
