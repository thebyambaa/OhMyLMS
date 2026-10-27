/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createMigrationSettings( readRuntime ) {
	return function MigrationSettings() {
		const {
			I: Controls,
			React,
			X4: MemoMigrationPlatformSelector,
			_6,
			d6: MemoMigrationImport,
			k4: MemoMigrationResources,
		} = readRuntime();
		return (
			<React.Fragment>
				<Controls.CardWP
					isBorderless={ ! 0 }
					variant={ 'secondary' }
					className={ 'ohmylms-full-screen-height' }
				>
					<Controls.SpacerWP
						padding={ 4 }
						paddingTop={ 6 }
						marginTop={ 4 }
						marginBottom={ 0 }
					>
						{ _6.length > 0 ? (
							<React.Fragment>
								<MemoMigrationPlatformSelector />
							</React.Fragment>
						) : (
							<React.Fragment>
								<MemoMigrationImport />
							</React.Fragment>
						) }
						<Controls.SpacerWP
							marginBottom={ 0 }
							marginTop={ 18 }
							paddingBottom={ 28 }
						>
							<MemoMigrationResources />
						</Controls.SpacerWP>
					</Controls.SpacerWP>
				</Controls.CardWP>
			</React.Fragment>
		);
	};
}
