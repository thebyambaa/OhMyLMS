import {
	createContext,
	useContext,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import type { ReactNode } from 'react';

/** What the builder bar needs to know about one step of the builder. */
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
	register: ( id: string, step: StepSave | null ) => void;
}

const Context = createContext< BuilderSaveContext | null >( null );

/**
 * Holds the save of every step that is mounted, so one Save button in the builder bar can save them all.
 * Steps stay mounted while the user moves between them, so a step the user has left keeps its edits and
 * its own save; the bar is unsaved while any step is, and Save runs each step that has changes.
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
	const [ steps, setSteps ] = useState< Record< string, StepSave > >( {} );
	const value = useMemo(
		() => ( {
			register: ( id: string, step: StepSave | null ) =>
				setSteps( ( current ) => {
					if ( step ) {
						return { ...current, [ id ]: step };
					}
					if ( ! ( id in current ) ) {
						return current;
					}
					const rest = { ...current };
					delete rest[ id ];
					return rest;
				} ),
		} ),
		[]
	);
	const step = useMemo< StepSave | null >( () => {
		const all = Object.values( steps );
		if ( ! all.length ) {
			return null;
		}
		return {
			save: () =>
				all
					.filter( ( one ) => one.dirty && ! one.busy )
					.forEach( ( one ) => one.save() ),
			dirty: all.some( ( one ) => one.dirty ),
			busy: all.some( ( one ) => one.busy ),
			course: all.find( ( one ) => one.course )?.course,
		};
	}, [ steps ] );
	return (
		<Context.Provider value={ value }>{ render( step ) }</Context.Provider>
	);
}

/**
 * A step registers its save here. Outside a builder (no provider) this does nothing.
 *
 * @param step The step's save, whether it is dirty and whether it is running.
 * @param id   Name of the step, so several steps can be registered at once.
 */
export function useBuilderSave( step: StepSave, id = 'step' ) {
	const context = useContext( Context );
	const latest = useRef( step.save );
	latest.current = step.save;
	useEffect( () => {
		context?.register( id, {
			save: () => latest.current(),
			dirty: step.dirty,
			busy: step.busy,
			course: step.course,
		} );
		return () => context?.register( id, null );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [
		context,
		id,
		step.dirty,
		step.busy,
		step.course?.status,
		step.course?.permalink,
		step.course?.preview_url,
	] );
}
