/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
export function createCourseAvailability( readRuntime ) {
	return function CourseAvailability() {
		const {
			I: Controls,
			React,
			Rz,
			T: StoreModule,
			b: I18n,
			xz,
			y: WordPressData,
		} = readRuntime();
		var t,
			n,
			r,
			a = ( 0, WordPressData.useDispatch )( StoreModule.default ),
			o = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getCourse();
			}, [] ),
			i = function ( e, _tLocal ) {
				a.setCourse(
					Rz(
						Rz( {}, o ),
						{},
						{
							duration: Rz(
								Rz( {}, o.duration ),
								{},
								xz( {}, _tLocal, e )
							),
						}
					)
				);
			};
		return (
			<React.Fragment>
				<Controls.FlexWP
					justify={ 'space-between' }
					align={ 'flex-start' }
				>
					<Controls.FlexItemWP
						style={ {
							flex: '5',
						} }
					>
						<Controls.HeadingWP level={ 4 }>
							{ ( 0, I18n.__ )( 'Course Duration', 'ohmylms' ) }
						</Controls.HeadingWP>
						<Controls.SpacerWP marginBottom={ 1 } />
						<Controls.TextWP>
							{ ( 0, I18n.__ )(
								'Define the Journey - Set the duration of how long the course is.',
								'ohmylms'
							) }
						</Controls.TextWP>
					</Controls.FlexItemWP>
					<Controls.FlexItemWP
						style={ {
							flex: '3',
						} }
					>
						<Controls.FlexWP gap={ 3 }>
							<Controls.FlexBlockWP>
								<Controls.InputNumberWP
									suffix={ 'hour' }
									min={ 0 }
									name={ 'hour' }
									value={
										null === ( t = o.duration ) ||
										void 0 === t
											? void 0
											: t.hour
									}
									onChange={ function ( e ) {
										if ( /^\d*\.?\d*$/.test( e ) ) {
											i( e, 'hour' );
										}
									} }
									onKeyDown={ function ( e ) {
										if (
											[
												'e',
												'E',
												'+',
												'-',
												'/',
												'\\',
												'.',
												',',
											].includes( e.key ) ||
											( /[a-zA-Z]/.test( e.key ) &&
												! [
													'Backspace',
													'Tab',
													'ArrowLeft',
													'ArrowRight',
													'Delete',
													'Enter',
												].includes( e.key ) )
										) {
											e.preventDefault();
										}
									} }
									onBlur={ function () {
										var e;
										if (
											( null === ( e = o.duration ) ||
											void 0 === e
												? void 0
												: e.hour ) < 0
										) {
											i( 0, 'hour' );
										}
									} }
								/>
							</Controls.FlexBlockWP>
							<Controls.FlexBlockWP>
								<Controls.InputNumberWP
									suffix={ 'min' }
									min={ 0 }
									name={ 'min' }
									value={
										null === ( n = o.duration ) ||
										void 0 === n
											? void 0
											: n.min
									}
									onChange={ function ( e ) {
										if ( /^\d*\.?\d*$/.test( e ) ) {
											i( e, 'min' );
										}
									} }
									onKeyDown={ function ( e ) {
										if (
											[
												'e',
												'E',
												'+',
												'-',
												'/',
												'\\',
												'.',
												',',
											].includes( e.key ) ||
											( /[a-zA-Z]/.test( e.key ) &&
												! [
													'Backspace',
													'Tab',
													'ArrowLeft',
													'ArrowRight',
													'Delete',
													'Enter',
												].includes( e.key ) )
										) {
											e.preventDefault();
										}
									} }
									onBlur={ function () {
										var e;
										if (
											( null === ( e = o.duration ) ||
											void 0 === e
												? void 0
												: e.min ) < 0
										) {
											i( 0, 'min' );
										}
									} }
								/>
							</Controls.FlexBlockWP>
							<Controls.FlexBlockWP>
								<Controls.InputNumberWP
									suffix={ 'sec' }
									min={ 0 }
									name={ 'sec' }
									value={
										null === ( r = o.duration ) ||
										void 0 === r
											? void 0
											: r.sec
									}
									onChange={ function ( e ) {
										if ( /^\d*\.?\d*$/.test( e ) ) {
											i( e, 'sec' );
										}
									} }
									onKeyDown={ function ( e ) {
										if (
											[
												'e',
												'E',
												'+',
												'-',
												'/',
												'\\',
												'.',
												',',
											].includes( e.key ) ||
											( /[a-zA-Z]/.test( e.key ) &&
												! [
													'Backspace',
													'Tab',
													'ArrowLeft',
													'ArrowRight',
													'Delete',
													'Enter',
												].includes( e.key ) )
										) {
											e.preventDefault();
										}
									} }
									onBlur={ function () {
										var e;
										if (
											( null === ( e = o.duration ) ||
											void 0 === e
												? void 0
												: e.sec ) < 0
										) {
											i( 0, 'sec' );
										}
									} }
								/>
							</Controls.FlexBlockWP>
						</Controls.FlexWP>
					</Controls.FlexItemWP>
				</Controls.FlexWP>
			</React.Fragment>
		);
	};
}
