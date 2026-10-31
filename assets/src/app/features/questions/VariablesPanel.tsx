import {
	Button,
	Notice,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { normalizeApiError, post } from '../../lib/api';

type Variable = {
	name: string;
	type: 'int' | 'decimal' | 'choice' | 'expr';
	min?: number;
	max?: number;
	step?: number;
	values?: Array< string | number >;
	expr?: string;
};
type Rule = { path: string; expr: string };
export type Template = {
	variables: Variable[];
	constraints: string[];
	set: Rule[];
};
type Sample = {
	ok: boolean;
	params: Record< string, unknown >;
	title: string;
	body: string;
	expected: string;
	errors?: string[];
};
type PreviewResult = { valid: boolean; message: string; samples?: Sample[] };

const NAME = /^[a-z][a-z0-9_]*$/i;
const plain = ( html: string ) =>
	String( html || '' )
		.replace( /<[^>]+>/g, ' ' )
		.replace( /\s+/g, ' ' )
		.trim();

/** Suggested `set` paths per type: where a computed value usually belongs. */
const PATHS: Record< string, string[] > = {
	numerical: [ 'answer', 'tolerance' ],
	expression: [ 'answer' ],
	'number-line': [ 'answer', 'min', 'max' ],
	'fill-level': [ 'answer' ],
	'count-blocks': [ 'answer' ],
	'make-amount': [ 'answer' ],
	'set-clock': [ 'answer' ],
	'shade-model': [ 'answer' ],
	'grid-build': [ 'answer' ],
	graphing: [ 'answer', 'min', 'max' ],
};

/** Checks the browser can do before asking the server (names, ranges, duplicates). */
export function templateProblems( t: Template ): string[] {
	const out: string[] = [];
	const seen = new Set< string >();
	t.variables.forEach( ( v, i ) => {
		const label = v.name || `#${ i + 1 }`;
		if ( ! NAME.test( v.name ) ) {
			out.push(
				sprintf(
					/* translators: %s: variable. */
					__( '%s: use a letter followed by letters or digits.', 'ohmylms' ),
					label
				)
			);
		}
		if ( seen.has( v.name ) ) {
			out.push(
				sprintf(
					/* translators: %s: variable. */
					__( '%s is defined twice.', 'ohmylms' ),
					label
				)
			);
		}
		seen.add( v.name );
		if (
			( 'int' === v.type || 'decimal' === v.type ) &&
			( v.min === undefined || v.max === undefined || v.min > v.max )
		) {
			out.push(
				sprintf(
					/* translators: %s: variable. */
					__( '%s: set a minimum no larger than the maximum.', 'ohmylms' ),
					label
				)
			);
		}
		if ( 'choice' === v.type && ! v.values?.length ) {
			out.push(
				sprintf(
					/* translators: %s: variable. */
					__( '%s: list at least one value.', 'ohmylms' ),
					label
				)
			);
		}
		if ( 'expr' === v.type && ! v.expr?.trim() ) {
			out.push(
				sprintf(
					/* translators: %s: variable. */
					__( '%s: enter a formula.', 'ohmylms' ),
					label
				)
			);
		}
	} );
	t.set.forEach( ( r ) => {
		if ( ! r.path.trim() || ! r.expr.trim() ) {
			out.push( __( 'Each computed field needs a field and a formula.', 'ohmylms' ) );
		}
	} );
	return out;
}

/**
 * Formula check with the Compute Engine (loaded on demand): parses each formula and reports
 * unknown symbols that are not template variables.
 * @param t
 */
async function computeEngineCheck( t: Template ): Promise< string[] > {
	const { ComputeEngine } = await import( '@cortex-js/compute-engine' );
	const ce = new ComputeEngine();
	const names = new Set( t.variables.map( ( v ) => v.name ) );
	const allowed = new Set( [ 'gcd', 'lcm', 'mod', 'round', 'floor', 'ceil', 'abs', 'sqrt', 'min', 'max', 'pi', 'e' ] );
	const formulas: Array< [ string, string ] > = [
		...t.variables
			.filter( ( v ) => 'expr' === v.type )
			.map( ( v ): [ string, string ] => [ v.name, v.expr || "" ] ),
		...t.set.map( ( r ): [ string, string ] => [ r.path, r.expr ] ),
		...t.constraints.map( ( c ): [ string, string ] => [ c, c.replace( /!=|==|>=|<=/g, '=' ) ] ),
	];
	const out: string[] = [];
	for ( const [ where, src ] of formulas ) {
		const words = String( src ).match( /[A-Za-z_][A-Za-z0-9_]*/g ) || [];
		const unknown = words.filter(
			( w ) => ! names.has( w ) && ! allowed.has( w.toLowerCase() )
		);
		if ( unknown.length ) {
			out.push(
				sprintf(
					/* translators: 1: field, 2: names. */
					__( '%1$s uses unknown name(s): %2$s', 'ohmylms' ),
					where,
					[ ...new Set( unknown ) ].join( ', ' )
				)
			);
			continue;
		}
		const expr = ce.parse(
			String( src ).replace( /\*/g, '\\times ' ).replace( /([A-Za-z]+)\(/g, '\\operatorname{$1}(' )
		);
		if ( ! expr.isValid ) {
			out.push(
				sprintf(
					/* translators: %s: field. */
					__( '%s is not a valid formula.', 'ohmylms' ),
					where
				)
			);
		}
	}
	return out;
}

const blank = (): Template => ( {
	variables: [
		{ name: 'a', type: 'int', min: 2, max: 9 },
		{ name: 'b', type: 'int', min: 2, max: 9 },
	],
	constraints: [],
	set: [],
} );

const num = ( text: string ) => ( '' === text ? undefined : Number( text ) );

/**
 * Randomized variables for any question type. Values replace {{expr}} in every text field; computed
 * fields (set rules) overwrite answer settings. The server draws and grades — the learner never
 * sees the answer or the seed.
 * @param root0
 * @param root0.type
 * @param root0.name
 * @param root0.description
 * @param root0.settings
 * @param root0.disabled
 * @param root0.onChange
 */
export function VariablesPanel( {
	type,
	name,
	description,
	settings,
	disabled,
	onChange,
}: {
	type: string;
	name: string;
	description: string;
	settings: Record< string, unknown >;
	disabled?: boolean;
	onChange: ( patch: Record< string, unknown > ) => void;
} ) {
	const template = settings.template as Template | undefined;
	const [ result, setResult ] = useState< PreviewResult | null >( null );
	const [ ceIssues, setCeIssues ] = useState< string[] | null >( null );
	const [ busy, setBusy ] = useState( false );

	if ( ! template ) {
		return (
			<div className="oml-vars oml-vars--empty">
				<p className="oml-editor-hint">
					{ __(
						'Give every learner and attempt different numbers or words. Write {{a}} or {{2*a}} anywhere in the question, options or answers.',
						'ohmylms'
					) }
				</p>
				<Button
					variant="secondary"
					disabled={ disabled }
					onClick={ () => onChange( { template: blank() } ) }
				>
					{ __( 'Add variables', 'ohmylms' ) }
				</Button>
			</div>
		);
	}

	const t: Template = {
		variables: template.variables || [],
		constraints: template.constraints || [],
		set: template.set || [],
	};
	const save = ( next: Partial< Template > ) => {
		setResult( null );
		setCeIssues( null );
		onChange( { template: { ...t, ...next } } );
	};
	const setVar = ( i: number, patch: Partial< Variable > ) =>
		save( {
			variables: t.variables.map( ( v, j ) =>
				i === j ? { ...v, ...patch } : v
			),
		} );
	const setRule = ( i: number, patch: Partial< Rule > ) =>
		save( {
			set: t.set.map( ( r, j ) => ( i === j ? { ...r, ...patch } : r ) ),
		} );
	const problems = templateProblems( t );
	const unused = t.variables
		.filter(
			( v ) =>
				v.name &&
				! new RegExp( `\\{\\{[^}]*\\b${ v.name }\\b` ).test(
					JSON.stringify( [ name, description, settings ] )
				)
		)
		.map( ( v ) => v.name );

	const preview = async () => {
		setBusy( true );
		try {
			setCeIssues( await computeEngineCheck( t ) );
			setResult(
				await post< PreviewResult >(
					'/ohmylms/v1/question-template/preview',
					{
						name,
						description,
						settings: { ...settings, type },
						count: 5,
					}
				)
			);
		} catch ( error ) {
			setResult( {
				valid: false,
				message: normalizeApiError( error ).message,
			} );
		} finally {
			setBusy( false );
		}
	};
	const nextName =
		'abcdefghjkmnpqrstuvwxyz'
			.split( '' )
			.find( ( c ) => ! t.variables.some( ( v ) => v.name === c ) ) || '';

	return (
		<div className="oml-vars">
			<table className="oml-vars__table widefat striped">
				<thead>
					<tr>
						<th>{ __( 'Name', 'ohmylms' ) }</th>
						<th>{ __( 'Kind', 'ohmylms' ) }</th>
						<th>{ __( 'Values', 'ohmylms' ) }</th>
						<th>
							<span className="screen-reader-text">
								{ __( 'Actions', 'ohmylms' ) }
							</span>
						</th>
					</tr>
				</thead>
				<tbody>
					{ t.variables.map( ( v, i ) => (
						<tr key={ i }>
							<td>
								<TextControl
									__nextHasNoMarginBottom
									hideLabelFromVision
									label={ __( 'Name', 'ohmylms' ) }
									value={ v.name }
									disabled={ disabled }
									onChange={ ( next ) =>
										setVar( i, { name: next.trim() } )
									}
								/>
							</td>
							<td>
								<SelectControl
									__nextHasNoMarginBottom
									hideLabelFromVision
									label={ __( 'Kind', 'ohmylms' ) }
									value={ v.type }
									disabled={ disabled }
									options={ [
										{ value: 'int', label: __( 'Whole number', 'ohmylms' ) },
										{ value: 'decimal', label: __( 'Decimal', 'ohmylms' ) },
										{ value: 'choice', label: __( 'Pick from list', 'ohmylms' ) },
										{ value: 'expr', label: __( 'Calculated', 'ohmylms' ) },
									] }
									onChange={ ( next ) =>
										setVar( i, { type: next as Variable[ 'type' ] } )
									}
								/>
							</td>
							<td className="oml-vars__values">
								{ ( 'int' === v.type || 'decimal' === v.type ) && (
									<>
										<TextControl
											__nextHasNoMarginBottom
											label={ __( 'Min', 'ohmylms' ) }
											type="number"
											value={ v.min ?? '' }
											disabled={ disabled }
											onChange={ ( x ) => setVar( i, { min: num( x ) } ) }
										/>
										<TextControl
											__nextHasNoMarginBottom
											label={ __( 'Max', 'ohmylms' ) }
											type="number"
											value={ v.max ?? '' }
											disabled={ disabled }
											onChange={ ( x ) => setVar( i, { max: num( x ) } ) }
										/>
										<TextControl
											__nextHasNoMarginBottom
											label={ __( 'Step', 'ohmylms' ) }
											type="number"
											value={ v.step ?? '' }
											disabled={ disabled }
											onChange={ ( x ) => setVar( i, { step: num( x ) } ) }
										/>
									</>
								) }
								{ 'choice' === v.type && (
									<TextControl
										__nextHasNoMarginBottom
										label={ __( 'Values, comma separated', 'ohmylms' ) }
										value={ ( v.values || [] ).join( ', ' ) }
										disabled={ disabled }
										onChange={ ( x ) =>
											setVar( i, {
												values: x
													.split( ',' )
													.map( ( s ) => s.trim() )
													.filter( Boolean ),
											} )
										}
									/>
								) }
								{ 'expr' === v.type && (
									<TextControl
										__nextHasNoMarginBottom
										label={ __( 'Formula', 'ohmylms' ) }
										value={ v.expr || '' }
										placeholder="a*b"
										disabled={ disabled }
										onChange={ ( x ) => setVar( i, { expr: x } ) }
									/>
								) }
							</td>
							<td>
								<Button
									icon="trash"
									isDestructive
									size="small"
									label={ sprintf(
										/* translators: %s: variable. */
										__( 'Remove %s', 'ohmylms' ),
										v.name
									) }
									disabled={ disabled }
									onClick={ () =>
										save( {
											variables: t.variables.filter(
												( _, j ) => j !== i
											),
										} )
									}
								/>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
			<p className="oml-vars__row-actions">
				<Button
					variant="secondary"
					size="small"
					disabled={ disabled || ! nextName }
					onClick={ () =>
						save( {
							variables: [
								...t.variables,
								{ name: nextName, type: 'int', min: 1, max: 10 },
							],
						} )
					}
				>
					{ __( 'Add variable', 'ohmylms' ) }
				</Button>
			</p>
			{ unused.length > 0 && (
				<p className="oml-editor-hint">
					{ sprintf(
						/* translators: %s: names. */
						__( 'Not used yet: %s. Type {{name}} in the text or answers.', 'ohmylms' ),
						unused.join( ', ' )
					) }
				</p>
			) }
			<TextareaControl
				label={ __( 'Conditions (one per line)', 'ohmylms' ) }
				help={ __( 'Every draw must satisfy all, e.g. a!=b, gcd(a,b)=1, mod(a,b)=0.', 'ohmylms' ) }
				rows={ 2 }
				value={ t.constraints.join( '\n' ) }
				disabled={ disabled }
				onChange={ ( x ) =>
					save( {
						constraints: x.split( '\n' ).map( ( s ) => s.trim() ).filter( Boolean ),
					} )
				}
			/>
			<fieldset className="oml-vars__set">
				<legend>{ __( 'Computed answer fields', 'ohmylms' ) }</legend>
				{ t.set.map( ( r, i ) => (
					<div className="oml-vars__rule" key={ i }>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Field', 'ohmylms' ) }
							value={ r.path }
							list={ `oml-vars-paths-${ type }` }
							disabled={ disabled }
							onChange={ ( x ) => setRule( i, { path: x } ) }
						/>
						<TextControl
							__nextHasNoMarginBottom
							label={ __( '= Formula', 'ohmylms' ) }
							value={ r.expr }
							disabled={ disabled }
							onChange={ ( x ) => setRule( i, { expr: x } ) }
						/>
						<Button
							icon="trash"
							size="small"
							isDestructive
							label={ __( 'Remove rule', 'ohmylms' ) }
							disabled={ disabled }
							onClick={ () => save( { set: t.set.filter( ( _, j ) => j !== i ) } ) }
						/>
					</div>
				) ) }
				<datalist id={ `oml-vars-paths-${ type }` }>
					{ ( PATHS[ type ] || [ 'answer' ] ).map( ( p ) => (
						<option key={ p } value={ p } />
					) ) }
				</datalist>
				<Button
					variant="tertiary"
					size="small"
					disabled={ disabled }
					onClick={ () =>
						save( {
							set: [ ...t.set, { path: PATHS[ type ]?.[ 0 ] || 'answer', expr: '' } ],
						} )
					}
				>
					{ __( 'Add computed field', 'ohmylms' ) }
				</Button>
			</fieldset>
			{ problems.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					<ul>
						{ problems.map( ( p ) => (
							<li key={ p }>{ p }</li>
						) ) }
					</ul>
				</Notice>
			) }
			<div className="oml-vars__actions">
				<Button
					variant="secondary"
					isBusy={ busy }
					disabled={ busy || problems.length > 0 }
					onClick={ preview }
				>
					{ __( 'Check and preview 5 draws', 'ohmylms' ) }
				</Button>
				<Button
					variant="tertiary"
					isDestructive
					disabled={ disabled }
					onClick={ () => onChange( { template: undefined } ) }
				>
					{ __( 'Remove variables', 'ohmylms' ) }
				</Button>
			</div>
			{ ceIssues && (
				<Notice status={ ceIssues.length ? 'warning' : 'success' } isDismissible={ false }>
					{ ceIssues.length
						? ceIssues.join( ' · ' )
						: __( 'Compute Engine: all formulas parse.', 'ohmylms' ) }
				</Notice>
			) }
			{ result && ! result.valid && (
				<Notice status="error" isDismissible={ false }>
					{ result.message }
				</Notice>
			) }
			{ result?.samples && result.samples.length > 0 && (
				<table className="oml-vars__samples widefat striped">
					<thead>
						<tr>
							<th>{ __( 'Values', 'ohmylms' ) }</th>
							<th>{ __( 'Learner sees', 'ohmylms' ) }</th>
							<th>{ __( 'Correct answer', 'ohmylms' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ result.samples.map( ( s, i ) => (
							<tr key={ i } className={ s.ok ? '' : 'is-error' }>
								<td>
									<code>
										{ Object.entries( s.params || {} )
											.map( ( [ k, v ] ) => `${ k }=${ String( v ) }` )
											.join( ' ' ) }
									</code>
								</td>
								<td>{ plain( s.body ) || s.title }</td>
								<td>
									{ s.ok ? s.expected : ( s.errors || [] ).join( '; ' ) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
}
