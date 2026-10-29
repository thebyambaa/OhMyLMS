/** Reconstructed React source. Runtime dependencies are explicit in components.json. */
import { createElement } from '@wordpress/element';
import * as Controls from '../../shared/controls';
export function createEmailPreview( readRuntime ) {
	return function EmailPreview( props ) {
		const {
			Cee,
			Ea,
			React,
			T: StoreModule,
			b: I18n,
			g: ReactHooks,
			y: WordPressData,
		} = readRuntime();
		var t,
			n,
			r,
			a,
			o,
			i,
			l,
			c = props.selected,
			u = props.content,
			s = props.footerText,
			d = props.courseSuggestionText,
			m = ( 0, ReactHooks.useRef )( null ),
			p = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).getEmail();
			}, [] ),
			f = ( 0, WordPressData.useSelect )( function ( e ) {
				return e( StoreModule.default ).selectSettingsMap();
			}, [] ),
			v = {
				title:
					( p === null || p === void 0 ? void 0 : p.heading ) || '',
				for:
					'creator_new_order' ===
					( p === null ||
					p === void 0 ||
					null === ( t = p.basic ) ||
					void 0 === t
						? void 0
						: t.id )
						? ( 0, I18n.__ )( 'creator', 'ohmylms' )
						: ( 0, I18n.__ )( 'student', 'ohmylms' ),
				content: u || '',
				button_text:
					( p === null || p === void 0 ? void 0 : p.button_text ) ||
					'',
				button_link:
					( p === null || p === void 0 ? void 0 : p.button_link ) ||
					'',
				footer_text: s || '',
				button_position:
					( f === null || f === void 0
						? void 0
						: f.ohmylms_email_button_possition ) || 'center',
				course_suggestion_text: d || '',
				img_url:
					( f === null || f === void 0
						? void 0
						: f.ohmylms_email_branding_image ) || '',
				base_color:
					( f === null || f === void 0
						? void 0
						: f.ohmylms_email_base_color ) || '',
				background_color:
					( f === null || f === void 0
						? void 0
						: f.ohmylms_email_background_color ) || '',
				body_background_color:
					( f === null || f === void 0
						? void 0
						: f.ohmylms_email_body_background_color ) || '',
				color:
					( f === null || f === void 0
						? void 0
						: f.ohmylms_email_body_text_color ) || '',
			},
			h = ( function ( ..._args ) {
				var e =
						_args.length > 0 && void 0 !== _args[ 0 ]
							? _args[ 0 ]
							: {},
					_tLocal = e.img_url,
					_nLocal = void 0 === _tLocal ? Cee : _tLocal,
					_rLocal = e.title,
					_aLocal =
						void 0 === _rLocal
							? 'Congratulations on completing the course!'
							: _rLocal,
					_oLocal = e.content,
					_iLocal =
						void 0 === _oLocal
							? '<div style="text-align: left;"><div>Hi {student_name},</div><div><br></div><div>Well done on successfully finishing the course, {course_name}! We hope you found it both enjoyable and enriching.</div><div><br></div><div><font size="4"><b>Student info\u2028</b></font></div><div>{student_name}</div><div>{student_email}</div><div><br></div><div>We’d love to hear your thoughts! Leaving a review about the course and instructor will not only help us enhance our content but also improve the learning experience for future students.</div></div>'
							: _oLocal,
					_lLocal = e.button_text,
					_cLocal =
						void 0 === _lLocal ? 'Share Your Feedback!' : _lLocal,
					_uLocal = e.button_link,
					_sLocal = void 0 === _uLocal ? '#' : _uLocal,
					_dLocal = e.footer_text,
					_mLocal =
						void 0 === _dLocal
							? 'Thank you for choosing us for your learning journey'
							: _dLocal,
					_pLocal = e.background_color,
					_fLocal = void 0 === _pLocal ? '#F4F5F7' : _pLocal,
					_vLocal = e.body_background_color,
					g = void 0 === _vLocal ? '#F4F5F7' : _vLocal,
					_hLocal = e.color,
					y = void 0 === _hLocal ? '#6e42d3' : _hLocal,
					b = e.base_color,
					_ = void 0 === b ? '#6e42d3' : b;
				return '\n        <!doctype html>\n<html lang="und" dir="auto" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">\n  <head>\n    <title></title>\n    \x3c!--[if !mso]>\x3c!--\x3e\n    <meta http-equiv="X-UA-Compatible" content="IE=edge">\n    \x3c!--<![endif]--\x3e\n    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">\n    <meta name="viewport" content="width=device-width, initial-scale=1">\n    <style type="text/css">\n      #outlook a { padding:0; }\n      body { margin:0;padding:0;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%; }\n      table, td { border-collapse:collapse;mso-table-lspace:0pt;mso-table-rspace:0pt; }\n      img { border:0;height:auto;line-height:100%; outline:none;text-decoration:none;-ms-interpolation-mode:bicubic; }\n      p { display:block;margin:13px 0; }\n    </style>\n    \x3c!--[if mso]>\n    <noscript>\n    <xml>\n    <o:OfficeDocumentSettings>\n      <o:AllowPNG/>\n      <o:PixelsPerInch>96</o:PixelsPerInch>\n    </o:OfficeDocumentSettings>\n    </xml>\n    </noscript>\n    <![endif]--\x3e\n    \x3c!--[if lte mso 11]>\n    <style type="text/css">\n      .mj-outlook-group-fix { width:100% !important; }\n    </style>\n    <![endif]--\x3e\n    \n    \n    <style type="text/css">\n      @media only screen and (min-width:480px) {\n        .mj-column-per-100 { width:100% !important; max-width: 100%; }\n      }\n    </style>\n    <style media="screen and (min-width:480px)">\n      .moz-text-html .mj-column-per-100 { width:100% !important; max-width: 100%; }\n    </style>\n    \n    \n  \n    \n    <style type="text/css">\n\n    @media only screen and (max-width:479px) {\n      table.mj-full-width-mobile { width: 100% !important; }\n      td.mj-full-width-mobile { width: auto !important; }\n      .ohmylms-email-template-body{padding: 0 20px 20px 20px  !important}\n      td.ohmylms-email-template-heading-wrapper{padding: 10px  0 !important}\n      td.ohmylms-email-template-heading-wrapper  div {font-size: 20px  !important; line-height: 1.5 !important; text-align: left !important}\n    }\n  \n    </style>\n    \n    \n  </head>\n  <body style="word-spacing:normal;background-color:'
					.concat(
						_fLocal || '#F4F5F7;',
						'">\n    \n    \n      <div\n         style="background-color:'
					)
					.concat(
						_fLocal || '#F4F5F7;',
						'; padding: 24px; border-radius: 20px;" lang="und" dir="auto"\n      >\n        \n      \n      \x3c!--[if mso | IE]><table align="center" border="0" cellpadding="0" cellspacing="0" class="" role="presentation" style="width:600px;" width="600" ><tr><td style="line-height:0px;font-size:0px;mso-line-heightRule:exactly;"><![endif]--\x3e\n    \n      \n      <div  style="margin:0px auto;max-width:600px;">\n        \n        <table\n           align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;"\n        >\n          <tbody>\n            <tr>\n              <td\n                 style="direction:ltr;font-size:0px;padding:0px;text-align:left;"\n              >\n                \x3c!--[if mso | IE]><table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td class="" style="vertical-align:top;width:600px;" ><![endif]--\x3e\n            \n      <div\n         class="mj-column-per-100 mj-outlook-group-fix" style="font-size:0px;text-align:left;direction:ltr;display:inline-block;vertical-align:top;width:100%;"\n      >\n        \n      <table\n         border="0" cellpadding="0" cellspacing="0" role="presentation" style="vertical-align:top;" width="100%"\n      >\n        <tbody>\n          \n              <tr>\n                <td\n                   align="center" style="background:'
					)
					.concat(
						g || '#FFFFFF',
						';font-size:0px;padding:17px 0px 17px 0px;word-break:break-word;border-radius: 20px 20px 0 0"\n                >\n                  \n      <table\n         border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:collapse;border-spacing:0px;"\n      >\n        <tbody>\n          <tr>\n            <td  style="width:100px;">\n              \n      <img\n         alt="" src=\''
					)
					.concat(
						_nLocal || Cee,
						'\' style="border:0;display:block;outline:none;text-decoration:none;height:auto;width:100%;font-size:13px;" width="100" height="auto"\n      />\n    \n            </td>\n          </tr>\n        </tbody>\n      </table>\n    \n                </td>\n              </tr>\n            \n        </tbody>\n      </table>\n    \n      </div>\n    \n          \x3c!--[if mso | IE]></td></tr></table><![endif]--\x3e\n              </td>\n            </tr>\n          </tbody>\n        </table>\n        \n      </div>\n    \n      \n      \x3c!--[if mso | IE]></td></tr></table><table align="center" border="0" cellpadding="0" cellspacing="0" class="" role="presentation" style="width:600px;" width="600" ><tr><td style="line-height:0px;font-size:0px;mso-line-heightRule:exactly;"><![endif]--\x3e\n    \n      \n      <div  style="margin:0px auto;max-width:600px;">\n        \n        <table\n           align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;"\n        >\n          <tbody>\n            <tr>\n              <td\n                 style="direction:ltr;font-size:0px;padding:0px;text-align:left;background: #FFF"\n              >\n                \x3c!--[if mso | IE]><table role="presentation" border="0" cellpadding="0" cellspacing="0"><tr><td class="" style="vertical-align:top;width:600px;" ><![endif]--\x3e\n            \n      <div\n         class="mj-column-per-100 mj-outlook-group-fix" style="font-size:0px;text-align:left;direction:ltr;display:inline-block;vertical-align:top;width:100%;"\n      >\n        \n      <table\n         border="0" cellpadding="0" cellspacing="0" role="presentation" style="vertical-align:top;" width="100%"\n      >\n        <tbody>\n          \n              <tr>\n                <td\n                   align="center" style="background:'
					)
					.concat(
						g || '#FFFFFF',
						';font-size:0px;padding:0px 0px 0px 0px;word-break:break-word;"\n                >\n                  \n      <p\n         style="border-top:solid 1px #EBECED;font-size:1px;margin:0px auto;width:100%;"\n      >\n      </p>\n      \n      \x3c!--[if mso | IE]><table align="center" border="0" cellpadding="0" cellspacing="0" style="border-top:solid 1px #EBECED;font-size:1px;margin:0px auto;width:600px;" role="presentation" width="600px" ><tr><td style="height:0;line-height:0;"> &nbsp;\n</td></tr></table><![endif]--\x3e\n    \n    \n                </td>\n              </tr>\n            \n        </tbody>\n      </table>\n    \n      </div>\n    \n          \x3c!--[if mso | IE]></td></tr></table><![endif]--\x3e\n              </td>\n            </tr>\n          </tbody>\n        </table>\n        \n      </div>\n    \n      \n      \x3c!--[if mso | IE]></td></tr></table><table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" style="width:600px;" width="600" ><tr><td style="line-height:0;font-size:0;mso-line-heightRule:exactly;"><v:image style="border:0;mso-position-horizontal:center;position:absolute;top:0;width:600px;z-index:-3;" xmlns:v="urn:schemas-microsoft-com:vml" /><![endif]--\x3e\n      <div\n         style="margin:0 auto;max-width:600px;"\n      >\n        <table\n           border="0" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;"\n        >\n          <tbody>\n            <tr\n               style="vertical-align:top;"\n            >\n              \n          <td  style="width:0.01%;padding-bottom:NaN%;mso-padding-bottom-alt:0;" />\n          <td class="ohmylms-email-template-body" style="background:'
					)
					.concat(
						g || '#FFFFFF',
						';background-position:center center;background-repeat:no-repeat;padding:0px 40px 30px 40px;vertical-align:top;border-radius: 0 0 20px 20px;">\n            \n      \x3c!--[if mso | IE]><table border="0" cellpadding="0" cellspacing="0" style="width:600px;" width="600" ><tr><td style=""><![endif]--\x3e\n      <div\n         class="mj-hero-content" style="margin:0px auto;"\n      >\n        <table\n           border="0" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;margin:0px;"\n        >\n          <tbody>\n            <tr>\n              <td  style="" >\n                <table\n                   border="0" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;margin:0px;"\n                >\n                  <tbody>\n                    \n                        <tr>\n                          <td\n                             align="center" style="font-size:0px;padding:29px 0px 28px 0px;word-break:break-word;"\n                             class="ohmylms-email-template-heading-wrapper"\n                          >\n                            \n      <div\n         style="font-family:Arial;font-size:38px;font-weight:800;line-height:48px;text-align:center;color:'
					)
					.concat( _ || '#6e42d3', ';"\n      >' )
					.concat(
						_aLocal,
						'</div>\n    \n                          </td>\n                        </tr>\n                      \n                        <tr>\n                          <td\n                             align="center" style="font-size:0px;padding:0px 0px 28px 0px;word-break:break-word;"\n                          >\n                            \n      <div\n         style="font-family:Arial;font-size:15px;font-weight:normal;line-height:30px;text-align:left;color:'
					)
					.concat( y || '#1F2328', ';"\n      >\n      ' )
					.concat(
						_iLocal,
						'\n      </div>\n    \n                          </td>\n                        </tr>\n                      \n                        <tr>\n                          <td\n                             align="left" style="font-size:0px;padding:0px 0px 30px 0px;word-break:break-word;"\n                          >\n                            \n      <table\n         border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:separate;line-height:100%;"\n      >\n        <tbody>\n          <tr>\n            <td\n               align="center" bgcolor="#6e42d3" role="presentation" style="border:none;border-radius:12px;cursor:auto;mso-padding-alt:14px 20px 14px 20px;text-align:center;background:#6e42d3;" valign="middle"\n            >\n              <a\n                 href="'
					)
					.concat(
						_sLocal,
						'" style="display:inline-block;background:'
					)
					.concat(
						_ || '#6e42d3',
						';color:#ffffff;font-family:Arial;font-size:15px;font-weight:normal;line-height:120%;margin:0;text-decoration:none;text-transform:none;padding:14px 20px 14px 20px;mso-padding-alt:0px;border-radius:12px;" target="_blank"\n              >\n                '
					)
					.concat(
						_cLocal,
						'\n              </a>\n            </td>\n          </tr>\n        </tbody>\n      </table>\n    \n                          </td>\n                        </tr>\n                      \n                        <tr>\n                          <td\n                             align="left" background="#F4F5F7" style="background:#F4F5F7;font-size:0px;padding:10px 25px 10px 25px;word-break:break-word;border-radius: 10px;padding: 20px;"\n                          >\n                            \n      <div\n         style="font-family:Arial;font-size:13px;font-weight:400;line-height:1.7;text-align:left;color:#8A8A97;"\n      ><div style="text-align: center;"><span style="word-spacing: normal;">'
					)
					.concat(
						_mLocal,
						'</span></div></div>\n    \n                          </td>\n                        </tr>\n                      \n                  </tbody>\n                </table>\n              </td>\n            </tr>\n          </tbody>\n        </table>\n      </div>\n      \x3c!--[if mso | IE]></td></tr></table><![endif]--\x3e\n    \n          </td>\n          <td  style="width:0.01%;padding-bottom:NaN%;mso-padding-bottom-alt:0;" />\n        \n            </tr>\n          </tbody>\n      </table>\n    </div>\n    \x3c!--[if mso | IE]></td></tr></table><![endif]--\x3e\n    \n      </div>\n    \n  </body>\n</html>\n    '
					);
			} )( v );
		return (
			'student_new_order' ===
				( p === null ||
				p === void 0 ||
				null === ( n = p.basic ) ||
				void 0 === n
					? void 0
					: n.id ) ||
			'creator_new_order' ===
				( p === null ||
				p === void 0 ||
				null === ( r = p.basic ) ||
				void 0 === r
					? void 0
					: r.id )
				? ( h = ( function ( ..._args2 ) {
						var e =
								_args2.length > 0 && void 0 !== _args2[ 0 ]
									? _args2[ 0 ]
									: {},
							_tLocal2 = e.img_url,
							_nLocal2 = void 0 === _tLocal2 ? Cee : _tLocal2,
							_rLocal2 = e.title,
							_aLocal2 =
								void 0 === _rLocal2
									? 'Order Confirmed: Your Course Awaits!'
									: _rLocal2,
							_oLocal2 = e.content,
							_iLocal2 =
								void 0 === _oLocal2
									? '<p style="margin: 0 0 15px 0; padding:0; color: #1F2328; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6;">Dear [student_name],</p><p style="margin: 0 0 15px 0; padding:0; color: #1F2328; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6;">Thank you for your purchase! We\'ve successfully received your order and are processing it. Below are the details for your reference.</p>'
									: _oLocal2,
							_lLocal2 = e.button_text,
							_cLocal2 = ( () => {
								if ( void 0 === _lLocal2 ) {
									if (
										'creator' !==
										( e === null || e === void 0
											? void 0
											: e.for )
									) {
										return 'View My Course';
									}
									return '';
								}
								return _lLocal2;
							} )(),
							_uLocal2 = ( e.button_link, e.footer_text ),
							_sLocal2 = ( () => {
								if ( void 0 === _uLocal2 ) {
									if (
										'creator' !==
										( e === null || e === void 0
											? void 0
											: e.for )
									) {
										return 'You can access your course(s) and view your full order details by clicking the button below.';
									}
									return '';
								}
								return _uLocal2;
							} )(),
							_dLocal2 = e.background_color,
							_mLocal2 =
								void 0 === _dLocal2 ? '#F4F5F7' : _dLocal2,
							_pLocal2 = e.body_background_color,
							_fLocal2 =
								void 0 === _pLocal2 ? '#FFFFFF' : _pLocal2,
							_vLocal2 = e.color,
							g = void 0 === _vLocal2 ? '#6e42d3' : _vLocal2,
							_hLocal2 = e.base_color,
							y = void 0 === _hLocal2 ? '#6e42d3' : _hLocal2,
							b = e.button_position,
							_ = '<div style="text-align: '
								.concat(
									( void 0 === b ? 'center' : b ) || 'center',
									'; margin: 25px 0 0;">\n                        <a href="#" style="background: '
								)
								.concat(
									y || '#6e42d3',
									';#6e42d3; color: #ffffff; padding: 14px 25px; font-size: 15px; font-weight: 500; line-height:1; text-transform: capitalize; text-decoration: none; border-radius: 12px; display: inline-block;">\n                            '
								)
								.concat(
									_cLocal2,
									'\n                        </a>\n                    </div>'
								);
						return '\n<!DOCTYPE html>\n<html lang="en">\n<head>\n    <meta charset="UTF-8">\n    <meta name="viewport" content="width=device-width, initial-scale=1.0">\n    <title>New Order #12345</title>\n</head>\n\n<body>\n    <div class="ohmylms-email-container">\n        <table class="ohmylms-table-main" style="width: 100%; border-spacing: 0; background: '
							.concat(
								_mLocal2 || '#F4F5F7',
								'; border:0;">\n            <tr style="background: transparent; border: none; border-radius: 0;">\n                <td style="background: transparent; border: none; border-radius: 0; padding: 40px;">\n                    <table class="ohmylms-table2" style="border-spacing: 0; max-width: 600px; width: 100%; margin-left: auto; margin-right: auto; border-collapse: separate;">\n                        <tr>\n                            <td style="border: 5px solid '
							)
							.concat( y || '#6e42d3', '; background: ' )
							.concat(
								_fLocal2 || '#FFFFFF',
								'; border-radius: 20px;">\n                                <table class="ohmylms-table3" style="width: 100%; border-collapse: collapse;">\n                                    \x3c!-- Header with Logo --\x3e\n                                    <tr class="ohmylms-email-header">\n                                        <td style="border: none; border-bottom: 1px solid #eee; padding: 16px 20px; text-align: center; background: '
							)
							.concat(
								_fLocal2 || '#FFFFFF',
								'; border-radius: 20px 20px 0 0;">\n                                            <img src="'
							)
							.concat(
								_nLocal2 || Cee,
								'" alt="Your Logo" width="auto" height="auto" style="border: 0; display: block; margin: 0 auto;">\n                                        </td>\n                                    </tr>\n                                    \x3c!-- Main Content --\x3e\n                                    <tr>\n                                        <td style="padding: 25px 35px 30px; background: '
							)
							.concat(
								_fLocal2 || '#FFFFFF',
								'; border: 0; border-radius: 0 0 20px 20px;">\n                                            <div class="body-content-inner" style="color: #1F2328; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6; text-align: '
							)
							.concat(
								'rtl' === document.dir ? 'right' : 'left',
								';">\n                                                <h1 style="padding: 0; color: '
							)
							.concat(
								y || '#6e42d3',
								';; font-size: 28px; line-height: 1.3; margin: 0; font-weight: bold; text-align: center; margin-bottom: 20px;">\n                                                    '
							)
							.concat(
								_aLocal2,
								'\n                                                </h1>\n                                                <div style="color: '
							)
							.concat(
								g || '#1F2328',
								'">\n                                                '
							)
							.concat(
								_iLocal2,
								'\n                                                </div>\n\n                                                <h2 style="color: '
							)
							.concat(
								y || '#6e42d3',
								';; font-size: 16px; line-height: 1.3; margin-bottom: 20px; font-weight: bold;">\n                                                  [order_id] [order_date]\n                                                </h2>\n\n                                              <table class="ohmylms-order-items-table" style="width: 100%; border: 0; border-collapse: separate; border-radius: 0; background: transparent; margin: 0 0 20px">\n                                                <tr>\n                                                  <td style="border: 1px solid #EBECED; border-radius: 10px; overflow: hidden; background: transparent;">\n                                                    <table style="width: 100%; border: 0; border-collapse: collapse; border-radius: 0; background: transparent;">\n                                                      <thead>\n                                                        <tr>\n                                                          <th style="background: '
							)
							.concat(
								_fLocal2 || '#FFFFFF',
								'; color: #7A8B9A; font-size: 14px; line-height: 1; padding: 13px 20px; border: none; border-bottom: 1px solid #EBECED; text-align: left; border-radius: 10px 0 0 0; font-weight: 500;">\n                                                            [course_name]\n                                                          </th>\n                                                          <th style="background: '
							)
							.concat(
								_fLocal2 || '#FFFFFF',
								'; color: #7A8B9A; font-size: 14px; line-height: 1; padding: 13px 20px; border: none; border-bottom: 1px solid #EBECED; text-align: left; font-weight: 500;">\n                                                            Quantity\n                                                          </th>\n                                                          <th style="background: '
							)
							.concat(
								_fLocal2 || '#FFFFFF',
								'; color: #7A8B9A; font-size: 14px; line-height: 1; padding: 13px 20px; border: none; border-bottom: 1px solid #EBECED; text-align: left; border-radius: 0 10px 0 0; font-weight: 500;">\n                                                            Price\n                                                          </th>\n                                                        </tr>\n                                                      </thead>\n                                                      <tbody>\n                                                        \x3c!-- Replace these rows with actual data --\x3e\n                                                        <tr>\n                                                          <td style="background: transparent; padding: 14px 20px; border:none; border-bottom: 1px solid #EBECED; color: #000D25; font-size: 14px; font-weight: 400; line-height: 1.3;">\n                                                            UX design for virtual reality\n                                                          </td>\n                                                          <td style="background: transparent; padding: 14px 20px; border:none; border-bottom: 1px solid #EBECED; color: #000D25; font-size: 14px; font-weight: 400; line-height: 1.3;">\n                                                            1\n                                                          </td>\n                                                          <td style="background: transparent; padding: 14px 20px; border:none; border-bottom: 1px solid #EBECED; color: #000D25; font-size: 14px; font-weight: 400; line-height: 1.3; width: 110px;">\n                                                            $100.00\n                                                          </td>\n                                                        </tr>\n                                                        \x3c!-- Add more rows as needed --\x3e\n                                                      </tbody>\n                                                      <tfoot>\n                                                        <tr>\n                                                          <th colspan="2" style="background: transparent; padding: 14px 20px 7px; color: '
							)
							.concat(
								g || '#1F2328',
								'; border: none; text-align: right; font-size: 14px; font-weight: 500; line-height: 1;">\n                                                            Subtotal:\n                                                          </th>\n                                                          <td style="background: transparent; padding: 14px 20px 7px; color: '
							)
							.concat(
								g || '#1F2328',
								'; border: none; font-size: 14px; font-weight: 400; line-height: 1; width: 110px;">\n                                                            $100.00\n                                                          </td>\n                                                        </tr>\n                                                        <tr>\n                                                          <th colspan="2" style="background: transparent; padding: 7px 20px 14px; color: '
							)
							.concat(
								g || '#1F2328',
								'; border: none; text-align: right; font-size: 14px; font-weight: 500; line-height: 1;">\n                                                            Payment method:\n                                                          </th>\n                                                          <td style="background: transparent; padding: 7px 20px 14px; color: '
							)
							.concat(
								g || '#1F2328',
								'; border: none; font-size: 14px; font-weight: 400; line-height: 1; width: 110px;">\n                                                            Credit Card\n                                                          </td>\n                                                        </tr>\n                                                        <tr>\n                                                          <th colspan="2" style="background: transparent; padding: 14px 20px; color: '
							)
							.concat(
								g || '#1F2328',
								'; border: none; border-top: 1px solid #EBECED; text-align: right; font-size: 14px; font-weight: 500; line-height: 1;">\n                                                            Total:\n                                                          </th>\n                                                          <td style="background: transparent; padding: 14px 20px; color: '
							)
							.concat(
								g || '#1F2328',
								'; border: none; border-top: 1px solid #EBECED; font-size: 14px; font-weight: 400; line-height: 1; width: 110px;">\n                                                            $100.00\n                                                          </td>\n                                                        </tr>\n                                                      </tfoot>\n                                                    </table>\n                                                  </td>\n                                                </tr>\n                                              </table>\n\n\n                                                <div style="margin: 0 0 15px 0; padding:0; color: '
							)
							.concat(
								g || '#1F2328',
								'; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6;">\n                                                    '
							)
							.concat(
								_sLocal2,
								'\n                                                </div>\n\n                                                '
							)
							.concat(
								_cLocal2 && _,
								'\n                                            </div>\n                                        </td>\n                                    </tr>\n                                </table>\n                            </td>\n                        </tr>\n                    </table>\n                </td>\n            </tr>\n        </table>\n    </div>\n</body>\n</html>\n    '
							);
					} )( v ) )
				: ( () => {
						if (
							'student_confirm_enrollment' ===
							( p === null ||
							p === void 0 ||
							null === ( a = p.basic ) ||
							void 0 === a
								? void 0
								: a.id )
						) {
							return ( h = ( function ( ..._args3 ) {
								var e =
										_args3.length > 0 &&
										void 0 !== _args3[ 0 ]
											? _args3[ 0 ]
											: {},
									_tLocal3 = e.img_url,
									_nLocal3 =
										void 0 === _tLocal3 ? Cee : _tLocal3,
									_rLocal3 = e.title,
									_aLocal3 =
										void 0 === _rLocal3
											? 'Enrollment Complete: <br> Welcome to Your Course!'
											: _rLocal3,
									_oLocal3 = e.content,
									_iLocal3 =
										void 0 === _oLocal3
											? '<p style="margin: 0 0 15px 0; padding:0; color: #1F2328;">Dear [student_name],</p><p style="margin: 0 0 15px 0; padding:0; color: #1F2328;">Your enrollment in <strong>[course_name]</strong> is confirmed! We’re excited to have you on this journey of learning and growth.</p>'
											: _oLocal3,
									_lLocal3 = e.button_text,
									_cLocal3 =
										void 0 === _lLocal3
											? 'Access My Course'
											: _lLocal3,
									_uLocal3 = ( e.button_link, e.footer_text ),
									_sLocal3 =
										void 0 === _uLocal3
											? 'You can access your course(s) and view your full order details by clicking the button below.'
											: _uLocal3,
									_dLocal3 = e.background_color,
									_mLocal3 =
										void 0 === _dLocal3
											? '#F4F5F7'
											: _dLocal3,
									_pLocal3 = e.body_background_color,
									_fLocal3 =
										void 0 === _pLocal3
											? '#FFFFFF'
											: _pLocal3,
									_vLocal3 = e.color,
									g =
										void 0 === _vLocal3
											? '#6e42d3'
											: _vLocal3,
									_hLocal3 = e.base_color,
									y =
										void 0 === _hLocal3
											? '#6e42d3'
											: _hLocal3,
									b = e.button_position,
									_ = '<div style="text-align: '
										.concat(
											( void 0 === b ? 'center' : b ) ||
												'center',
											'; margin: 25px 0 0;">\n                        <a href="#" style="background: '
										)
										.concat(
											y || '#6e42d3',
											';#6e42d3; color: #ffffff; padding: 14px 25px; font-size: 15px; font-weight: 500; line-height:1; text-transform: capitalize; text-decoration: none; border-radius: 12px; display: inline-block;">\n                            '
										)
										.concat(
											_cLocal3,
											'\n                        </a>\n                    </div>'
										);
								return '\n<html lang="en">\n<head>\n    <meta charset="UTF-8">\n    <meta name="viewport" content="width=device-width, initial-scale=1.0">\n    <title>Confirm Enrollment</title>\n</head>\n<body>\n    <div class="ohmylms-email-container">\n        <table class="ohmylms-table-main" style="width: 100%; border-spacing: 0; background: background: '
									.concat(
										_mLocal3 || '#F4F5F7',
										'; border:0;">\n            <tr style="background: transparent; border: none; border-radius: 0;">\n                <td style="background: transparent; border: none; border-radius: 0; padding: 40px;">\n                    <table class="ohmylms-table2" style="border-spacing: 0; max-width: 600px; width: 100%; margin-left: auto; margin-right: auto; border-collapse: separate;">\n                        <tr>\n                            <td style="border: 5px solid '
									)
									.concat( y || '#6e42d3', '; background: ' )
									.concat(
										_fLocal3 || '#FFFFFF',
										'; border-radius: 20px;">\n                                <table class="ohmylms-table3" style="width: 100%; border-collapse: collapse;">\n                                    \x3c!-- Header with Logo --\x3e\n                                    <tr class="ohmylms-email-header">\n                                        <td style="border: none; border-bottom: 1px solid #eee; padding: 16px 20px; text-align: center; background: '
									)
									.concat(
										_fLocal3 || '#FFFFFF',
										'; border-radius: 20px 20px 0 0;">\n                                            <img src="'
									)
									.concat(
										_nLocal3 || Cee,
										'" alt="Your Logo" width="auto" height="auto" style="border: 0; display: block; margin: 0 auto;">\n                                        </td>\n                                    </tr>                                    \n                                    \x3c!-- Main Content --\x3e\n                                    <tr>\n                                        <td style="padding: 25px 35px 30px; background: '
									)
									.concat(
										_fLocal3 || '#FFFFFF',
										'; border: 0; border-radius: 0 0 20px 20px;">\n                                            <div class="body-content-inner" style="color: '
									)
									.concat(
										g || '#1f2328',
										'; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6; text-align: '
									)
									.concat(
										'rtl' === document.dir
											? 'right'
											: 'left',
										';">\n                                                <h1 style="padding: 0; color: '
									)
									.concat(
										y || '#6e42d3',
										'; font-size: 28px; line-height: 1.3; margin: 0; font-weight: bold; text-align: center; margin-bottom: 20px;">\n                                                    '
									)
									.concat(
										_aLocal3,
										'\n                                                </h1>\n                                                <div style="color: '
									)
									.concat(
										g || '#1f2328',
										';">\n                                                    '
									)
									.concat(
										_iLocal3,
										'\n                                                </div>\n                                                <table class="ohmylms-order-items-table" style="width: 100%; border: 0; border-collapse: separate; border-radius: 0; background: transparent; margin: 0 0 20px;">\n                                                    <tr>\n                                                        <td style="border: 1px solid #EBECED; border-radius: 10px; overflow: hidden; background: transparent;">\n                                                            <table style="width: 100%; border: 0; border-collapse: collapse; border-radius: 0; background: transparent;">\n                                                                <tbody>\n                                                                    <tr>\n                                                                        <td style="background: transparent; padding: 14px 20px; border:none; border-bottom: 1px solid #EBECED;">\n                                                                            <span style="color: #7A8B9A;">Course Name:</span>\n                                                                            [course_name]\n                                                                        </td>\n                                                                    </tr>\n                                                                    <tr>\n                                                                        <td style="background: transparent; padding: 14px 20px; border:none; border-bottom: 1px solid #EBECED;">\n                                                                            <span style="color: #7A8B9A;">Enrollment Date:</span>\n                                                                            [start_date]\n                                                                        </td>\n                                                                    </tr>\n                                                                    <tr>\n                                                                        <td style="background: transparent; padding: 14px 20px; border:none;">\n                                                                            <span style="color: #7A8B9A;">Access Duration:</span>\n                                                                            [course_access_duration]\n                                                                        </td>\n                                                                    </tr>\n                                                                </tbody>\n                                                            </table>\n                                                        </td>\n                                                    </tr>\n                                                </table>\n                                                <div style="margin: 0 0 15px 0; padding:0; color: '
									)
									.concat(
										g || '#1f2328',
										'">\n                                                    '
									)
									.concat(
										_sLocal3,
										'\n                                                </div>\n                                                '
									)
									.concat(
										_cLocal3 && _,
										'\n                                            </div>\n                                        </td>\n                                    </tr>\n                                </table>\n                            </td>\n                        </tr>\n                    </table>\n                </td>\n            </tr>\n        </table>\n    </div>\n</body>\n</html>\n\n    '
									);
							} )( v ) );
						}
						if (
							'student_cancel_enrollment' ===
								( p === null ||
								p === void 0 ||
								null === ( o = p.basic ) ||
								void 0 === o
									? void 0
									: o.id ) ||
							'creator_cancelled_order' ===
								( p === null ||
								p === void 0 ||
								null === ( i = p.basic ) ||
								void 0 === i
									? void 0
									: i.id )
						) {
							return ( h = ( function ( ..._args4 ) {
								var e =
										_args4.length > 0 &&
										void 0 !== _args4[ 0 ]
											? _args4[ 0 ]
											: {},
									_tLocal4 = e.img_url,
									_nLocal4 =
										void 0 === _tLocal4 ? Cee : _tLocal4,
									_rLocal4 = e.title,
									_aLocal4 =
										void 0 === _rLocal4
											? 'Enrollment Cancelled: Need Assistance?'
											: _rLocal4,
									_oLocal4 = e.content,
									_iLocal4 =
										void 0 === _oLocal4
											? '<p>Hi [student_name],</p><p style="margin: 0 0 15px 0; padding:0; color: #1F2328;">We noticed your enrollment for <strong>[course_name]</strong> was canceled.</p>'
											: _oLocal4,
									_lLocal4 =
										( e.button_text,
										e.button_link,
										e.footer_text ),
									_cLocal4 =
										void 0 === _lLocal4
											? '<p>Your refund will be processed as soon as possible, and you will receive an e-mail notification once it has been completed. Please allow <strong>[expected_processing_time]</strong> for the refund to reflect in your account.</p><p>Thank you for choosing <strong>[store_name]</strong>, and we look forward to serving you better in the future. If you\'d like to explore more of our courses or re-enroll, contact us at<a href="mailto:[email@example.com]" style="color: var(--ohmylms-primary-color); font-weight: 500;">[email@example.com]</a>.</p><p >Best,<strong>[site_name]</strong></p>'
											: _lLocal4,
									_uLocal4 = e.background_color,
									_sLocal4 =
										void 0 === _uLocal4
											? '#F4F5F7'
											: _uLocal4,
									_dLocal4 = e.body_background_color,
									_mLocal4 =
										void 0 === _dLocal4
											? '#FFFFFF'
											: _dLocal4,
									_pLocal4 = e.color,
									_fLocal4 =
										void 0 === _pLocal4
											? '#6e42d3'
											: _pLocal4,
									_vLocal4 = e.base_color,
									g =
										void 0 === _vLocal4
											? '#6e42d3'
											: _vLocal4;
								return (
									e.button_position,
									'\n<!DOCTYPE html>\n<html lang="en">\n\n<head>\n    <meta charset="UTF-8">\n    <meta name="viewport" content="width=device-width, initial-scale=1.0">\n    <title>Confirm Enrollment</title>\n</head>\n\n<body>\n    <div class="ohmylms-email-container">\n        <table class="ohmylms-table-main" style="width: 100%; border-spacing: 0; background: '
										.concat(
											_sLocal4 || '#F4F5F7',
											'; border:0;">\n            <tr style="background: transparent; border: none; border-radius: 0;">\n                <td style="background: transparent; border: none; border-radius: 0; padding: 40px;">\n                    <table class="ohmylms-table2" style="border-spacing: 0; max-width: 600px; width: 100%; margin-left: auto; margin-right: auto; border-collapse: separate;">\n                        <tr>\n                            <td style="border: 5px solid '
										)
										.concat(
											g || '#6e42d3',
											'; background: '
										)
										.concat(
											_mLocal4 || '#FFFFFF',
											'; border-radius: 20px;">\n                                <table class="ohmylms-table3" style="width: 100%; border-collapse: collapse;">\n                                    \x3c!-- Header with Logo --\x3e\n                                    <tr class="ohmylms-email-header">\n                                        <td style="border: none; border-bottom: 1px solid #eee; padding: 16px 20px; text-align: center; background: '
										)
										.concat(
											_mLocal4 || '#FFFFFF',
											'; border-radius: 20px 20px 0 0;">\n                                            <img src="'
										)
										.concat(
											_nLocal4 || Cee,
											'" alt="Your Logo" width="auto" height="auto" style="border: 0; display: block; margin: 0 auto;">\n                                        </td>\n                                    </tr>\n                                    \x3c!-- Main Content --\x3e\n                                    <tr>\n                                        <td style="padding: 25px 35px 30px; background: '
										)
										.concat(
											_mLocal4 || '#FFFFFF',
											'; border: 0; border-radius: 0 0 20px 20px;">\n                                            <div class="body-content-inner" style="color: '
										)
										.concat(
											_fLocal4 || '#1f2328',
											'"; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6; text-align: '
										)
										.concat(
											'rtl' === document.dir
												? 'right'
												: 'left',
											';">\n                                                <h1 style="padding: 0; color: '
										)
										.concat(
											g || '#6e42d3',
											'; font-size: 28px; line-height: 1.3; margin: 0; font-weight: bold; text-align: center; margin-bottom: 20px;">\n                                                    '
										)
										.concat(
											_aLocal4,
											'\n                                                </h1>\n\n                                                <div style="margin: 0 0 15px 0; padding:0; color: '
										)
										.concat(
											_fLocal4 || '#1f2328',
											'";">\n                                                    '
										)
										.concat(
											_iLocal4,
											'\n                                                </div>\n\n                                                <div style="box-shadow: 0px 1px 4px #D3D6DD; background: '
										)
										.concat(
											_mLocal4 || '#FFFFFF',
											'; padding: 20px; border-radius: 12px; margin: 0 0 20px;">\n                                                    <p style="color: '
										)
										.concat(
											_fLocal4 || '#1f2328',
											'; font-size: 16px; font-weight: 500; margin: 0 0 10px;">\n                                                        Enrollment Canceled\n                                                    </p>\n\n                                                    <ul style="padding: 0 0 0 35px;">\n                                                        <li style="color: '
										)
										.concat(
											_fLocal4 || '#1f2328',
											'"; margin: 0 0 5px;">\n                                                            Order Date:\n                                                            <strong style="color: '
										)
										.concat(
											_fLocal4 || '#1f2328',
											'">[start_date]</strong>\n                                                        </li>\n\n                                                        <li style="color: '
										)
										.concat(
											_fLocal4 || '#1f2328',
											'"; margin: 0 0 5px;">\n                                                            Status:\n                                                            <strong style="color: '
										)
										.concat(
											_fLocal4 || '#1f2328',
											'">Canceled</strong>\n                                                        </li>\n\n                                                        <li style="color: '
										)
										.concat(
											_fLocal4 || '#1f2328',
											'";">\n                                                            Reason (if provided):\n                                                            <strong style="color: '
										)
										.concat(
											_fLocal4 || '#1f2328',
											'">[reason_for_cancellation]</strong>\n                                                        </li>\n                                                    </ul>\n                                                </div>\n\n                                                <div style="margin: 0 0 15px 0; padding:0; color: '
										)
										.concat(
											_fLocal4 || '#1f2328',
											'";">\n                                                    '
										)
										.concat(
											_cLocal4,
											'\n                                                </div>\n                                            </div>\n                                        </td>\n                                    </tr>\n                                </table>\n                            </td>\n                        </tr>\n                    </table>\n                </td>\n            </tr>\n        </table>\n    </div>\n</body>\n\n</html>\n\n\n    '
										)
								 );
							} )( v ) );
						}
						return (
							'student_course_completed' ===
								( p === null ||
								p === void 0 ||
								null === ( l = p.basic ) ||
								void 0 === l
									? void 0
									: l.id ) &&
							( h = ( function ( ..._args5 ) {
								var e =
										_args5.length > 0 &&
										void 0 !== _args5[ 0 ]
											? _args5[ 0 ]
											: {},
									_tLocal5 = e.img_url,
									_nLocal5 =
										void 0 === _tLocal5 ? Cee : _tLocal5,
									_rLocal5 = e.title,
									_aLocal5 =
										void 0 === _rLocal5
											? 'Congratulations! You’ve Completed'
											: _rLocal5,
									_oLocal5 = e.content,
									_iLocal5 =
										void 0 === _oLocal5
											? '<p>Hi [student_name],</p><p style="margin: 0 0 15px 0; padding:0; color: #1F2328;">We noticed your enrollment for <strong>[course_name]</strong> was cancelled.</p>'
											: _oLocal5,
									_lLocal5 = e.button_text,
									_cLocal5 =
										void 0 === _lLocal5
											? 'Access My Certificate'
											: _lLocal5,
									_uLocal5 = ( e.button_link, e.footer_text ),
									_sLocal5 =
										void 0 === _uLocal5
											? 'Click below to view your course and access your certificate:'
											: _uLocal5,
									_dLocal5 = e.background_color,
									_mLocal5 =
										void 0 === _dLocal5
											? '#F4F5F7'
											: _dLocal5,
									_pLocal5 = e.body_background_color,
									_fLocal5 =
										void 0 === _pLocal5
											? '#FFFFFF'
											: _pLocal5,
									_vLocal5 = e.color,
									g =
										void 0 === _vLocal5
											? '#6e42d3'
											: _vLocal5,
									_hLocal4 = e.base_color,
									y =
										void 0 === _hLocal4
											? '#6e42d3'
											: _hLocal4,
									b = e.button_position,
									_ = void 0 === b ? 'center' : b,
									w = e.course_suggestion_text,
									E =
										void 0 === w
											? 'To continue on your implementation journey, we recommend you take the following courses next:'
											: w,
									S = '<div style="text-align: '
										.concat(
											_ || 'center',
											'; margin: 25px 0 0;">\n                        <a href="#" style="background: '
										)
										.concat(
											y || '#6e42d3',
											';#6e42d3; color: #ffffff; padding: 14px 25px; font-size: 15px; font-weight: 500; line-height:1; text-transform: capitalize; text-decoration: none; border-radius: 12px; display: inline-block;">\n                            '
										)
										.concat(
											_cLocal5,
											'\n                        </a>\n                    </div>'
										);
								return '\n<!DOCTYPE html>\n<html lang="en">\n\n<head>\n    <meta charset="UTF-8">\n    <meta name="viewport" content="width=device-width, initial-scale=1.0">\n    <title>Complete Course #</title>\n</head>\n\n<body>\n    <div class="ohmylms-email-container">\n        <table class="ohmylms-table-main" style="width: 100%; border-spacing: 0; background: '
									.concat(
										_mLocal5 || '#F4F5F7',
										'; border:0;">\n            <tr style="background: transparent; border: none; border-radius: 0;">\n                <td style="background: transparent; border: none; border-radius: 0; padding: 40px;">\n                    <table class="ohmylms-table2" style="border-spacing: 0; max-width: 600px; width: 100%; margin-left: auto; margin-right: auto; border-collapse: separate;">\n                        <tr>\n                            <td style="border: 5px solid '
									)
									.concat( y || '#6e42d3', '; background: ' )
									.concat(
										_fLocal5 || '#FFFFFF',
										'; border-radius: 20px;">\n                                <table class="ohmylms-table3" style="width: 100%; border-collapse: collapse;">\n                                    \x3c!-- Header with Logo --\x3e\n                                    <tr class="ohmylms-email-header">\n                                        <td style="border: none; border-bottom: 1px solid #eee; padding: 16px 20px; text-align: center; background: '
									)
									.concat(
										_fLocal5 || '#FFFFFF',
										'; border-radius: 20px 20px 0 0;">\n                                            <img src="'
									)
									.concat(
										_nLocal5 || Cee,
										'" alt="Your Logo" width="auto" height="auto" style="border: 0; display: block; margin: 0 auto;">\n                                        </td>\n                                    </tr>\n                                    \x3c!-- Main Content --\x3e\n                                    <tr>\n                                        <td style="padding: 25px 35px 30px; background: '
									)
									.concat(
										_fLocal5 || '#FFFFFF',
										'; border: 0; border-radius: 0 0 20px 20px;">\n                                            <div class="body-content-inner" style="color: '
									)
									.concat(
										g || '#1f2328',
										'; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6; text-align: '
									)
									.concat(
										'rtl' === document.dir
											? 'right'
											: 'left',
										';">\n                                                <h1 style="padding: 0; color: '
									)
									.concat(
										y || '#6e42d3',
										'; font-size: 28px; line-height: 1.3; margin: 0; font-weight: bold; text-align: center; margin-bottom: 20px;">\n                                                    '
									)
									.concat(
										_aLocal5,
										'\n                                                </h1>\n                                                <div style="margin: 0 0 15px 0; padding:0; color: '
									)
									.concat(
										g || '#1f2328',
										';">\n                                                  '
									)
									.concat(
										_iLocal5,
										'\n                                                </div>\n                                              \n                                                <p style="color: '
									)
									.concat(
										g || '#1f2328',
										'; font-size: 16px; font-weight: 500; margin: 0 0 10px;">\n                                                    Completion Details:\n                                                </p>\n\n                                                <table class="ohmylms-order-items-table" style="width: 100%; border: 0; border-collapse: separate; margin: 0 0 20px">\n                                                    <tr>\n                                                        <td style="border: 1px solid #EBECED; border-radius: 10px; overflow: hidden;">\n                                                            <table style="width: 100%; border: 0; border-collapse: collapse;">\n                                                                <tbody>\n                                                                    <tr>\n                                                                        <td style="padding: 14px 20px; border-bottom: 1px solid #EBECED;">\n                                                                            <span style="color: #7A8B9A;">Course Name:</span>\n                                                                            <strong>[course_name]</strong>\n                                                                        </td>\n                                                                    </tr>\n                                                                    <tr>\n                                                                        <td style="padding: 14px 20px; border-bottom: 1px solid #EBECED;">\n                                                                            <span style="color: #7A8B9A;">Enrollment Date:</span>\n                                                                            <strong>[enrollment_date]</strong>\n                                                                        </td>\n                                                                    </tr>\n                                                                    <tr>\n                                                                        <td style="padding: 14px 20px;">\n                                                                            <span style="color: #7A8B9A;">Certificate:</span>\n                                                                            <a href="/path/to/certificate" style="color: '
									)
									.concat(
										y || '#6e42d3',
										';" download>Download Certificate</a>\n                                                                        </td>\n                                                                    </tr>\n                                                                </tbody>\n                                                            </table>\n                                                        </td>\n                                                    </tr>\n                                                </table>\n\n                                                <div style="margin: 0 0 15px 0; padding:0;">\n                                                    '
									)
									.concat(
										E,
										'\n                                                </div>\n\n                                                <ul class="related-course" style="padding: 0 0 0 35px; margin: 15px 0;">\n                                                    <li style="margin: 0 0 5px;"><a href="#" style="color: '
									)
									.concat(
										y || '#6e42d3',
										';">UX Design for Virtual Reality</a></li>\n                                                    <li style="margin: 0 0 5px;"><a href="#" style="color: '
									)
									.concat(
										y || '#6e42d3',
										';">AI for Designers</a></li>\n                                                </ul>\n\n                                                <div style="margin: 0 0 15px 0; padding:0; color: '
									)
									.concat(
										g || '#1f2328',
										'">\n                                                    '
									)
									.concat(
										_sLocal5,
										'\n                                                </div>\n\n                                                '
									)
									.concat(
										_cLocal5 && S,
										'\n                                            </div>\n                                        </td>\n                                    </tr>\n                                </table>\n                            </td>\n                        </tr>\n                    </table>\n                </td>\n            </tr>\n        </table>\n    </div>\n</body>\n\n</html>\n    '
									);
							} )( v ) )
						);
					} )(),
			( 0, ReactHooks.useEffect )(
				function () {
					var e = m.current;
					if ( e ) {
						(
							e.contentDocument || e.contentWindow.document
						).body.innerHTML = h;
					}
				},
				[ h ]
			),
			(
				<React.Fragment>
					<Ea
						style={ {
							backgroundColor:
								( v === null || v === void 0
									? void 0
									: v.background_color ) || '#F4F5F7',
						} }
					>
						<Controls.FlexWP
							justify={ 'center' }
							align={ 'center' }
						>
							<iframe
								ref={ m }
								width={ '100%' }
								height={ '667px' }
								style={
									'mobile' === c
										? {
												width: '515px',
											}
										: {}
								}
								title={ I18n.__( 'Email preview', 'ohmylms' ) }
							/>
						</Controls.FlexWP>
					</Ea>
				</React.Fragment>
			 )
		 );
	};
}
