import {
	createContext,
	useContext,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import type { ReactNode } from 'react';

/** What the builder bar needs to know about the step that is open. */
export interface StepSave {
	/** Runs the step's own save. */
	save: () => void;
	/** Whether the step has changes that are not saved. */
	dirty: boolean;
	/** Whether a save is running. */
	busy: boolean;
	/** The course the step has loaded, so the bar can link to it without another request. */
	course?: { status: string; permalink: string; preview_url: string };
}

interface BuilderSaveContext {
	register: ( step: StepSave | null ) => void;
}

const Context = createContext< BuilderSaveContext | null >( null );

/**
 * Holds the save of the step that is open, so one Save button in the builder bar can run it.
 *
 * @param props          Props.
 * @param props.children The builder: the bar reads `step`, the step components register themselves.
 * @param props.render   Renders the children with the registered step.
 */
export function BuilderSaveProvider( {
	render,
}: {
	children?: ReactNode;
	render: ( step: StepSave | null ) => ReactNode;
} ) {
	const [ step, setStep ] = useState< StepSave | null >( null );
	const value = useMemo( () => ( { register: setStep } ), [] );
	return (
		<Context.Provider value={ value }>{ render( step ) }</Context.Provider>
	);
}

/**
 * A step registers its save here. Outside a builder (no provider) this does nothing.
 *
 * @param step The step's save, whether it is dirty and whether it is running.
 */
export function useBuilderSave( step: StepSave ) {
	const context = useContext( Context );
	const latest = useRef( step.save );
	latest.current = step.save;
	useEffect( () => {
		context?.register( {
			save: () => latest.current(),
			dirty: step.dirty,
			busy: step.busy,
			course: step.course,
		} );
		return () => context?.register( null );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [
		context,
		step.dirty,
		step.busy,
		step.course?.status,
		step.course?.permalink,
		step.course?.preview_url,
	] );
}
