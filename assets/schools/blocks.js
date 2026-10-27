(function (wp) {
  const names = {
    'student-registration': 'OhMyLMS Student Registration',
    'teacher-registration': 'OhMyLMS Teacher Registration',
    'parent-registration': 'OhMyLMS Parent Registration',
    'sign-in': 'OhMyLMS Sign In',
    'school-dashboard': 'OhMyLMS School Dashboard',
    'teacher-dashboard': 'OhMyLMS Teacher Dashboard',
    'parent-dashboard': 'OhMyLMS Parent Dashboard',
  };
  Object.entries(names).forEach(([name, title]) => {
    wp.blocks.registerBlockType('ohmylms/' + name, {
      apiVersion: 2,
      title: wp.i18n.__(title, 'ohmylms'),
      category: 'ohmylms',
      icon: 'welcome-learn-more',
      description: wp.i18n.__('School and family learning with private, account-specific access.', 'ohmylms'),
      edit: function () {
        return wp.element.createElement('div', wp.blockEditor.useBlockProps({ className: 'ohmylms-school-preview', style: { padding: '24px', border: '1px solid #d9e2ef', borderRadius: '12px' } }),
          wp.element.createElement('strong', null, title),
          wp.element.createElement('p', null, wp.i18n.__('This legacy block links to the learning hub. Registration, sign-in and school work now happen there.', 'ohmylms')));
      },
      save: function () { return null; },
    });
  });
})(window.wp);
