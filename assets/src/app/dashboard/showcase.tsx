import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Alert } from '../../ui/Alert';
import { Button } from '../../ui/Button';
import { Checkbox } from '../../ui/Checkbox';
import { IconButton } from '../../ui/IconButton';
import { Input } from '../../ui/Input';
import { Select } from '../../ui/Select';
import { Textarea } from '../../ui/Textarea';

const LONG = __(
	'A deliberately long label that has to wrap on narrow screens without breaking the layout',
	'ohmylms'
);
const MONGOLIAN = 'Өгөгдлийн сан, үйлчилгээний нөхцөл, хэрэглэгчийн нэр';

/**
 * One titled group of examples.
 *
 * @param props          Props.
 * @param props.title    Heading.
 * @param props.note     Usage guidance shown under the heading.
 * @param props.children The examples.
 */
function Group( {
	title,
	note,
	children,
}: {
	title: string;
	note?: string;
	children: React.ReactNode;
} ) {
	return (
		<section className="oml-dash-card">
			<h2>{ title }</h2>
			{ note && <p className="oml-dash-note">{ note }</p> }
			{ children }
		</section>
	);
}

/**
 * Internal reference for the shared controls: every variant and state the dashboard may use.
 * A new variant is added here before screens adopt it.
 */
export function Showcase() {
	const [ busy, setBusy ] = useState( false );
	const [ text, setText ] = useState( '' );
	const row = { display: 'flex', flexWrap: 'wrap', gap: '0.6rem' } as const;

	return (
		<section>
			<h1 className="oml-dash-title">
				{ __( 'Design system', 'ohmylms' ) }
			</h1>
			<p className="oml-dash-note">
				{ __(
					'Shared controls from assets/src/ui, styled by oml-ui.css through the --oml-* tokens. Use these in feature screens; do not restyle raw buttons, inputs or selects.',
					'ohmylms'
				) }
			</p>

			<Group
				title={ __( 'Button', 'ohmylms' ) }
				note={ __(
					'One primary action per task area. Name the object: "Add lesson", not "Add".',
					'ohmylms'
				) }
			>
				<div style={ row }>
					<Button variant="primary">
						{ __( 'Primary', 'ohmylms' ) }
					</Button>
					<Button>{ __( 'Secondary', 'ohmylms' ) }</Button>
					<Button variant="tertiary">
						{ __( 'Tertiary', 'ohmylms' ) }
					</Button>
					<Button variant="danger">
						{ __( 'Delete lesson', 'ohmylms' ) }
					</Button>
				</div>
				<div style={ { ...row, marginTop: '0.6rem' } }>
					<Button size="sm">{ __( 'Small', 'ohmylms' ) }</Button>
					<Button>{ __( 'Medium', 'ohmylms' ) }</Button>
					<Button size="lg">{ __( 'Large', 'ohmylms' ) }</Button>
					<Button disabled>{ __( 'Disabled', 'ohmylms' ) }</Button>
					<Button
						variant="primary"
						loading={ busy }
						onClick={ () => {
							setBusy( true );
							window.setTimeout( () => setBusy( false ), 1500 );
						} }
					>
						{ __( 'Save changes', 'ohmylms' ) }
					</Button>
				</div>
				<div style={ { ...row, marginTop: '0.6rem' } }>
					<Button>{ LONG }</Button>
					<Button>{ MONGOLIAN }</Button>
				</div>
			</Group>

			<Group
				title={ __( 'Input and Field', 'ohmylms' ) }
				note={ __(
					'The Field supplies the label, hint and error and links them to the control.',
					'ohmylms'
				) }
			>
				<Input
					label={ __( 'Default', 'ohmylms' ) }
					value={ text }
					onChange={ ( event ) => setText( event.target.value ) }
				/>
				<Input
					label={ __( 'With hint', 'ohmylms' ) }
					help={ __( 'Shown to learners.', 'ohmylms' ) }
					required
				/>
				<Input
					label={ __( 'Invalid', 'ohmylms' ) }
					error={ __( 'Enter a name.', 'ohmylms' ) }
					defaultValue=""
				/>
				<Input
					label={ __( 'Disabled', 'ohmylms' ) }
					disabled
					defaultValue="—"
				/>
				<Input label={ LONG } />
				<Input
					label={ MONGOLIAN }
					lang="mn"
					defaultValue={ MONGOLIAN }
				/>
			</Group>

			<Group
				title={ __( 'Icon button and checkbox', 'ohmylms' ) }
				note={ __(
					'An icon button must have a label: it is the accessible name and the tooltip. Give reordering a button alternative to dragging.',
					'ohmylms'
				) }
			>
				<div style={ row }>
					<IconButton label={ __( 'Move up', 'ohmylms' ) }>
						↑
					</IconButton>
					<IconButton label={ __( 'Move down', 'ohmylms' ) }>
						↓
					</IconButton>
				</div>
				<Checkbox label={ __( 'Shuffle questions', 'ohmylms' ) } />
				<Checkbox
					label={ __( 'With a hint', 'ohmylms' ) }
					help={ __( 'Shown under the label.', 'ohmylms' ) }
					defaultChecked
				/>
				<Checkbox label={ __( 'Disabled', 'ohmylms' ) } disabled />
				<Checkbox label={ LONG } />
			</Group>

			<Group title={ __( 'Select', 'ohmylms' ) }>
				<Select
					label={ __( 'Default', 'ohmylms' ) }
					options={ [
						{ value: 'a', label: __( 'Option A', 'ohmylms' ) },
						{ value: 'b', label: __( 'Option B', 'ohmylms' ) },
					] }
				/>
				<Select
					label={ __( 'Invalid', 'ohmylms' ) }
					error={ __( 'Choose one.', 'ohmylms' ) }
					placeholder={ __( 'Choose…', 'ohmylms' ) }
					options={ [
						{ value: 'a', label: __( 'Option A', 'ohmylms' ) },
					] }
				/>
				<Select
					label={ __( 'Disabled', 'ohmylms' ) }
					disabled
					options={ [
						{ value: 'a', label: __( 'Option A', 'ohmylms' ) },
					] }
				/>
			</Group>

			<Group title={ __( 'Textarea', 'ohmylms' ) }>
				<Textarea label={ __( 'Default', 'ohmylms' ) } />
				<Textarea
					label={ __( 'Invalid', 'ohmylms' ) }
					error={ __( 'Paste at least one row.', 'ohmylms' ) }
				/>
			</Group>

			<Group
				title={ __( 'Alert', 'ohmylms' ) }
				note={ __(
					'Confirm a save with success; keep what the user typed after an error.',
					'ohmylms'
				) }
			>
				<Alert tone="success">{ __( 'Saved.', 'ohmylms' ) }</Alert>
				<Alert tone="danger">
					{ __(
						'Could not save. Your changes are kept.',
						'ohmylms'
					) }
				</Alert>
				<Alert tone="info">{ __( 'No skills yet.', 'ohmylms' ) }</Alert>
			</Group>
		</section>
	);
}
