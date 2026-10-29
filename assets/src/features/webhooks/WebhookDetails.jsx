/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createWebhookDetails( readRuntime ) {
	return function WebhookDetails( props ) {
		const {
			Ge,
			Nm,
			Pf,
			React,
			T: StoreModule,
			b: I18n,
			j5,
			k5,
			y: WordPressData,
		} = readRuntime();
		var t = props.errors,
			n = ( props.setErrors, props.validate ),
			r = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).selectWebhookFormData();
			}, [] ),
			a = ( 0, WordPressData.useDispatch )(
				StoreModule.default
			).updateWebhookFormData,
			o = function ( e, _tLocal ) {
				a( e, _tLocal );
				n( k5( k5( {}, r ), {}, j5( {}, e, _tLocal ) ) );
			},
			i = [
				{
					label: ( 0, I18n.__ )( 'Course Purchase', 'ohmylms' ),
					value: 'course_purchase',
				},
				{
					label: ( 0, I18n.__ )( 'Course Enrollment', 'ohmylms' ),
					value: 'course_enrollment',
				},
				{
					label: ( 0, I18n.__ )( 'Course Completion', 'ohmylms' ),
					value: 'course_completion',
				},
				{
					label: ( 0, I18n.__ )( 'Lesson Completion', 'ohmylms' ),
					value: 'lesson_completion',
				},
				{
					label: ( 0, I18n.__ )( 'Quiz Submission', 'ohmylms' ),
					value: 'quiz_submission',
				},
				{
					label: ( 0, I18n.__ )( 'Quiz Achievement', 'ohmylms' ),
					value: 'quiz_achievement',
				},
				{
					label: ( 0, I18n.__ )( 'Assignment Submission', 'ohmylms' ),
					value: 'assignment_submission',
				},
				{
					label: ( 0, I18n.__ )(
						'Assignment Achievement',
						'ohmylms'
					),
					value: 'assignment_achievement',
				},
			],
			l = [
				{
					label: ( 0, I18n.__ )( 'Active', 'ohmylms' ),
					value: 'active',
				},
				{
					label: ( 0, I18n.__ )( 'Inactive', 'ohmylms' ),
					value: 'inactive',
				},
			];
		return (
			<React.Fragment>
				<Pf
					title={ ( 0, I18n.__ )( 'Webhook Name', 'ohmylms' ) }
					description={ ( 0, I18n.__ )(
						'Enter a descriptive name for this webhook',
						'ohmylms'
					) }
					tooltip={ ( 0, I18n.__ )(
						'This name helps you identify the webhook.',
						'ohmylms'
					) }
					value={ Ge(
						( r === null || r === void 0 ? void 0 : r.name ) || ''
					) }
					onChange={ function ( e ) {
						return o( 'name', e );
					} }
					error={ t === null || t === void 0 ? void 0 : t.name }
					placeholder={ ( 0, I18n.__ )(
						'e.g. Zapier Course Purchase Hook',
						'ohmylms'
					) }
				/>
				<Controls.DividerWP marginStart={ '2' } marginEnd={ '2' } />
				<Pf
					title={ ( 0, I18n.__ )( 'Webhook URL', 'ohmylms' ) }
					description={ ( 0, I18n.__ )(
						'The endpoint URL where the webhook data will be sent',
						'ohmylms'
					) }
					tooltip={ ( 0, I18n.__ )(
						'This is the destination URL for the webhook.',
						'ohmylms'
					) }
					value={
						( r === null || r === void 0
							? void 0
							: r.webhook_url ) || ''
					}
					onChange={ function ( e ) {
						return o( 'webhook_url', e );
					} }
					error={
						t === null || t === void 0 ? void 0 : t.webhook_url
					}
					placeholder={ ( 0, I18n.__ )(
						'https://hooks.zapier.com/hooks/catch/11896719/brzfge4',
						'ohmylms'
					) }
				/>
				<Controls.DividerWP marginStart={ '2' } marginEnd={ '2' } />
				<Nm
					title={ ( 0, I18n.__ )( 'Trigger Event', 'ohmylms' ) }
					description={ ( 0, I18n.__ )(
						'Select when this webhook should be triggered',
						'ohmylms'
					) }
					tooltip={ ( 0, I18n.__ )(
						'The webhook will fire when this event occurs.',
						'ohmylms'
					) }
					placeholder={ ( 0, I18n.__ )(
						'Select trigger event',
						'ohmylms'
					) }
					data={ i }
					notFoundMessage={ ( 0, I18n.__ )(
						'Nothing Found',
						'ohmylms'
					) }
					isMultiple={ ! 1 }
					onChange={ function ( e ) {
						return o( 'trigger_event', e );
					} }
					value={
						r === null || r === void 0 ? void 0 : r.trigger_event
					}
					staticSearch={ ! 0 }
					error={
						t === null || t === void 0 ? void 0 : t.trigger_event
					}
				/>
				<Controls.DividerWP marginStart={ '2' } marginEnd={ '2' } />
				<Nm
					title={ ( 0, I18n.__ )( 'HTTP Method', 'ohmylms' ) }
					description={ ( 0, I18n.__ )(
						'The HTTP method to use for the request',
						'ohmylms'
					) }
					placeholder={ ( 0, I18n.__ )(
						'Select HTTP method',
						'ohmylms'
					) }
					data={ [
						{
							label: 'GET',
							value: 'GET',
						},
						{
							label: 'POST',
							value: 'POST',
						},
						{
							label: 'PUT',
							value: 'PUT',
						},
						{
							label: 'DELETE',
							value: 'DELETE',
						},
						{
							label: 'PATCH',
							value: 'PATCH',
						},
					] }
					notFoundMessage={ ( 0, I18n.__ )(
						'Nothing Found',
						'ohmylms'
					) }
					isMultiple={ ! 1 }
					onChange={ function ( e ) {
						return o( 'http_method', e );
					} }
					value={
						r === null || r === void 0 ? void 0 : r.http_method
					}
					staticSearch={ ! 0 }
				/>
				<Controls.DividerWP marginStart={ '2' } marginEnd={ '2' } />
				<Nm
					title={ ( 0, I18n.__ )( 'Data Format', 'ohmylms' ) }
					description={ ( 0, I18n.__ )(
						'The format of the data to be sent',
						'ohmylms'
					) }
					placeholder={ ( 0, I18n.__ )(
						'Select data format',
						'ohmylms'
					) }
					data={ [
						{
							label: 'JSON',
							value: 'json',
						},
						{
							label: 'Form Data',
							value: 'form',
						},
						{
							label: 'XML',
							value: 'xml',
						},
					] }
					notFoundMessage={ ( 0, I18n.__ )(
						'Nothing Found',
						'ohmylms'
					) }
					isMultiple={ ! 1 }
					onChange={ function ( e ) {
						return o( 'data_type', e );
					} }
					value={ r === null || r === void 0 ? void 0 : r.data_type }
					staticSearch={ ! 0 }
				/>
				<Controls.DividerWP marginStart={ '2' } marginEnd={ '2' } />
				<Nm
					title={ ( 0, I18n.__ )( 'Status', 'ohmylms' ) }
					description={ ( 0, I18n.__ )(
						'Enable or disable this webhook',
						'ohmylms'
					) }
					placeholder={ ( 0, I18n.__ )( 'Select status', 'ohmylms' ) }
					data={ l }
					notFoundMessage={ ( 0, I18n.__ )(
						'Nothing Found',
						'ohmylms'
					) }
					isMultiple={ ! 1 }
					onChange={ function ( e ) {
						return o( 'status', e );
					} }
					value={ r === null || r === void 0 ? void 0 : r.status }
					staticSearch={ ! 0 }
				/>
			</React.Fragment>
		);
	};
}
