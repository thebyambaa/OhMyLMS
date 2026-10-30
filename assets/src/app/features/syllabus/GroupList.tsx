import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type {
	Fields,
	Group,
	SendCommand,
	Skill,
	SyllabusOutline,
} from './model';

export type EditSelection = {
	operation: 'add_group' | 'edit_group' | 'create_skill' | 'edit_skill';
	group?: Group;
	skill?: Skill;
	item?: number;
	initial: Fields;
};
export type MoveSelection = { group: Group; skill?: Skill };
const empty: Fields = { name: '', code: '', description: '' };
export function GroupList( {
	data,
	busy,
	send,
	edit,
	move,
	attach,
	detach,
	mapping,
}: {
	data: SyllabusOutline;
	busy: boolean;
	send: SendCommand;
	edit: ( value: EditSelection ) => void;
	move: ( value: MoveSelection ) => void;
	attach: ( group: Group ) => void;
	detach: ( group: Group, skill: Skill ) => void;
	mapping: ( skill: Skill ) => void;
} ) {
	return (
		<fieldset disabled={ busy }>
			<legend>{ __( 'Syllabus outline', 'ohmylms' ) }</legend>
			{ data.contents.map( ( content ) => (
				<section key={ content.id } aria-label={ content.name }>
					<h2>{ content.name }</h2>
					<Button
						onClick={ () =>
							edit( {
								operation: 'add_group',
								item: content.id,
								initial: empty,
							} )
						}
					>
						{ __( 'Add skill group', 'ohmylms' ) }
					</Button>
					{ content.groups.map( ( group, index ) => (
						<section key={ group.id } aria-label={ group.name }>
							<h3>
								{ group.code } { group.name }
							</h3>
							<p>{ group.description }</p>
							<Button
								onClick={ () =>
									edit( {
										operation: 'edit_group',
										group,
										initial: group,
									} )
								}
							>
								{ __( 'Edit group', 'ohmylms' ) }
							</Button>
							<Button
								disabled={ index === 0 }
								onClick={ () =>
									void send( 'move_group', {
										group_id: group.id,
										item_id: content.id,
										position: index - 1,
									} )
								}
							>
								{ __( 'Move group up', 'ohmylms' ) }
							</Button>
							<Button
								disabled={ index === content.groups.length - 1 }
								onClick={ () =>
									void send( 'move_group', {
										group_id: group.id,
										item_id: content.id,
										position: index + 1,
									} )
								}
							>
								{ __( 'Move group down', 'ohmylms' ) }
							</Button>
							<Button onClick={ () => move( { group } ) }>
								{ __( 'Move group…', 'ohmylms' ) }
							</Button>
							<ol>
								{ group.skills.map( ( skill, position ) => (
									<li key={ skill.term_id }>
										<strong>
											{ skill.code } { skill.name }
										</strong>
										<Button
											onClick={ () =>
												edit( {
													operation: 'edit_skill',
													group,
													skill,
													initial: skill,
												} )
											}
										>
											{ __( 'Edit skill', 'ohmylms' ) }
										</Button>
										<Button
											disabled={ position === 0 }
											onClick={ () =>
												void send( 'move_skill', {
													group_id: group.id,
													term_id: skill.term_id,
													destination: group.id,
													position: position - 1,
												} )
											}
										>
											{ __( 'Move skill up', 'ohmylms' ) }
										</Button>
										<Button
											disabled={
												position ===
												group.skills.length - 1
											}
											onClick={ () =>
												void send( 'move_skill', {
													group_id: group.id,
													term_id: skill.term_id,
													destination: group.id,
													position: position + 1,
												} )
											}
										>
											{ __(
												'Move skill down',
												'ohmylms'
											) }
										</Button>
										<Button
											onClick={ () =>
												move( { group, skill } )
											}
										>
											{ __( 'Move skill…', 'ohmylms' ) }
										</Button>
										<Button
											onClick={ () =>
												detach( group, skill )
											}
										>
											{ __( 'Detach skill', 'ohmylms' ) }
										</Button>
										<Button
											onClick={ () => mapping( skill ) }
										>
											{ __(
												'Shared-skill mappings',
												'ohmylms'
											) }
										</Button>
									</li>
								) ) }
							</ol>
							<Button
								onClick={ () =>
									edit( {
										operation: 'create_skill',
										group,
										initial: empty,
									} )
								}
							>
								{ __( 'Create skill', 'ohmylms' ) }
							</Button>
							<Button onClick={ () => attach( group ) }>
								{ __( 'Attach library skill', 'ohmylms' ) }
							</Button>
						</section>
					) ) }
				</section>
			) ) }
		</fieldset>
	);
}
